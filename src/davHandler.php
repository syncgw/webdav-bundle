<?php
declare(strict_types=1);

/*
 * 	WebDAV handler class
 *
 *	@package	sync*gw
 *	@subpackage	SabreDAV support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * For patches search "syncGW" in directory "syncgw/Sabre"
 */

namespace syncgw\webdav;

use syncgw\lib\Config;
use syncgw\lib\DataStore;
use syncgw\lib\ErrorHandler;
use syncgw\lib\HTTP;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\Session;
use syncgw\lib\XML;

class davHandler {

    /**
     * 	Singleton instance of object
     * 	@var davHandler
     */
    static private $_obj = null;

	/**
     * 	MIME version to use
     *  @var array
     */
    static public $mime = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): davHandler {

		if (!self::$_obj) {

            self::$_obj = new self();

			// set log message cods 19001-19010
			Log::getInstance()->setLogMsg([
		            19001 => 'Calendar and task list synchronization in parallel is not supported by DAV protocoll - '.
							 'task synchronization disabled',
					19002 => 'Cannot load %s for user (%s) - please check synchronization status',
			]);
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'WebDAV handler');

		$xml->addVar('Opt', 'Maximum object size');
		$cnf = Config::getInstance();
		$xml->addVar('Stat', sprintf('%d bytes', $cnf->getVar(Config::MAXOBJSIZE)) );

		$xml->addVar('Opt', '<a href="https://github.com/sabre-io/dav" target="_blank">SabreDAV</a> framework for PHP');
		$xml->addVar('Stat', 'v'.\Sabre\DAV\Version::VERSION);

		$xml->addVar('Opt', '<a href="http://tools.ietf.org/html/rfc2617" target="_blank">RFC2617</a> '.
					  'Basic access authentication handler');
		$xml->addVar('Stat', 'Implemented');
	}

	/**
	 * 	Process client request
	 */
	public function Process(): void {

		$http = HTTP::getInstance();
		$cnf = Config::getInstance();

		// check authorization
		if (!$http->getHTTPVar('PHP_AUTH_USER')) {

			$http->addHeader('WWW-Authenticate', 'Basic realm=davHandler');
			$http->send(401);
			return;
		}

		// set hacks we must support
		$a = $http->getHTTPVar('User-Agent');
		$h = 0;
		// CardDAV client
		if (stripos($a, 'CardDAV-Sync (Android)') !== false)
			$h |= Config::HACK_CDAV;
		$cnf->updVar(Config::HACK, $h);

		// create / restart session
		if (!Session::getInstance()->mkSession())
			return;

		// we don't wanna get SabreDAV warnings
		ErrorHandler::filter(E_NOTICE|E_DEPRECATED, 'Sabre');

		// ----------------------------------------------------------------------------------------------------------------------------------
		// Extracted from "SabreDav\examples\groupwareserver.php"

		$authBackend      = davUser::getInstance();
		$principalBackend = davPrincipal::getInstance();
		$tree             = [];
		$ena 			  = $cnf->getVar(Config::ENABLED);

		if ($ena & DataStore::CONTACT) {

			$tree[] = new \Sabre\DAVACL\PrincipalCollection($principalBackend);
    		$tree[] = new \Sabre\CardDAV\AddressBookRoot($principalBackend, davContact::getInstance());
    		// default mime encoding
    		self::$mime = [ 'text/vcard', 4.0 ];
			// <CARD:address-data content-type="text/vcard" version="4.0"/>
			$body = $http->getHTTPVar(HTTP::RCV_BODY);
    		if (($pos = strpos($body, 'content-type')) !== false) {

    			$a = explode('"', substr($body, $pos, 50));
    			self::$mime = [ $a[1], $a[3] ];
    		}
    		// VERSION:4.0
    		elseif (($pos = strpos($body, 'VERSION:')) !== false) {

    			$v = substr($body, $pos + 8, 3);
    			self::$mime = [ $v == '2.1' ? 'text/x-vcard' : 'text/vcard', $v ];
    		}
		} else
			self::$mime = [ 'text/calendar', 2.0 ];

		// is task data store enabled?
		if ($ena & DataStore::TASK) {

            // sub domain enables to task list synchronization?
            // task list synchronization forced?
		    if (($t = $cnf->getVar(Config::FORCE_TASKDAV)) && ($t == 'FORCE' ||
		    	stripos($http->getHTTPVar('SERVER_NAME'), $t))) {

    		    // disable calendar synchronization
                $ena &= ~DataStore::CALENDAR;
                Msg::InfoMsg('Force task synchronization only');
		    }

		    if ($ena & DataStore::CALENDAR && $ena & DataStore::TASK) {

   		        Log::getInstance()->logMsg(Log::WARN, 19001);
       			$ena &= ~DataStore::TASK;
		    } else {

        		$tree[] = new \Sabre\CalDAV\Principal\Collection($principalBackend);
         		$tree[] = new \Sabre\CalDAV\CalendarRoot($principalBackend, davTask::getInstance());
		    }

		    // store update enabled handler ID for this session
            $cnf->updVar(Config::ENABLED, $ena);
		}

		// is calendar data store enabled?
		if ($ena & DataStore::CALENDAR) {

    		$tree[] = new \Sabre\CalDAV\Principal\Collection($principalBackend);
    		$tree[] = new \Sabre\CalDAV\CalendarRoot($principalBackend, davCalendar::getInstance());
		}

		// include helper functions
		require_once($cnf->getVar(Config::ROOT).'/../sabre/http/lib/functions.php');
		require_once($cnf->getVar(Config::ROOT).'/../sabre/uri/lib/functions.php');

		// allocate server
		$wd = new \Sabre\DAV\Server($tree);
		// catch full exception errors?
		if ($cnf->getVar(Config::TRACE_EXC) == 'Y')
			$wd->debugExceptions = true;

		// patch Sapi class => Sabre\HTTP\Sapi.php
		$wd->sapi = $this;
		$wd->httpRequest = self::getRequest();
		$wd->setBaseUri($http->getHTTPVar('SCRIPT_NAME'));

		// authentication plugin
        $wd->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend));

        if ($ena & DataStore::CONTACT)
            $wd->addPlugin(new \Sabre\CardDAV\Plugin());
        if ($ena & (DataStore::CALENDAR|DataStore::TASK))
            $wd->addPlugin(new \Sabre\CalDAV\Plugin());

        // permission plugin
        $wd->addPlugin(new \Sabre\DAVACL\Plugin());
        // WebDAV sync plugin
        $wd->addPlugin(new \Sabre\DAV\Sync\Plugin());

        // clear any potential XML error
        libxml_clear_errors();

        // start processing
        $wd->start();

		// ----------------------------------------------------------------------------------------------------------------------------------

        ErrorHandler::resetReporting();
	}

    /**
     * This static method will create a new Request object, based on the
     * current PHP request.
     *
     * @return - Request
     */
    static function getRequest() {

        $http = HTTP::getInstance();
        $req = new \Sabre\HTTP\Request($http->getHTTPVar('REQUEST_METHOD'), $uri = $http->getHTTPVar('REQUEST_URI'),
                                       $http->getHTTPVar(HTTP::RCV_HEAD), $http->getHTTPVar(HTTP::RCV_BODY));
        $req->setHttpVersion($http->getHTTPVar('SERVER_PROTOCOL') == 'HTTP/1.0' ? '1.0' : '1.1');
        $req->setRawServerData($http->getHTTPVar(HTTP::SERVER));
        $p = $http->getHTTPVar('HTTPS');
        $h = $http->getHTTPVar('HTTP_HOST');
        $req->setAbsoluteUrl((!empty($p) && $p !== 'off' ? 'https' : 'http').'://'.($h ? $h : 'localhost').$uri);
        $req->setPostData([]);

        return $req;
    }

    /**
     * Sends the HTTP response back to a HTTP client.
     *
     * This calls php's header() function and streams the body to php://output.
     *
     * @param  - ResponseInterface
     */
    static function sendResponse(\Sabre\HTTP\ResponseInterface $response) {

        $http = HTTP::getInstance();

        $rc = $response->getStatus();
        foreach ($response->getHeaders() as $key => $value) {
            foreach ($value as $unused => $v)
                $http->addHeader($key, $v);
        }
		$unused; // disable Eclipse warning

        $bdy = $response->getBody();
        if (is_resource($bdy)) {
            $http->addBody(stream_get_contents($bdy));
            fclose($bdy);
         } else
            $http->addBody($bdy);

        // flush data
        $http->send($rc);
     }

}
