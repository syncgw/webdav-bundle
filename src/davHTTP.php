<?php
declare(strict_types=1);

/*
 * 	Process HTTP input / output
 *
 *	@package	sync*gw
 *	@subpackage	SabreDAV support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\webdav;

use syncgw\lib\Config;
use syncgw\lib\HTTP;

class davHTTP extends HTTP {

    /**
     * 	Singleton instance of object
     * 	@var davHTTP
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): davHTTP {

		if (!self::$_obj) {
            self::$_obj = new self();
            parent::getInstance();
		}

		return self::$_obj;
	}

 	/**
	 * 	Check HTTP input
	 *
	 * 	@return - HTTP status code
	 */
	public function checkIn(): int {

        $cnf = Config::getInstance();

        if ($cnf->getVar(Config::HANDLER))
        	return 200;

		$cnf->updVar(Config::HANDLER, 'DAV');

        // debugging turned on?
        if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE) {

	        $tags = [ 'REQUEST_URI' => HTTP::SERVER, 'PATH_INFO' => HTTP::SERVER,
	        		  'PATH_TRANSLATED' => HTTP::SERVER, 'PHP_SELF' => HTTP::SERVER,
	        		  'Request' => HTTP::RCV_HEAD, 'User' => HTTP::RCV_HEAD ];

	        $nuid = $cnf->getVar(Config::DBG_USR);

	        // check old user id
	        if (isset(self::$_http[HTTP::SERVER]['PHP_AUTH_USER'])) {

	        	$ouid = self::$_http[HTTP::SERVER]['PHP_AUTH_USER'];

	            // replace userid with debug user id
	            foreach ([ '/', '\\' ] as $sep) {

		            foreach ([ 'principals', 'calendars', 'addressbooks'] as $key) {

				        foreach ($tags as $tag => $typ) {

				        	if (isset(self::$_http[$typ][$tag]))
			        			self::$_http[$typ][$tag] = str_replace($key.$sep.$ouid, $key.$sep.$nuid,
			        											self::$_http[$typ][$tag]);
					    }
	        	    }
	            }

	            // change body
	           	self::$_http[HTTP::RCV_BODY] = str_replace([
	           				'/principals/'.$ouid.'/',
	           				'/calendars/'.$ouid.'/',
	           				'/addressbooks/'.$ouid.'/',
	           				'displayname>'.$ouid.'<',
	           	], [
	           				'/principals/'.$nuid.'/',
	           				'/calendars/'.$nuid.'/',
	           				'/addressbooks/'.$nuid.'/',
	           				'displayname>'.$nuid.'<',
	           	],  self::$_http[HTTP::RCV_BODY]);
	        }

$x = stripos(self::$_http[HTTP::RCV_BODY], 'xml-ns');
           	// special hack for e.g. PROPFIND /calendars/t1@mail.fd/Florian/
	        if (self::$_http[HTTP::SERVER]['REQUEST_METHOD'] == 'PROPFIND' &&
	        	strpos(self::$_http[HTTP::RCV_HEAD]['Request'], '@')) {

	        	$w = explode('/', self::$_http[HTTP::RCV_HEAD]['Request']);
	        	$ouid = $w[2];
	        	$w[2] = $nuid;
		        self::$_http[HTTP::RCV_HEAD]['Request'] = implode('/', $w);
		        foreach ([ 'REQUEST_URI', 'PATH_INFO', 'PATH_TRANSLATED', 'PHP_SELF'] as $tag)
		        	if (isset(self::$_http[HTTP::SERVER][$tag]))
			        	self::$_http[HTTP::SERVER][$tag] = str_replace($ouid, $nuid, self::$_http[HTTP::SERVER][$tag]);
	        }

        }

		// do not change "xmlns" to "xml-ns" as Sabre expect this attibute!

		return 200;
	}

	/**
	 * 	Check HTTP output
	 *
	 * 	@return - HTTP status code
	 */
	public function checkOut(): int {

		$cnf = Config::getInstance();

		if ($cnf->getVar(Config::HANDLER) != 'DAV')
			return 200;

		// delete optional character set attributes
		if (!self::$_http[HTTP::SND_BODY])
			self::$_http[HTTP::SND_BODY] = '';

		self::$_http[HTTP::SND_BODY] = preg_replace('/(\sCHARSET)(=[\'"].*[\'"])/iU', '', self::$_http[HTTP::SND_BODY]);

        // debugging turned on?
        if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE) {

        	// check old user id
	        if (isset(self::$_http[HTTP::SERVER]['PHP_AUTH_USER'])) {

	        	$ouid = self::$_http[HTTP::SERVER]['PHP_AUTH_USER'];
		        $nuid = $cnf->getVar(Config::DBG_USR);

	        	// change body
		        self::$_http[HTTP::SND_BODY] = str_replace([
		        			'/principals/'.$ouid.'/',
		           			'/calendars/'.$ouid.'/',
		           			'/addressbooks/'.$ouid.'/',
		           			'displayname>'.$ouid.'<',
		        ], [
		        			'/principals/'.$nuid.'/',
		        			'/calendars/'.$nuid.'/',
		        			'/addressbooks/'.$nuid.'/',
		        			'displayname>'.$nuid.'<',
		        ],  self::$_http[HTTP::SND_BODY]);

	        }

        }

        self::$_http[self::SND_HEAD]['Content-Length'] = strlen(self::$_http[self::SND_BODY]);

		return 200;
	}

}
