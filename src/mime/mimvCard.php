<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for contact data class
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

class mimvCard extends mimHandler {

	const MIME = [

		[ 'text/vcard',		4.0 ],
		[ 'text/vcard',		3.0 ],
		[ 'text/x-vcard',	3.0 ],
		[ 'text/x-vcard',	2.1 ],
	];
   	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    	'VCARD/BEGIN'																=> 'fldBegin',
    //  'VCARD/VERSION'																// Handled by fldBegin
	//	'VCARD/PRODID'																// Handled by fldBegin
        'VCARD/SOURCE'																=> 'fldSource',
 	    'VCARD/KIND'																=> 'fldKind',
        'VCARD/XML'																	=> 'fldXML',
 	    'VCARD/FN'																	=> 'fldFullName',
        'VCARD/N'																	=> 'fldName',
        'VCARD/NICKNAME,VCARD/X-EPOCSECONDNAME,VCARD/X-NICKNAME,'					=> 'fldNickName',
 	    'VCARD/PHOTO'																=> 'fldPhoto',
 	    'VCARD/BDAY'																=> 'fldBirthday',
 	    'VCARD/ANNIVERSARY'															=> 'fldAnniversary',
 	    'VCARD/GENDER'																=> 'fldGender',
 	    'VCARD/ADR'																	=> 'fldAddresses',
	    'VCARD/TEL'																	=> 'fldPhones',
        'VCARD/EMAIL'																=> 'fldMails',
	    'VCARD/IMPP'																=> 'fldIMAddresses',
	    'VCARD/LANG'																=> 'fldLanguage',
	    'VCARD/TZ'																	=> 'fldTimezone',
	    'VCARD/GEO'																	=> 'fldGeoPosition',
	    'VCARD/TITLE'																=> 'fldTitle',
	    'VCARD/ROLE'																=> 'fldRole',
	    'VCARD/LOGO'																=> 'fldLogo',
	    'VCARD/ORG'																	=> 'fldOrganization',
	    'VCARD/MEMBER'																=> 'fldMember',
	    'VCARD/RELATED'																=> 'fldRelated',
  	    'VCARD/CATEGORIES,VCARD/X-CATEGORIES,'										=> 'fldCategories',
      	'VCARD/NOTE'																=> 'fldBody',
        'VCARD/REV'																	=> 'fldCreated',
	    'VCARD/SOUND'																=> 'fldSound',
	    'VCARD/UID'																	=> 'fldUid',
	    'VCARD/CLIENTPIDMAP'														=> 'fldClientPidMap',
	    'VCARD/URL'																	=> 'fldURLs',
	    'VCARD/KEY'																	=> 'fldKey',
	    'VCARD/FBURL'																=> 'fldFreeBusy',
	    'VCARD/CALADRURI'															=> 'fldCalAdrURI',
	    'VCARD/CALURI'																=> 'fldCalURI',
    //  'VCARD/MAILER'																	// RFC2426 Ignored
    //  'VCARD/AGENT'																	// RFC2426 Ignored
    //  'VCARD/SORT-STRING'																// RFC2426 Ignored
	    'VCARD/LABEL'                         										=> 'fldLabels',				// RFC2426
        'VCARD/CLASS'                         										=> 'fldClass',				// RFC2426
       	'VCARD/END'																	=> 'fldEnd',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    ];

    /**
     * 	Singleton instance of object
     * 	@var mimvCard
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimvCard {

		if (!self::$_obj) {

            self::$_obj = new self();
 			self::$_obj->_mime = self::MIME;
 			self::$_obj->_hid  = DataStore::CONTACT;

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

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc6350" target="_blank">RFC6350</a> '.
						  'vCard MIME Directory Profile handler');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc6868" target="_blank">RFC6868</a> '.
					      'Parameter Value Encoding in iCalendar and vCard');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7405" target="_blank">RFC7405</a> '.
						  'Case-Sensitive String Support in ABNF');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc7405" target="_blank">RFC7405</a> '.
					      'Case-Sensitive String Support in ABNF');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc5234" target="_blank">RFC5234</a> '.
						  'Augmented BNF for Syntax Specifications handler');

			$xml->addVar('Name', '<a href="https://tools.ietf.org/html/rfc2425" target="_blank">RFC2425</a> '.
					      'A MIME Content-Type for Directory Information');
		}

		parent::Info($mod, $xml);
	}

}
