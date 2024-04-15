<?php
declare(strict_types=1);

/*
 * 	SabreDAV calDav (task) handler class
 *
 *	@package	sync*gw
 *	@subpackage	SabreDAV support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\webdav;

use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\HTTP;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\User;
use syncgw\lib\XML;
use syncgw\document\docTask;
use syncgw\document\field\fldColor;
use syncgw\document\field\fldDescription;
use syncgw\document\field\fldEndTime;
use syncgw\document\field\fldGroupName;
use syncgw\document\field\fldStartTime;

class davTask extends \Sabre\CalDAV\Backend\AbstractBackend implements \Sabre\CalDAV\Backend\SyncSupport {

   	/**
     * 	Singleton instance of object
     * 	@var davTask
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): davTask {

	  	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

    /**
     * Returns a list of calendars for a principal.
     *
     * Every project is an array with the following keys:
     *  * id, a unique id that will be used by other functions to modify the
     *    calendar. This can be the same as the uri or a database key.
     *  * uri. This is just the 'base uri' or 'filename' of the calendar.
     *  * principaluri. The owner of the calendar. Almost always the same as
     *    principalUri passed to this method.
     *
     * Furthermore it can contain webdav properties in clark notation. A very
     * common one is '{DAV:}displayname'.
     *
     * Many clients also require:
     * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
     * For this property, you can just return an instance of
     * Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet.
     *
     * If you return {http://sabredav.org/ns}read-only and set the value to 1,
     * ACL will automatically be put in read-only mode.
     *
     * @param string $principalUri
     * @return array
     */
	public function getCalendarsForUser($principalUri) {

		// split URI
		list(, $gid) = \Sabre\Uri\split($principalUri);

		// get synchronization key
   	    $usr  = User::getInstance();
		$sync = $usr->syncKey('DAV-'.DataStore::TASK);

		$db   = DB::getInstance();
		$recs = [];

		// read all records
		if (!count($ids = $db->Query(DataStore::TASK, DataStore::GRPS))) {

			Log::getInstance()->logMsg(Log::WARN, 19002, 'task list', $gid);
			return $recs;
		}

		foreach ($ids as $id => $unused) {

		    if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $id)))
                continue;

            $rec = [
                'uri'
            			=> $doc->getVar(fldGroupName::TAG),
    			'{DAV:}displayname'
            			=> $doc->getVar(fldGroupName::TAG),
                '{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description'
            			=> $doc->getVar(fldDescription::TAG),
                '{http://apple.com/ns/ical/}calendar-color'
            			=> $doc->getVar(fldColor::TAG),
                '{http://sabredav.org/ns}sync-token'
            			=> $sync,
                '{'.\Sabre\CalDAV\Plugin::NS_CALENDARSERVER.'}getctag'
            			=> $sync,
			    '{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}schedule-calendar-transp'
            			=> new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp('transparent'),
            ];

			$rec['id']
					= $id;
			$rec['principaluri']
					= $principalUri;
			$rec['{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}supported-calendar-component-set']
					= new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet([ 'VTODO' ]);
			$rec['{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-timezone']
					= 'UTC';

			$recs[] = $rec;
		}
		$unused; // disable Eclipse warning

		Msg::InfoMsg($recs, 'Task lists found');

		return $recs;
	}

    /**
     * Creates a new calendar for a principal.
     *
     * If the creation was a success, an id must be returned that can be used
     * to reference this calendar in other methods, such as updateCalendar.
     *
     * @param string $principalUri
     * @param string $calendarUri
     * @param array $properties
     * @return string
     */
	public function createCalendar($principalUri, $calendarUri, array $properties) {

		Msg::InfoMsg($properties, 'Create task list for user ['.$principalUri.']');

		// property array
		$prop = [];

		// swap properties
		foreach ([
		    '{DAV:}displayname'											=> 'n',
        	'{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description' => 'd',
		    '{http://apple.com/ns/ical/}calendar-color'					=> 'c' ] as $k => $v)
			$prop[$v] = isset($properties[$k]) ? $properties[$k] : '';

		$db  = DB::getInstance();
		$xml = $db->mkDoc(DataStore::TASK, [ fldGroupName::TAG => $prop['n'],
						  fldDescription::TAG => $prop['d'], fldColor::TAG => $prop['c'] ], true);

		return $xml->getVar('GUID');
	}

    /**
     * Updates properties for a calendar.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documentation for more info and examples.
     *
     * @param mixed $calendarId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
	public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

		Msg::InfoMsg('Update task list ['.$calendarId.']');

		$db = DB::getInstance();

		// load group definition
		if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $calendarId)))
		    return;

        $props = [
			'{DAV:}displayname'											=> fldGroupName::TAG,
			'{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description'	=> fldDescription::TAG,
			'{http://apple.com/ns/ical/}calendar-color'					=> fldColor::TAG,
        ];

       $propPatch->handle(array_keys($props), function($mutations) use ($props, $doc, $db)  {

    	    // no updates
	   	    $upd = false;

            foreach ($mutations as $k => $v) {

                if (!isset($props[$k])) {
                    continue;
                }

			    $doc->getVar('Data');
			    $doc->updVar($props[$k], $v, false);
				$upd = true;
            }

		    // any updates?
		    if ($upd)
    		     $db->Query(DataStore::TASK, DataStore::UPD, $doc);
       });
    }

    /**
     * Delete a calendar and all its objects
     *
     * @param mixed $calendarId
     * @return void
     */
    public function deleteCalendar($calendarId) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

		Msg::InfoMsg('Deleting task list ['.$calendarId.']');

		DB::getInstance()->Query(DataStore::TASK, DataStore::DEL, $calendarId);
	}

    /**
     * Returns all calendar objects within a calendar.
     *
     * Every item contains an array with the following keys:
     *   * calendardata - The iCalendar-compatible calendar data
     *   * uri - a unique key which will be used to construct the uri. This can
     *     be any arbitrary string, but making sure it ends with '.ics' is a
     *     good idea. This is only the basename, or filename, not the full
     *     path.
     *   * lastmodified - a timestamp of the last modification time
     *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
     *   '  "abcdef"')
     *   * size - The size of the calendar objects, in bytes.
     *   * component - optional, a string containing the type of object, such
     *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
     *     the Content-Type header.
     *
     * Note that the etag is optional, but it's highly encouraged to return for
     * speed reasons.
     *
     * The calendardata is also optional. If it's not returned
     * 'getCalendarObject' will be called later, which *is* expected to return
     * calendardata.
     *
     * If neither etag or size are specified, the calendardata will be
     * used/fetched to determine these numbers. If both are specified the
     * amount of times this is needed is reduced by a great degree.
     *
     * @param mixed $calendarId
     * @return array
     */
	public function getCalendarObjects($calendarId) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

		$dav = davHandler::getInstance();
    	$db  = DB::getInstance();
    	$ds  = docTask::getInstance();

		// group is synchronized
		if ($doc = $db->Query(DataStore::TASK, DataStore::RGID, $calendarId))
      		$db->setSyncStat(DataStore::TASK, $doc, DataStore::STAT_OK);

		// export records
		$recs = [];
		foreach ($db->Query(DataStore::TASK, DataStore::RIDS, $calendarId) as $gid => $typ) {

			if ($typ == DataStore::TYP_GROUP)
				continue;

			if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $gid)))
				break;

			// really delete deleted records
			if ($doc->getVar('SyncStat') == DataStore::STAT_DEL) {

				$db->Query(DataStore::TASK, DataStore::DEL, $gid);
				continue;
			}

			// special hack to create <LUID>
			if (!$doc->getVar('LUID') && ($id = $doc->getVar('UID'))) {

				$doc->updVar('LUID', $id);
				$db->Query(DataStore::TASK, DataStore::UPD, $doc);
			}

			$out = new XML();
			$ds->export($out, $doc, $dav::$mime);

			$data   = str_replace("\r", '', $out->getVar('Data'));
			$etag   = '"'.$doc->getVar('CRC').'"';
			$id 	= $doc->getVar('LUID');
			$recs[] = [
					'id'			=> $id,
					'uri'			=> $id.'.ics',
					'lastmodified'	=> $doc->getVar('LastMod'),
					'etag'			=> $etag,
			        'calendarid'	=> $calendarId,
					'size'			=> strlen($data),
		    	    'calendardata' 	=> $data,

			        'component'     => 'vtodo',
			];
		}

		Msg::InfoMsg($recs, 'All todos in task list ['.$calendarId.']');

		return $recs;
	}

    /**
     * Returns information from a single calendar object, based on it's object
     * uri.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * The returned array must have the same keys as getCalendarObjects. The
     * 'calendardata' object is required here though, while it's not required
     * for getCalendarObjects.
     *
     * This method must return null if the object did not exist.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @return array|null
     */
 	public function getCalendarObject($calendarId, $objectUri) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

     	// serialize object?
    	if (strpos($objectUri, '.ics') !== false)
    	    $objectUri = substr($objectUri, 0, -4);

    	$db  = DB::getInstance();
		$ds  = docTask::getInstance();

		// load data record - we expect <GUID>
        if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $objectUri))) {

			// load record according to <LUID>
        	if (!($doc = $db->Query(DataStore::TASK, DataStore::RLID, $objectUri)))
				return [];
 		}

    	// unfortunately SabreDAV has a small bug, which prevents updates of records
    	$http = HTTP::getInstance();
    	if ($http->getHTTPVar('REQUEST_METHOD') == 'PUT')
    		return [];

		// special hack to create <LUID>
		if (!$doc->getVar('LUID') && ($id = $doc->getVar('UID'))) {

			$doc->updVar('LUID', $id);
			$db->Query(DataStore::TASK, DataStore::UPD, $doc);
		}

		// export record
		$out = new XML();
		$dav = davHandler::getInstance();
		$ds->export($out, $doc, $dav::$mime);

		// update status of record
		$db->setSyncStat(DataStore::TASK, $doc, DataStore::STAT_OK);

		$data = str_replace("\r", '', $out->getVar('Data'));
		$etag = '"'.$doc->getVar('CRC').'"';
		$id 	= $doc->getVar('LUID');
		$rec  = [
				'id'			=> $id,
				'uri'			=> $id.'.ics',
		        'lastmodified'	=> $doc->getVar('LastMod'),
				'etag'			=> $etag,
		        'calendarid'	=> $calendarId,
		        'size'          => strlen($data),
		        'calendardata' 	=> $data,
			    'component'     => 'vtodo',
		];

		Msg::InfoMsg($rec, 'Get todo ['.$objectUri.'] in task list ['.$calendarId.']');

		return $rec;
	}

    /**
     * Returns a list of calendar objects.
     *
     * This method should work identical to getCalendarObject, but instead
     * return all the calendar objects in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param mixed $calendarId
     * @param array $uris
     * @return array
     */
	public function getMultipleCalendarObjects($calendarId, array $uris) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

		$recs = [];
		foreach ($uris as $uri)
		    $recs[] = self::getCalendarObject($calendarId, $uri);

		return $recs;
	}

    /**
     * Creates a new calendar object.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return string|null
     */
	public function createCalendarObject($calendarId, $objectUri, $calendarData) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

 		// serialize object?
    	if (strpos($objectUri, '.ics') !== false)
    	    $objectUri = substr($objectUri, 0, -4);

	   	$db = DB::getInstance();

		// unfortunately SabreDAV has a small bug, which prevents updates of records
    	if (HTTP::getInstance()->getHTTPVar('REQUEST_METHOD') == 'PUT') {

    		if ($db->Query(DataStore::TASK, DataStore::RLID, $objectUri))
	    		return self::updateCalendarObject($calendarId, $objectUri, $calendarData);
    	}

    	Msg::InfoMsg([ $calendarData ], 'Creating todo ['.$objectUri.'] in task list ['.$calendarId.']');

    	// check for existing LUID
		$db = DB::getInstance();
		if ($db->Query(DataStore::CALENDAR, DataStore::RLID, $objectUri)) {

			Msg::WarnMsg('Record ['.$calendarId.'] already exists');
			return null;
		}

		// create document
		$doc = new XML();
		$doc->loadXML('<Data>'.$doc->cnvStr($calendarData).'</Data>');

		// import data
		$ds = docTask::getInstance();
		if ($ds->import($doc, DataStore::ADD, $calendarId))
            $etag = '"'.$ds->getVar('CRC').'"';
       	else {

        	Msg::WarnMsg('Error adding record in ['.$calendarId.']');
		    $etag = null;
        }

	    Msg::InfoMsg($doc, 'Create todo ETag=['.$etag.']');

		return $etag;
	}

    /**
     * Updates an existing calendarobject, based on it's uri.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return string|null
     */
	public function updateCalendarObject($calendarId, $objectUri, $calendarData) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

    	// serialize object?
    	if (strpos($objectUri, '.ics') !== false)
    	    $objectUri = substr($objectUri, 0, -4);

 	 	Msg::InfoMsg([ $calendarData ], 'Update todo ['.$objectUri.'] in task list ['.$calendarId.']');

		// get document
		$db = DB::getInstance();
		if (!($xml = $db->Query(DataStore::TASK, DataStore::RLID, $objectUri)))
			$xml = $db->Query(DataStore::TASK, DataStore::RGID, $objectUri);

		// import data
		$ds = docTask::getInstance();
		$ds->loadXML($xml->saveXML());

		// import data
		$doc = new XML();
		$doc->loadXML('<Data>'.$doc->cnvStr($calendarData).'</Data>');
		if ($ds->import($doc, DataStore::UPD, $calendarId))
            $etag = '"'.$ds->getVar('CRC').'"';
        else {

        	Msg::WarnMsg('Error updating ['.$ds->getVar('GUID').'] in ['.$calendarId.']');
		    $etag = null;
        }

	    Msg::InfoMsg($ds, 'Update todo ETag=['.$etag.']');

		return $etag;
	}

    /**
     * Deletes an existing calendar object.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @return void
     */
    public function deleteCalendarObject($calendarId, $objectUri) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

        Msg::InfoMsg('Deleting todo ['.$objectUri.'] in task list ['.$calendarId.']');

    	// serialize object?
    	if (strpos($objectUri, '.ics') !== false)
    	    $objectUri = substr($objectUri, 0, -4);

		$ds = docTask::getInstance();

		return $ds->delete($objectUri);
	}

    /**
     * Performs a calendar-query on the contents of this calendar.
     *
     * The calendar-query is defined in RFC4791 : CalDAV. Using the
     * calendar-query it is possible for a client to request a specific set of
     * object, based on contents of iCalendar properties, date-ranges and
     * iCalendar component types (VTODO, VEVENT).
     *
     * This method should just return a list of (relative) urls that match this
     * query.
     *
     * The list of filters are specified as an array. The exact array is
     * documented by \Sabre\CalDAV\CalendarQueryParser.
     *
     * Note that it is extremely likely that getCalendarObject for every path
     * returned from this method will be called almost immediately after. You
     * may want to anticipate this to speed up these requests.
     *
     * This method provides a default implementation, which parses *all* the
     * iCalendar objects in the specified calendar.
     *
     * This default may well be good enough for personal use, and calendars
     * that aren't very large. But if you anticipate high usage, big calendars
     * or high loads, you are strongly adviced to optimize certain paths.
     *
     * The best way to do so is override this method and to optimize
     * specifically for 'common filters'.
     *
     * Requests that are extremely common are:
     *   * requests for just VEVENTS
     *   * requests for just VTODO
     *   * requests with a time-range-filter on a VEVENT.
     *
     * ..and combinations of these requests. It may not be worth it to try to
     * handle every possible situation and just rely on the (relatively
     * easy to use) CalendarQueryValidator to handle the rest.
     *
     * Note that especially time-range-filters may be difficult to parse. A
     * time-range filter specified on a VEVENT must for instance also handle
     * recurrence rules correctly.
     * A good example of how to interpret all these filters can also simply
     * be found in \Sabre\CalDAV\CalendarQueryFilter. This class is as correct
     * as possible, so it gives you a good idea on what type of stuff you need
     * to think of.
     *
     * This specific implementation (for the PDO) backend optimizes filters on
     * specific components, and VEVENT time-ranges.
     *
     * @param mixed $calendarId
     * @param array $filters
     * @return array
     */
	public function calendarQuery($calendarId, array $filters) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

        Msg::InfoMsg($filters, 'Performing query on task list ['.$calendarId.']');

        // check for time range
        $tr = isset($filters['comp-filters'][0]['time-range']) ? $filters['comp-filters'][0]['time-range'] : '';
        if ($tr && isset($tr['start'])) {

            $s = $tr['start'];
            $s->setTimezone(new \DateTimeZone('UTC'));
        } else
            $s = 0;

        if ($tr && isset($tr['end'])) {

            $e = $tr['end'];
            $e->setTimezone(new \DateTimeZone('UTC'));
        } else
            $e = 0;

		// export records
		$rc = [];
		$db = DB::getInstance();
		foreach ($db->Query(DataStore::TASK, DataStore::RIDS, $calendarId) as $gid => $typ) {

			if ($typ != DataStore::TYP_DATA)
				continue;

			if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $gid)))
				break;

			// really delete deleted records
			if ($doc->getVar('SyncStat') == DataStore::STAT_DEL) {

				$db->Query(DataStore::TASK, DataStore::DEL, $gid);
				continue;
			}

			if ($s) {
			    // reach into time window?
			    if ($ct = $doc->getVar(fldEndTime::TAG)) {

			    	if ($ct < $s->getTimestamp())
			            continue;
			    }
			}

			if ($e) {
			    // start in time window?
			    if ($ct = $doc->getVar(fldStartTime::TAG)) {

			        if ($ct > $e->getTimestamp())
			            continue;
			    }
			}

			// save uri
			$rc[] = (($lid = $doc->getVar('LUID')) ? $lid : $gid).'.ics';
		}

		Msg::InfoMsg($rc, 'Todos found in task list ['.$calendarId.']');

		return $rc;
	}

    /**
     * Searches through all of a users calendars and calendar objects to find
     * an object with a specific UID.
     *
     * This method should return the path to this object, relative to the
     * calendar home, so this path usually only contains two parts:
     *
     * calendarpath/objectpath.ics
     *
     * If the uid is not found, return null.
     *
     * This method should only consider * objects that the principal owns, so
     * any calendars owned by other principals that also appear in this
     * collection should be ignored.
     *
     * @param string $principalUri
     * @param string $uid
     * @return string|null
     */
	function getCalendarObjectByUID($principalUri, $uid) {

		$db = DB::getInstance();

     	// serialize object?
    	if (strpos($uid, '.ics') !== false)
    	    $uid = substr($uid, 0, -4);

   	    if ($rc = $db->Query(DataStore::TASK, DataStore::RLID, $uid)) {

    		$grp = $db->Query(DataStore::TASK, DataStore::RGID, $rc->getVar('Group'));
    		$rc = $grp->getVar(fldGroupName::TAG).'/'.$uid.'.ics';
   	    }

   	    return $rc;
	}

    /**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified calendar.
     *
     * This function should return an array, such as the following:
     *
     * [
     *   'syncToken' => 'The current synctoken',
     *   'added'   => [
     *      'new.txt',
     *   ],
     *   'modified'   => [
     *      'modified.txt',
     *   ],
     *   'deleted' => [
     *      'foo.php.bak',
     *      'old.txt'
     *   ]
     * ];
     *
     * The returned syncToken property should reflect the *current* syncToken
     * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
     * property this is needed here too, to ensure the operation is atomic.
     *
     * If the $syncToken argument is specified as null, this is an initial
     * sync, and all members should be reported.
     *
     * The modified property is an array of nodenames that have changed since
     * the last token.
     *
     * The deleted property is an array with nodenames, that have been deleted
     * from collection.
     *
     * The $syncLevel argument is basically the 'depth' of the report. If it's
     * 1, you only have to report changes that happened only directly in
     * immediate descendants. If it's 2, it should also include changes from
     * the nodes below the child collections. (grandchildren)
     *
     * The $limit argument allows a client to specify how many results should
     * be returned at most. If the limit is not specified, it should be treated
     * as infinite.
     *
     * If the limit (infinite or not) is higher than you're willing to return,
     * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
     *
     * If the syncToken is expired (due to data cleanup) or unknown, you must
     * return null.
     *
     * The limit is 'suggestive'. You are free to ignore it.
     *
     * @param mixed $calendarId
     * @param string $syncToken
     * @param int $syncLevel
     * @param int $limit
     * @return array
     */
    function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null) {

		if (is_array($calendarId))
    		list($calendarId, ) = $calendarId;

    	Msg::InfoMsg('Get changes for todo in task list ['.$calendarId.'] with SyncToken ['.strval($syncToken).']');

        $db = DB::getInstance();

        // get calendar
        if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $calendarId))) {

        	Msg::InfoMsg('Task list not found!');
	       	return null;
        }

        // output array
        $recs = [
            'syncToken' => $syncToken,
            'added'     => [],
            'modified'  => [],
            'deleted'   => [],
        ];

		foreach ($db->Query(DataStore::TASK, DataStore::RIDS, $calendarId) as $gid => $typ) {

			// skip folders
			if ($typ != DataStore::TYP_DATA)
                continue;

			// load record
			if (!($doc = $db->Query(DataStore::TASK, DataStore::RGID, $gid)))
				continue;

			if (!($id = $doc->getVar('LUID')))
    			$id = $doc->getVar('GUID');
			$id .= '.ics';

			switch ($doc->getVar('SyncStat')) {
			case DataStore::STAT_ADD:
			    $recs['added'][] = $id;
			    break;

			case DataStore::STAT_DEL:
			    $recs['deleted'][] = $id;
			    break;

			case DataStore::STAT_REP:
			    $recs['modified'][] = $id;
			    break;

			default:
			    if (!$syncToken)
                    $recs['added'][] = $id;
			    break;
			}
		}

    	Msg::InfoMsg($recs, 'Synchronization status for task list ['.$calendarId.']');

		return $recs;
    }

}
