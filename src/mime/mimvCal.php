<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for calendar data class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\webdav\mime;

use syncgw\document\mime\mimHandler;
use syncgw\lib\DataStore;
use syncgw\lib\XML;

class mimvCal extends mimHandler {

	const MIME = [

		[ 'text/calendar',		2.0 ],
		[ 'text/x-vcalendar',	2.0 ],
		[ 'text/x-vcalendar',	1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	 	'VCALENDAR/BEGIN'															=> 'fldBegin',
	//	'VCALENDAR/VERSION'																// Ignored
	//	'VCALENDAR/X-PRODID'															// Ignored
	//	'VCALENDAR/CALSCALE'															// Ignored
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
		'VCALENDAR/VEVENT/BEGIN'													=> 'fldBegin',
	//  'VCALENDAR/VEVENT/DTSTAMP'														// Ignored
	    'VCALENDAR/VEVENT/UID,VCALENDAR/VEVENT/X-IRMC-LUID,'						=> 'fldUid',
	    'VCALENDAR/VEVENT/DTSTART'													=> 'fldStartTime',
	    'VCALENDAR/VEVENT/CLASS'													=> 'fldClass',
        'VCALENDAR/VEVENT/CREATED,VCALENDAR/VEVENT/DCREATED,'						=> 'fldCreated',
	    'VCALENDAR/VEVENT/DESCRIPTION'												=> 'fldBody',
	    'VCALENDAR/VEVENT/GEO'														=> 'fldGeoPosition',
	    'VCALENDAR/VEVENT/LAST-MODIFIED'											=> 'fldLastMod',
	    'VCALENDAR/VEVENT/LOCATION'													=> 'fldLocation',
		'VCALENDAR/VEVENT/ORGANIZER'												=> 'fldOrganizer',
    	'VCALENDAR/VEVENT/PRIORITY'													=> 'fldPriority',
	    'VCALENDAR/VEVENT/SEQUENCE'													=> 'fldSequence',
    	'VCALENDAR/VEVENT/STATUS'													=> 'fldStatus',
		'VCALENDAR/VEVENT/SUMMARY'													=> 'fldSummary',
	    'VCALENDAR/VEVENT/TRANSP'													=> 'fldTransparency',
	    'VCALENDAR/VEVENT/URL'														=> 'fldURLs',
	    'VCALENDAR/VEVENT/RECURRENCE-ID'											=> 'fldRecurrenceId',
		'VCALENDAR/VEVENT/RRULE'													=> 'fldRecurrence',
    	'VCALENDAR/VEVENT/DTEND'													=> 'fldEndTime',
        'VCALENDAR/VEVENT/DURATION'													=> 'fldDuration',
	    'VCALENDAR/VEVENT/ATTACH'													=> 'fldAttach',
		'VCALENDAR/VEVENT/ATTENDEE'													=> 'fldAttendee',
  	    'VCALENDAR/VEVENT/CATEGORIES,VCALENDAR/VEVENT/X-EPOCAGENDAENTRYTYPE,'		=> 'fldCategories',
	    'VCALENDAR/VEVENT/COMMENT'													=> 'fldComment',
	    'VCALENDAR/VEVENT/CONTACT'													=> 'fldContact',
	    'VCALENDAR/VEVENT/EXDATE'													=> 'fldExceptions',
	//  'VCALENDAR/VEVENT/EXRULE'														// Depreciated
	    'VCALENDAR/VEVENT/REQUEST-STATUS'											=> 'fldRequestStatus',
	    'VCALENDAR/VEVENT/RELATED-TO'												=> 'fldRelated',
    	'VCALENDAR/VEVENT/RESOURCES'												=> 'fldResource',
	    'VCALENDAR/VEVENT/RDATE'													=> 'fldRecurrenceDate',

	    // RFC7086
	    'VCALENDAR/VEVENT/NAME'														=> 'fldName',
	    'VCALENDAR/VEVENT/REFRESH-INTERVAL'											=> 'fldRefreshInterval',
	    'VCALENDAR/VEVENT/SOURCE'													=> 'fldSource',
        'VCALENDAR/VEVENT/COLOR'													=> 'fldColor',
	    'VCALENDAR/VEVENT/IMAGE'													=> 'fldPhoto',
        'VCALENDAR/VEVENT/CONFERENCE'												=> 'fldConference',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// 	'VCALENDAR/VEVENT/VALARM/BEGIN'                                              	// Handled by fldAlarm
    //	'VCALENDAR/VEVENT/DALARM'														// Handled by fldAlarm
    //	'VCALENDAR/VEVENT/MALARM'														// Handled by fldAlarm
    //	'VCALENDAR/VEVENT/AALARM'														// Handled by fldAlarm
    //	'VCALENDAR/VEVENT/PALARM'														// Ignored
        'VCALENDAR/VEVENT/VALARM/ACTION'                                             => 'fldAlarm',
	//	'VCALENDAR/VEVENT/VALARM/SUMMARY'                                            	// Handled by fldAlarm
	//	'VCALENDAR/VEVENT/VALARM/DESCRIPTION'                                        	// Handled by fldAlarm
    //	'VCALENDAR/VEVENT/VALARM/TRIGGER'                                            	// Handled by fldAlarm
	//	'VCALENDAR/VEVENT/VALARM/DURATION'												// Handled by fldAlarm
	//	'VCALENDAR/VEVENT/VALARM/REPEAT'                                             	// Handled by fldAlarm
	//	'VCALENDAR/VEVENT/VALARM/ATTACH'                                             	// Handled by fldAlarm
	//	'VCALENDAR/VEVENT/VALARM/ATTENDEE'                                           	// Handled by fldAlarm
	//	'VCALENDAR/VEVENT/VALARM/END'                  									// Handled by fldAlarm
	    'VCALENDAR/VEVENT/END'														=> 'fldEnd',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    //	'VCALENDAR/VTIMEZONE/BEGIN'                                              		// Handled by fldTimezone
      	'VCALENDAR/VTIMEZONE/TZID,VCALENDAR/TZ,'									=> 'fldTimezone',
	//	'VCALENDAR/VTIMEZONE/LAST-MODIFIED'												// Ignored
	//	'VCALENDAR/VTIMEZONE/TZURL'														// Ignored
	//	'VCALENDAR/VTIMEZONE/STANDARD/BEGIN'											// Handled by fldTimezone
	//  'VCALENDAR/VTIMEZONE/STANDARD/DTSTART'											// Handled by fldTimezone
	//  'VCALENDAR/VTIMEZONE/STANDARD/TZOFFSETTO'										// Handled by fldTimezone
    //  'VCALENDAR/VTIMEZONE/STANDARD/TZOFFSETFROM'										// Handled by fldTimezone
	//  'VCALENDAR/VTIMEZONE/STANDARD/COMMENT'											// Ignored
	//  'VCALENDAR/VTIMEZONE/STANDARD/RDATE'											// Ignored
	//  'VCALENDAR/VTIMEZONE/STANDARD/TZNAME'											// Ignored
	//	'VCALENDAR/VTIMEZONE/STANDARD/END'												// ignored
	//	'VCALENDAR/VTIMEZONE/DAYLIGTH/BEGIN'											// Handled by fldTimezone
	//  'VCALENDAR/VTIMEZONE/DAYLIGTH/DTSTART'											// Handled by fldTimezone
	//  'VCALENDAR/VTIMEZONE/DAYLIGTH/TZOFFSETTO'										// Handled by fldTimezone
    //  'VCALENDAR/VTIMEZONE/DAYLIGTH/TZOFFSETFROM'										// Handled by fldTimezone
	//  'VCALENDAR/VTIMEZONE/DAYLIGTH/COMMENT'											// Ignored
	//  'VCALENDAR/VTIMEZONE/DAYLIGTH/RDATE'											// Ignored
	//  'VCALENDAR/VTIMEZONE/DAYLIGTH/TZNAME'											// Ignored
	//	'VCALENDAR/VTIMEZONE/DAYLIGTH/END'												// ignored
	// 	'VCALENDAR/VTIMEZONE/END'                  										// Handled by fldTimezone
		'VCALENDAR/END'                  											=> 'fldEnd',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	];

    /**
     * 	Singleton instance of object
     * 	@var mimvCal
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimvCal {

		if (!self::$_obj) {

            self::$_obj = new self();

			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::CALENDAR;

			foreach (self::MAP as $tag => $class) {

			    $class = 'syncgw\\document\\field\\'.$class;
			    self::$_obj->_map[$tag] = $class::getInstance();
			}
		}

		return self::$_obj;
	}

    /**
	 * 	Get information about class
	 *
     *	@param 	- true = Check status; false = Provide supported features
	 * 	@param 	- Object to store information
	 */
	public function Info(bool $mod, XML $xml): void {

		if (!$mod) {

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7986" target="_blank">RFC7986</a> '.
					      'New Properties for iCalendar');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc5545" target="_blank">RFC5545</a> '.
					      'Internet Calendaring and Scheduling Core Object Specification handler');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', 'vCal 1.0  '.
					      'The Personal Electronic Calendaring and Scheduling Exchange Format');
			$xml->addVar('Stat', 'Implemented');;

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc6868" target="_blank">RFC6868</a> '.
					      'Parameter Value Encoding in iCalendar and vCard');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7405" target="_blank">RFC7405</a> '.
						  'Case-Sensitive String Support in ABNF');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7405" target="_blank">RFC7405</a> '.
					      'Case-Sensitive String Support in ABNF');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc5234" target="_blank">RFC5234</a> '.
						  'Augmented BNF for Syntax Specifications handler');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc2425" target="_blank">RFC2425</a> '.
					      'A MIME Content-Type for Directory Information');
			$xml->addVar('Stat', 'Implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7529" target="_blank">RFC7529</a> '.
						  'Non-Gregorian Recurrence Rules in the iCalendar');
			$xml->addVar('Stat', 'Not implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7953" target="_blank">RFC7953</a> '.
						  'Calendar Availability');
			$xml->addVar('Stat', 'Not implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc5546" target="_blank">RFC5546</a> '.
						  'iCalendar Transport-Independent Interoperability Protocol (iTIP)');
			$xml->addVar('Stat', 'Not implemented');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc2445" target="_blank">RFC2445</a> '.
					      'Internet Calendaring and Scheduling Core Object Specification');
			$xml->addVar('Stat', 'Implemented');
		}

		parent::Info($mod, $xml);
	}

}
