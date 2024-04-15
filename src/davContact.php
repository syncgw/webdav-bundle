<?php
declare(strict_types=1);

/*
 * 	SabreDAV cardDav (contact) handler class
 *
 *	@package	sync*gw
 *	@subpackage	SabreDAV support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\webdav;

use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\User;
use syncgw\lib\XML;
use syncgw\document\docContact;
use syncgw\document\field\fldDescription;
use syncgw\document\field\fldGroupName;

class davContact extends \Sabre\CardDAV\Backend\AbstractBackend implements \Sabre\CardDAV\Backend\SyncSupport {

   	/**
     * 	Singleton instance of object
     * 	@var davContact
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): davContact {

	  	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

    /**
     * Returns the list of addressbooks for a specific user.
     *
     * Every addressbook should have the following properties:
     *   id - an arbitrary unique id
     *   uri - the 'basename' part of the url
     *   principaluri - Same as the passed parameter
     *
     * Any additional clark-notation property may be passed besides this. Some
     * common ones are :
     *   {DAV:}displayname
     *   {urn:ietf:params:xml:ns:carddav}addressbook-description
     *   {http://calendarserver.org/ns/}getctag
     *
     * @param string $principalUri
     *
     * @return array
     */
	public function getAddressBooksForUser($principalUri) {

		// split URI
		list(, $gid) = \Sabre\Uri\split($principalUri);

		// get synchronization key
		$usr  = User::getInstance();
		$sync = $usr->syncKey('DAV-'.DataStore::CONTACT);

		$db   = DB::getInstance();
		$recs = [];

		// read all records
		if (!count($ids = $db->Query(DataStore::CONTACT, DataStore::GRPS))) {

			Log::getInstance()->logMsg(Log::WARN, 19002, 'contacts', $gid);
			return $recs;
		}

		foreach ($ids as $id => $unused) {

		    $doc  = $db->Query(DataStore::CONTACT, DataStore::RGID, $id);
	        $name = $doc->getVar(fldGroupName::TAG);
            $desc = $doc->getVar(fldDescription::TAG);

			// reset sync. status
    	    $db->setSyncStat(DataStore::CONTACT, $doc, DataStore::STAT_OK);

			$recs[] = [
			    'id'																=> $id,
				    'uri'															=> $name,
			    'principaluri'														=> $principalUri,
			    '{DAV:}displayname'													=> $name,
			    '{'.\Sabre\CardDAV\Plugin::NS_CARDDAV.'}addressbook-description'	=> $desc,
			    '{http://calendarserver.org/ns/}getctag'							=> $sync,
                '{http://sabredav.org/ns}sync-token'								=> $sync,
		    ];
		}
		$unused; // disable Eclipse warning

		Msg::InfoMsg($recs, 'Address books found');

		return $recs;
	}

    /**
     * Updates properties for an address book.
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
     * @param string $addressBookId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
	function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {

		Msg::InfoMsg('Update address book ['.$addressBookId.']');

		// valid address book?
		if (!$addressBookId)
			return;

		// load group record
		$db = DB::getInstance();
		$doc = $db->Query(DataStore::CONTACT, DataStore::RGID, $addressBookId);

		foreach($propPatch as $k => $v) {

			switch($k) {
			case '{DAV:}displayname':
				$doc->getVar('Data');
				$doc->updVar(fldGroupName::TAG, $v, false);
				$upd = true;
				break;

			case '{'.\Sabre\CardDAV\Plugin::NS_CARDDAV.'}addressbook-description':
				$doc->getVar('Data');
				$doc->updVar(fldDescription::TAG, $v, false);
				$upd = true;
				break;

			default:
				break;
			}
		}

		// any updates?
		if ($upd)
    		$db->Query(DataStore::CONTACT, DataStore::UPD, $doc);
	}

    /**
     * Creates a new address book.
     *
     * This method should return the id of the new address book. The id can be
     * in any format, including ints, strings, arrays or objects.
     *
     * @param string $principalUri
     * @param string $url Just the 'basename' of the url.
     * @param array $properties
     * @return mixed
     */
	public function createAddressBook($principalUri, $url, array $properties) {

		Msg::InfoMsg('Create address books for user ['.$principalUri.']');

		// set properties
		$prop = [ 'n' => $url, 'd' => '' ];

		foreach($properties as $k => $v) {

			switch($k) {
			case '{DAV:}displayname':
				$prop['n'] = $v;
				break;

			case '{'.\Sabre\CardDAV\Plugin::NS_CARDDAV.'}addressbook-description':
				$prop['d'] = $v;
				break;

			default :
				throw new \Sabre\DAV\Exception\BadRequest('Unknown property: '.$k);
			}
		}

		$db  = DB::getInstance();
		$xml = $db->mkDoc(DataStore::CONTACT,
						  [ fldGroupName::TAG => $prop['n'], fldDescription::TAG => $prop['d'] ], true);

		return $xml->getVar('GUID');
	}

    /**
     * Deletes an entire addressbook and all its contents.
     *
     * @param mixed $addressBookId
     * @return void
     */
	public function deleteAddressBook($addressBookId) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

		Msg::InfoMsg('Delete addresss book ['.$addressBookId.']');

		$db = DB::getInstance();
        $db->Query(DataStore::CONTACT, DataStore::DEL, $addressBookId);
	}

    /**
     * Returns all cards for a specific addressbook id.
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp
     *
     * It's recommended to also return the following properties:
     *   * etag - A unique etag. This must change every time the card changes.
     *   * size - The size of the card in bytes.
     *
     * If these last two properties are provided, less time will be spent
     * calculating them. If they are specified, you can also ommit carddata.
     * This may speed up certain requests, especially with large cards.
     *
     * @param mixed $addressbookId
     * @return array
     */
	public function getCards($addressbookId) {

		if (is_array($addressbookId))
    		list($addressbookId, ) = $addressbookId;

		$dav = davHandler::getInstance();
		$db  = DB::getInstance();
		$ds  = docContact::getInstance();

		// export records
		$recs = [];
		foreach ($db->Query(DataStore::CONTACT, DataStore::RIDS, $addressbookId) as $gid => $typ) {

			if ($typ == DataStore::TYP_GROUP)
		        continue;

			if (!($doc = $db->Query(DataStore::CONTACT, DataStore::RGID, $gid)))
				break;

			// really delete deleted records
			if ($doc->getVar('SyncStat') == DataStore::STAT_DEL) {

				$db->Query(DataStore::CONTACT, DataStore::DEL, $gid);
				continue;
			}

			$out = new XML();
    		$ds->export($out, $doc, $dav::$mime);

    		$data   = str_replace("\r", '', $out->getVar('Data'));
    		$etag   = '"'.$doc->getVar('CRC').'"';
    		$id     = ($lid = $doc->getVar('LUID')) ? $lid : $gid;
    		$recs[] = [
    				'etag'			=> $etag,
    		        'carddata' 		=> $data,
    				'uri'			=> $id.'.vcf',
    				'lastmodified'	=> $doc->getVar('LastMod'),
    		        'size'			=> strlen($data),
    		];
		}

		// group is synchronized
    	if ($doc = $db->Query(DataStore::CONTACT, DataStore::RGID, $addressbookId))
       		$db->setSyncStat(DataStore::CONTACT, $doc, DataStore::STAT_OK);

    	Msg::InfoMsg($recs, 'All contacts in ['.$addressbookId.']');

		return $recs;
	}

    /**
     * Returns a specfic card.
     *
     * The same set of properties must be returned as with getCards. The only
     * exception is that 'carddata' is absolutely required.
     *
     * If the card does not exist, you must return false.
     *
     * @param mixed  $addressBookId
     * @param string $cardUri
     * @return array
     */
	public function getCard($addressBookId, $cardUri) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

    	// serialize object?
    	$id = $cardUri && strpos($cardUri, '.vcf') !== false ? substr($cardUri, 0, -4) : $cardUri;

		$db = DB::getInstance();
		$ds = docContact::getInstance();

		// load data record - we expect <GUID>
		if (!($doc = $db->Query(DataStore::CONTACT, DataStore::RGID, $id))) {

			// load record according to <LUID>
			if (!($doc = $db->Query(DataStore::CONTACT, DataStore::RLID, $id)))
				return [];
		}

		// export record
		$out = new XML();
		$dav = davHandler::getInstance();
		$ds->export($out, $doc, $dav::$mime);

		// update status of record
		$db->setSyncStat(DataStore::CONTACT, $doc, DataStore::STAT_OK);

		$data = str_replace("\r", '', $out->getVar('Data'));
        $etag = '"'.$doc->getVar('CRC').'"';
		$id   = ($lid = $doc->getVar('LUID')) ? $lid : $id;
		$rec  = [
    		    'etag'			=> $etag,
		        'carddata' 		=> $data,
				'uri'			=> $id.'.vcf',
				'lastmodified'	=> $doc->getVar('LastMod'),
		        'size'			=> strlen($data),
		];

    	Msg::InfoMsg($rec, 'Single contact ['.$id.'] in group ['.$addressBookId.']');

		return $rec;
	}

    /**
     * Returns a list of cards.
     *
     * This method should work identical to getCard, but instead return all the
     * cards in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param mixed $addressBookId
     * @param array $uris
     * @return array
     */
	public function getMultipleCards($addressBookId, array $uris) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

		$recs = [];
		foreach ($uris as $uri)
		    $recs[] = self::getCard($addressBookId, $uri);

		return $recs;
	}

    /**
     * Creates a new card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag is for the
     * newly created resource, and must be enclosed with double quotes (that
     * is, the string itself must contain the double quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
	public function createCard($addressBookId, $cardUri, $cardData) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

    	// serialize object?
    	if (strpos($cardUri, '.vcf') !== false)
    	    $cardUri = substr($cardUri, 0, -4);

    	Msg::InfoMsg([ $cardData ], 'Creating contact ['.$cardUri.'] in ['.$addressBookId.']');

		// check for existing LUID
		$db = DB::getInstance();
		if ($db->Query(DataStore::CONTACT, DataStore::RLID, $cardUri)) {

			Msg::WarnMsg('Record ['.$cardUri.'] already exists');
			return null;
		}

		// create document
		$doc = new XML();
		$doc->loadXML('<Data>'.$doc->cnvStr($cardData).'</Data>');

		// import data
		$ds = docContact::getInstance();
		if ($ds->import($doc, DataStore::ADD, $addressBookId))
            $etag = '"'.$ds->getVar('CRC').'"';
        else {

        	Msg::WarnMsg('Error adding record in ['.$addressBookId.']');
		    $etag = null;
        }

	    Msg::InfoMsg($doc, 'Create contact ETag=['.$etag.']');

		return $etag;
	}

    /**
     * Updates a card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag should
     * match that of the updated resource, and must be enclosed with double
     * quotes (that is: the string itself must contain the actual quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
	public function updateCard($addressBookId, $cardUri, $cardData) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

	   	// serialize object?
    	if (strpos($cardUri, '.vcf') !== false)
    	    $cardUri = substr($cardUri, 0, -4);

		// get document
		$db = DB::getInstance();
		$xml = $db->Query(DataStore::CONTACT, DataStore::RGID, $cardUri);

		// import data
		$ds = docContact::getInstance();
		$ds->loadXML($xml->saveXML());

		$doc = new XML();
		$doc->loadXML('<Data>'.$doc->cnvStr($cardData).'</Data>');
		if ($ds->import($doc, DataStore::UPD, $addressBookId))
            $etag = '"'.$ds->getVar('CRC').'"';
        else {

        	Msg::WarnMsg('Error updating ['.$ds->getVar('GUID').'] in ['.$addressBookId.']');
		    $etag = null;
        }

	    Msg::InfoMsg($doc, 'Update contact RC=['.$etag.']');

		return $etag;
	}

    /**
     * Deletes a card.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return bool
     */
	public function deleteCard($addressBookId, $cardUri) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

		Msg::InfoMsg('Deleting contact ['.$cardUri.'] in ['.$addressBookId.']');

    	// serialize object?
    	if (strpos($cardUri, '.vcf') !== false)
    	    $cardUri = substr($cardUri, 0, -4);

		$ds = docContact::getInstance();

		return $ds->delete($cardUri);
	}

    /**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified address book.
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
     * of the addressbook, as reported in the {http://sabredav.org/ns}sync-token
     * property. This is needed here too, to ensure the operation is atomic.
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
     * @param string $addressBookId
     * @param string $syncToken
     * @param int $syncLevel
     * @param int $limit
     * @return array
     */
	public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null) {

		if (is_array($addressBookId))
    		list($addressBookId, ) = $addressBookId;

		Msg::InfoMsg('Get changes for address book ['.$addressBookId.'] with SyncToken ['.strval($syncToken).'}');

        $db = DB::getInstance();

		$ds = docContact::getInstance();
        $ds->syncDS($addressBookId);

        // get address book
        if (!($doc = $db->Query(DataStore::CONTACT, DataStore::RGID, $addressBookId))) {

            Msg::WarnMsg('Address book not found!');
            return null;
        }

        // output array
        $recs = [
    	   'syncToken' => $syncToken,
           'added'     => [],
           'modified'  => [],
           'deleted'   => [],
        ];

		foreach ($db->Query(DataStore::CONTACT, DataStore::RIDS, $addressBookId) as $gid => $typ) {

			// skip folders
			if ($typ == DataStore::TYP_GROUP)
                continue;

			// load record
            if (!($doc = $db->Query(DataStore::CONTACT, DataStore::RGID, $gid))) {

				Msg::WarnMsg('Error reading record ['.$gid.']');
            	continue;
            }

            // get record id
			if (!($id = $doc->getVar('LUID')))
    			$id = $doc->getVar('GUID');
			$id .= '.vcf';

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

	    Msg::InfoMsg($recs, 'Synchronization status for adress book ['.$addressBookId.']');

		return $recs;
    }

}
