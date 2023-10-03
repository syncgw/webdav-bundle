<?php
declare(strict_types=1);

/*
 * 	WebDAV user administration handler class
 *
 *	@package	sync*gw
 *	@subpackage	SabreDAV support
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 *  @link       Sabre/DAV/Auth/Backend/AbstactBasic.php
 */

namespace syncgw\webdav;

use syncgw\lib\Config;
use syncgw\lib\DataStore;
use syncgw\lib\Msg;
use syncgw\lib\Device;
use syncgw\lib\HTTP;
use syncgw\lib\Session;
use syncgw\lib\User;
use syncgw\lib\Util;

class davUser extends \Sabre\DAV\Auth\Backend\AbstractBasic {

    /**
     * 	Singleton instance of object
     * 	@var davUser
     */
    static private $_obj = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): davUser {

	  	if (!self::$_obj) {

           	self::$_obj = new self();
			self::$_obj->setRealm('davUser');
		}

		return self::$_obj;
	}

    /**
     * Validates a username and password.
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param 	- User name
     * @param 	- Password
     * @return 	- true = Ok; false = Error
     */
	protected function validateUserPass($username, $password) {

		Msg::InfoMsg('Validate "'.$username.'" with "'.$password.'"');

		$usr  = User::getInstance();
		$http = HTTP::getInstance();
		$cnf  = Config::getInstance();

		$dev = $http->getHTTPVar('REMOTE_ADDR');
		// processing trace records?
		if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE &&
			// no forced trace running=
			!($cnf->getVar(Config::TRACE) & Config::TRACE_FORCE) &&
			// not akready prefixed?
			strncmp($dev, Config::DBG_PREF, Config::DBG_PLEN))
			$dev = Config::DBG_PREF.$dev;

		if (!$usr->Login($http->getHTTPVar('User'), $http->getHTTPVar('Password'), $dev))
			return false;

		// we will only synchronize data stores once per session
		$sess = Session::getInstance();
		if (!$sess->getVar('DAVSync')) {

			$cnf  = Config::getInstance();

			// disable double calls
			$sess->updVar('DAVSync', '1');

			$ena = $cnf->getVar(Config::ENABLED);
			$dev = Device::getInstance();

			foreach ([ DataStore::CONTACT, DataStore::CALENDAR, DataStore::TASK] as $hid) {

				if (!($ena & $hid))
					continue;

				// update synchronization key
				$usr->syncKey('DAV-'.$hid, 1);
				Msg::InfoMsg('Synchronizing data store ['.Util::HID(Util::HID_ENAME, $hid).
						   ']. Incrementing synchronization key to ['.$usr->syncKey('DAV-'.$hid).']');

				// sync all groups (-> no late loading!)
				$ds = Util::HID(Util::HID_CNAME, $hid);
		        $ds = $ds::getInstance();
				$ds->syncDS('', true);
			}
		}

		return true;
	}

}
