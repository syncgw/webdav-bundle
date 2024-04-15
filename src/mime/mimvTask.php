<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for task data class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\webdav\mime;

use syncgw\document\mime\mimHandler;
use syncgw\lib\DataStore;
use syncgw\lib\XML;

class mimvTask extends mimHandler {

	const MIME = [

		[ 'text/calendar',		2.0 ],
		[ 'text/x-vcalendar',	2.0 ],
		[ 'text/x-vcalendar',	1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	  	'VCALENDAR/BEGIN'															=> 'fldBegin',
	//	'VCALENDAR/VERSION'																// Ignored
	//	'VCALENDAR/PRODID'																// Ignored
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
		'VCALENDAR/VTODO/BEGIN'														=> 'fldBegin',
	//  'VCALENDAR/VTODO/DTSTAMP'														// Ignored
	    'VCALENDAR/VEVENT/UID,VCALENDAR/VEVENT/X-IRMC-LUID,'						=> 'fldUid',
	    'VCALENDAR/VTODO/CLASS'														=> 'fldClass',
    	'VCALENDAR/VTODO/COMPLETED'													=> 'fldCompleted',
        'VCALENDAR/VTODO/CREATED,'													=> 'fldCreated',
	    'VCALENDAR/VTODO/DESCRIPTION'												=> 'fldBody',
		'VCALENDAR/VTODO/DTSTART'													=> 'fldStartTime',
	    'VCALENDAR/VTODO/GEO'														=> 'fldGeoPosition',
	    'VCALENDAR/VTODO/LAST-MODIFIED'												=> 'fldLastMod',
	    'VCALENDAR/VTODO/LOCATION'													=> 'fldLocation',
		'VCALENDAR/VTODO/ORGANIZER'													=> 'fldOrganizer',
    	'VCALENDAR/VTODO/PERCENT-COMPLETE'											=> 'fldPercentComplete',
	    'VCALENDAR/VTODO/PRIORITY'													=> 'fldPriority',
	    'VCALENDAR/VTODO/RECURRENCE-ID'												=> 'fldRecurrenceId',
	    'VCALENDAR/VTODO/SEQUENCE'													=> 'fldSequence',
    	'VCALENDAR/VTODO/STATUS'													=> 'fldStatus',
		'VCALENDAR/VTODO/SUMMARY'													=> 'fldSummary',
		'VCALENDAR/VTODO/URL'														=> 'fldURLs',
		'VCALENDAR/VTODO/RRULE'														=> 'fldRecurrence',
	    'VCALENDAR/VTODO/DUE'														=> 'fldDueDate',
        'VCALENDAR/VTODO/DURATION'													=> 'fldDuration',
	    'VCALENDAR/VTODO/ATTACH'													=> 'fldAttach',
	    'VCALENDAR/VTODO/ATTENDEE'													=> 'fldAttendee',
  	    'VCALENDAR/VTODO/CATEGORIES,VCALENDAR/VTODO/X-EPOCAGENDAENTRYTYPE,'			=> 'fldCategories',
	    'VCALENDAR/VTODO/COMMENT'													=> 'fldComment',
	    'VCALENDAR/VTODO/CONTACT'													=> 'fldContact',
	    'VCALENDAR/VTODO/EXDATE'													=> 'fldExceptions',
	    'VCALENDAR/VTODO/REQUEST-STATUS'											=> 'fldRequestStatus',
	    'VCALENDAR/VTODO/RELATED-TO'												=> 'fldRelated',
    	'VCALENDAR/VTODO/RESOURCES'													=> 'fldResource',
	    'VCALENDAR/VTODO/RDATE'														=> 'fldRecurrenceDate',

	    'VCALENDAR/VTODO/NAME'														=> 'fldName',
	    'VCALENDAR/VTODO/REFRESH-INTERVAL'											=> 'fldRefreshInterval',
	    'VCALENDAR/VTODO/SOURCE'													=> 'fldSource',
        'VCALENDAR/VTODO/COLOR'														=> 'fldColor',
	    'VCALENDAR/VTODO/IMAGE'														=> 'fldPhoto',
        'VCALENDAR/VTODO/CONFERENCE'												=> 'fldConference',
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// 	'VCALENDAR/VTODO/VALARM/BEGIN'                                              	// Handled by fldAlarm
    //	'VCALENDAR/VTODO/DALARM'														// Handled by fldAlarm
    //	'VCALENDAR/VTODO/MALARM'														// Handled by fldAlarm
    //	'VCALENDAR/VTODO/AALARM'														// Handled by fldAlarm
    //	'VCALENDAR/VTODO/PALARM'														// Ignored
    	'VCALENDAR/VTODO/VALARM/ACTION'                                             => 'fldAlarm',
	//	'VCALENDAR/VTODO/VALARM/SUMMARY'                                            	// Handled by fldAlarm
	//	'VCALENDAR/VTODO/VALARM/DESCRIPTION'                                        	// Handled by fldAlarm
    //	'VCALENDAR/VTODO/VALARM/TRIGGER'                                            	// Handled by fldAlarm
	//	'VCALENDAR/VTODO/VALARM/DURATION'												// Handled by fldAlarm
	//	'VCALENDAR/VTODO/VALARM/REPEAT'                                             	// Handled by fldAlarm
	//	'VCALENDAR/VTODO/VALARM/ATTACH'                                             	// Handled by fldAlarm
	//	'VCALENDAR/VTODO/VALARM/ATTENDEE'                                           	// Handled by fldAlarm
	//	'VCALENDAR/VTODO/VALARM/END'                  									// Handled by fldAlarm
	    'VCALENDAR/VTODO/END'														=> 'fldEnd',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    //	'VCALENDAR/VTIMEZONE/BEGIN'                                              		// Handled by fldTimezone
      	'VCALENDAR/VTIMEZONE/TZID'													=> 'fldTimezone',
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
     * 	@var mimvTask
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimvTask {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::TASK;

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
			$xml->addVar('Stat', 'Implemented');

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
