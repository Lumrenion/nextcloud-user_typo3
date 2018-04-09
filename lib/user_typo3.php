<?php

/**
 * nextCloud - user_typo3
 *
 * @author Andreas Böhler and contributors
 * @copyright 2012-2015 Andreas Böhler <dev (at) aboehler (dot) at>
 *
 * credits go to Ed W for several SQL injection fixes and caching support
 * credits go to Frédéric France for providing Joomla support
 * credits go to Mark Jansenn for providing Joomla 2.5.18+ / 3.2.1+ support
 * credits go to Dominik Grothaus for providing SSHA256 support and fixing a few bugs
 * credits go to Sören Eberhardt-Biermann for providing multi-host support
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\user_typo3;
use OC\User\Backend;

use \OCA\user_typo3\lib\Helper;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;
use OCP\Util;
use TYPO3\CMS\Saltedpasswords\Salt\AbstractSalt;
use TYPO3\CMS\Saltedpasswords\Salt\BlowfishSalt;
use TYPO3\CMS\Saltedpasswords\Salt\Md5Salt;
use TYPO3\CMS\Saltedpasswords\Salt\Pbkdf2Salt;
use TYPO3\CMS\Saltedpasswords\Salt\PhpassSalt;
use TYPO3\CMS\Saltedpasswords\Salt\SaltFactory;
use TYPO3\CMS\Saltedpasswords\Salt\SaltInterface;

abstract class BackendUtility {
    protected $access;

    /**
     * constructor, make sure the subclasses call this one!
     * @param Access $access an instance of Access for LDAP interaction
     */
    public function __construct(Access $access) {
        $this->access = $access;
    }
}


class OC_USER_TYPO3 extends BackendUtility implements \OCP\IUserBackend,
                                                        \OCP\UserInterface
{
    protected $cache;
    protected $settings;
    protected $helper;
    protected $session_cache_name;
    protected $ocConfig;

    /**
     * The default constructor. It loads the settings for the given domain
     * and tries to connect to the database.
     */
    public function __construct()
    {
        $memcache = \OC::$server->getMemCacheFactory();
        if ($memcache->isAvailable()) {
            $this->cache = $memcache->create();
        }
        $this->helper = new \OCA\user_typo3\lib\Helper();
        $domain = \OC::$server->getRequest()->getServerHost();
        $this->settings = $this->helper->loadSettingsForDomain($domain);
        $this->ocConfig = \OC::$server->getConfig();
        $this->helper->connectToDb($this->settings);
        $this->session_cache_name = 'USER_TYPO3_CACHE';
        return false;
    }

    /**
     * Sync the user's E-Mail address with the address stored by ownCloud.
     * We have three (four) sync modes:
     *   - none:     Does nothing
     *   - initial:  Do the sync only once from SQL -> ownCloud
     *   - forcesql: The SQL database always wins and sync to ownCloud
     *   - forceoc:  ownCloud always wins and syncs to SQL
     *
     * @param string $uid The user's ID to sync
     * @return bool Success or Fail
     */
    private function doEmailSync($uid)
    {
        Util::writeLog('OC_USER_TYPO3', "Entering doEmailSync for UID: $uid",
            Util::DEBUG);
        if ($this->settings['col_email'] === '') {
            return false;
        }

        if ($this->settings['set_mail_sync_mode'] === 'none') {
            return false;
        }

        $ocUid = $uid;
        $uid = $this->doUserDomainMapping($uid);

        $row = $this->helper->runQuery('getMail', array('uid' => $uid));
        if ($row === false) {
            return false;
        }
        $newMail = $row['email'];

        $currMail = $this->ocConfig->getUserValue($ocUid,
            'settings',
            'email', '');

        switch ($this->settings['set_mail_sync_mode']) {
            case 'initial':
                if ($currMail === '') {
                    $this->ocConfig->setUserValue($ocUid,
                        'settings',
                        'email',
                        $newMail);
                }
                break;
            case 'forcesql':
                //if($currMail !== $newMail)
                $this->ocConfig->setUserValue($ocUid,
                    'settings',
                    'email',
                    $newMail);
                break;
            case 'forceoc':
                if (($currMail !== '') && ($currMail !== $newMail)) {
                    $row = $this->helper->runQuery('setMail',
                        array(
                            'uid' => $uid,
                            'currMail' => $currMail
                        )
                        , true);

                    if ($row === false) {
                        Util::writeLog('OC_USER_TYPO3',
                            "Could not update E-Mail address in SQL database!",
                            Util::ERROR);
                    }
                }
                break;
        }

        return true;
    }

    /**
     * This maps the username to the specified domain name.
     * It can only append a default domain name.
     *
     * @param string $uid The UID to work with
     * @return string The mapped UID
     */
    private function doUserDomainMapping($uid)
    {
        $uid = trim($uid);

        if ($this->settings['set_default_domain'] !== '') {
            Util::writeLog('OC_USER_TYPO3', "Append default domain: " .
                $this->settings['set_default_domain'], Util::DEBUG);
            if (strpos($uid, '@') === false) {
                $uid .= "@" . $this->settings['set_default_domain'];
            }
        }

        $uid = strtolower($uid);
        Util::writeLog('OC_USER_TYPO3', 'Returning mapped UID: ' . $uid,
            Util::DEBUG);
        return $uid;
    }

    /**
     * Return the actions implemented by this backend
     * @param $actions
     * @return bool
     */
    public function implementsActions($actions)
    {
        return (bool)((Backend::CHECK_PASSWORD
                | Backend::GET_DISPLAYNAME
                | Backend::COUNT_USERS
                | ($this->settings['set_allow_pwchange'] === 'true' ?
                    Backend::SET_PASSWORD : 0)
            ) & $actions);
    }

    /**
     * Checks if this backend has user listing support
     * @return bool
     */
    public function hasUserListings()
    {
        return true;
    }

    /**
     * Return the user's home directory, if enabled
     * @param string $uid The user's ID to retrieve
     * @return mixed The user's home directory or false
     */
    public function getHome($uid)
    {
        return false;
    }

    /**
     * Create a new user account using this backend
     * @return bool always false, as we can't create users
     */
    public function createUser()
    {
        // Can't create user
        Util::writeLog('OC_USER_TYPO3',
            'Not possible to create local users from web' .
            ' frontend using SQL user backend', Util::ERROR);
        return false;
    }

    /**
     * Delete a user account using this backend
     * @param string $uid The user's ID to delete
     * @return bool always false, as we can't delete users
     */
    public function deleteUser($uid)
    {
        // Can't delete user
        Util::writeLog('OC_USER_TYPO3', 'Not possible to delete local users' .
            ' from web frontend using SQL user backend', Util::ERROR);
        return false;
    }

    /**
     * Set (change) a user password
     * This can be enabled/disabled in the settings (set_allow_pwchange)
     *
     * @param string $uid The user ID
     * @param string $password The user's new password
     * @return bool The return status
     */
    public function setPassword($uid, $password)
    {
        // Update the user's password - this might affect other services, that
        // use the same database, as well
        Util::writeLog('OC_USER_TYPO3', "Entering setPassword for UID: $uid",
            Util::DEBUG);

        if ($this->settings['set_allow_pwchange'] !== 'true') {
            return false;
        }

        $uid = $this->doUserDomainMapping($uid);

        $row = $this->helper->runQuery('getPass', array('uid' => $uid));
        if ($row === false) {
            return false;
        }

        $saltingInstance = $this->getSaltingInstance();
        $enc_password = $saltingInstance->getHashedPassword($password);

        $res = $this->helper->runQuery('setPass',
            array('uid' => $uid, 'enc_password' => $enc_password),
            true);
        if ($res === false) {
            Util::writeLog('OC_USER_TYPO3', "Could not update password!",
                Util::ERROR);
            return false;
        }
        Util::writeLog('OC_USER_TYPO3',
            "Updated password successfully, return true",
            Util::DEBUG);
        return true;
    }

    /**
     * Check if the password is correct
     * @param string $uid The username
     * @param string $password The password
     * @return bool true/false
     *
     * Check if the password is correct without logging in the user
     */
    public function checkPassword($uid, $password)
    {
        Util::writeLog('OC_USER_TYPO3',
            "Entering checkPassword() for UID: $uid",
            Util::DEBUG);

        $uid = $this->doUserDomainMapping($uid);

        $row = $this->helper->runQuery('getPass', array('uid' => $uid));
        if ($row === false) {
            Util::writeLog('OC_USER_TYPO3', "Got no row, return false", Util::DEBUG);
            return false;
        }
        $db_pass = $row['password'];

        Util::writeLog('OC_USER_TYPO3', "Encrypting and checking password",
            Util::DEBUG);

        $saltingInstance = $this->getSaltingInstance($db_pass);
        if ($saltingInstance === false) {
            return false;
        }

        $ret = $saltingInstance->checkPassword($password, $db_pass);

        if ($ret) {
            Util::writeLog('OC_USER_TYPO3',
                "Passwords matching, return true",
                Util::DEBUG);
            if ($this->settings['set_strip_domain'] === 'true') {
                $uid = explode("@", $uid);
                $uid = $uid[0];
            }
            return $uid;
        } else {
            Util::writeLog('OC_USER_TYPO3',
                "Passwords do not match, return false",
                Util::DEBUG);
            return false;
        }
    }

    /**
     * Count the number of users
     * @return int The user count
     */
    public function countUsers()
    {
        Util::writeLog('OC_USER_TYPO3', "Entering countUsers()",
            Util::DEBUG);

        $search = "%" . $this->doUserDomainMapping("");
        $userCount = $this->helper->runQuery('countUsers',
            array('search' => $search));
        if ($userCount === false) {
            $userCount = 0;
        } else {
            $userCount = reset($userCount);
        }

        Util::writeLog('OC_USER_TYPO3', "Return usercount: " . $userCount,
            Util::DEBUG);
        return $userCount;
    }

    /**
     * Get a list of all users
     * @param string $search The search term (can be empty)
     * @param int $limit The search limit (can be null)
     * @param int $offset The search offset (can be null)
     * @return array with all uids
     */
    public function getUsers($search = '', $limit = null, $offset = null)
    {
        Util::writeLog('OC_USER_TYPO3',
            "Entering getUsers() with Search: $search, " .
            "Limit: $limit, Offset: $offset", Util::DEBUG);
        $users = array();

        if ($search !== '') {
            $search = "%" . $this->doUserDomainMapping($search . "%") . "%";
        } else {
            $search = "%" . $this->doUserDomainMapping("") . "%";
        }

        $rows = $this->helper->runQuery('getUsers',
            array('search' => $search),
            false,
            true,
            array(
                'limit' => $limit,
                'offset' => $offset
            ));
        if ($rows === false) {
            return array();
        }

        foreach ($rows as $row) {
            $uid = $row['username'];
            if ($this->settings['set_strip_domain'] === 'true') {
                $uid = explode("@", $uid);
                $uid = $uid[0];
            }
            $users[] = strtolower($uid);
        }
        Util::writeLog('OC_USER_TYPO3', "Return list of results",
            Util::DEBUG);
        return $users;
    }

    /**
     * Check if a user exists
     * @param string $uid the username
     * @return boolean
     */
    public function userExists($uid)
    {

        $cacheKey = 'sql_user_exists_' . $uid;
        $cacheVal = $this->getCache($cacheKey);
        Util::writeLog('OC_USER_TYPO3',
            "userExists() for UID: $uid cacheVal: $cacheVal",
            Util::DEBUG);
        if (!is_null($cacheVal)) {
            return (bool)$cacheVal;
        }

        Util::writeLog('OC_USER_TYPO3',
            "Entering userExists() for UID: $uid",
            Util::DEBUG);

        // Only if the domain is removed for internal user handling,
        // we should add the domain back when checking existance
        if ($this->settings['set_strip_domain'] === 'true') {
            $uid = $this->doUserDomainMapping($uid);
        }

        $exists = (bool)$this->helper->runQuery('userExists',
            array('uid' => $uid));;
        $this->setCache($cacheKey, $exists, 60);

        if (!$exists) {
            Util::writeLog('OC_USER_TYPO3',
                "Empty row, user does not exists, return false",
                Util::DEBUG);
            return false;
        } else {
            Util::writeLog('OC_USER_TYPO3', "User exists, return true",
                Util::DEBUG);
            return true;
        }

    }

    /**
     * Get the display name of the user
     * @param string $uid The user ID
     * @return mixed The user's display name or FALSE
     */
    public function getDisplayName($uid)
    {
        Util::writeLog('OC_USER_TYPO3',
            "Entering getDisplayName() for UID: $uid",
            Util::DEBUG);

        $this->doEmailSync($uid);
        $uid = $this->doUserDomainMapping($uid);

        if (!$this->userExists($uid)) {
            return false;
        }

        $row = $this->helper->runQuery('getDisplayName',
            array('uid' => $uid));

        if (!$row) {
            Util::writeLog('OC_USER_TYPO3',
                "Empty row, user has no display name or " .
                "does not exist, return false",
                Util::DEBUG);
            return false;
        } else {
            Util::writeLog('OC_USER_TYPO3',
                "User exists, return true",
                Util::DEBUG);
            $displayName = $row['name'];
            return $displayName;
        }
    }

    public function getDisplayNames($search = '', $limit = null, $offset = null)
    {
        $uids = $this->getUsers($search, $limit, $offset);
        $displayNames = array();
        foreach ($uids as $uid) {
            $displayNames[$uid] = $this->getDisplayName($uid);
        }
        return $displayNames;
    }

    /**
     * Returns the backend name
     * @return string
     */
    public function getBackendName()
    {
        return 'SQL';
    }

    /**
     * @return bool|AbstractSalt|SaltInterface
     */
    protected function getSaltingInstance($saltedHash = '')
    {
        require_once(__DIR__ . '/../Salt/SaltInterface.php');
        require_once(__DIR__ . '/../Salt/AbstractSalt.php');
        if (empty($saltedHash)) {
            return $this->getSaltingInstanceInternal($this->settings['set_crypt_type']);
        } else {
            foreach (['typo3_md5', 'typo3_blowfish', 'typo3_phpass', 'typo3_pbkdf2'] as $cryptType) {
                $saltingInstance = $this->getSaltingInstanceInternal($cryptType);
                if ($saltingInstance->isValidSaltedPW($saltedHash)) {
                    return $saltingInstance;
                }
            }
        }

        return false;
    }

    /**
     * @param string $cryptType
     * @return BlowfishSalt|Md5Salt|Pbkdf2Salt|PhpassSalt
     */
    protected function getSaltingInstanceInternal($cryptType)
    {
        switch ($cryptType) {
            case 'typo3_md5':
                require_once(__DIR__ . '/../Salt/Md5Salt.php');
                $saltingInstance = new Md5Salt();
                break;
            case 'typo3_blowfish':
                require_once(__DIR__ . '/../Salt/BlowfishSalt.php');
                $saltingInstance = new BlowfishSalt();
                break;
            case 'typo3_phpass':
                require_once(__DIR__ . '/../Salt/PhpassSalt.php');
                $saltingInstance = new PhpassSalt();
                break;
            case 'typo3_pbkdf2':
                require_once(__DIR__ . '/../Salt/Pbkdf2Salt.php');
                $saltingInstance = new Pbkdf2Salt();
                break;
        }

        return $saltingInstance;
    }

    /**
     * Store a value in memcache or the session, if no memcache is available
     * @param string $key  The key
     * @param mixed $value The value to store
     * @param int $ttl (optional) defaults to 3600 seconds.
     */
    private function setCache($key, $value, $ttl=3600)
    {
        if ($this -> cache === NULL)
        {
            $_SESSION[$this -> session_cache_name][$key] = array(
                'value' => $value,
                'time' => time(),
                'ttl' => $ttl,
            );
        } else
        {
            $this -> cache -> set($key,$value,$ttl);
        }
    }

    /**
     * Fetch a value from memcache or session, if memcache is not available.
     * Returns NULL if there's no value stored or the value expired.
     * @param string $key
     * @return mixed|NULL
     */
    private function getCache($key)
    {
        $retVal = NULL;
        if ($this -> cache === NULL)
        {
            if (isset($_SESSION[$this -> session_cache_name],
                        $_SESSION[$this -> session_cache_name][$key]))
            {
                $value = $_SESSION[$this -> session_cache_name][$key];
                if (time() < $value['time'] + $value['ttl'])
                {
                    $retVal = $value['value'];
                }
            }
        } else
        {
            $retVal = $this -> cache -> get ($key);
        }
        return $retVal;
    }

    function hash_equals( $a, $b ) {
        $a_length = strlen( $a );

        if ( $a_length !== strlen( $b ) ) {
            return false;
            }
            $result = 0;

        // Do not attempt to "optimize" this.
        for ( $i = 0; $i < $a_length; $i++ ) {
        $result |= ord( $a[ $i ] ) ^ ord( $b[ $i ] );
        }

            //Hide the length of the string
        $additional_length=200-($a_length % 200);
        $tmp=0;
           $c="abCD";
        for ( $i = 0; $i < $additional_length; $i++ ) {
            $tmp |= ord( $c[ 0 ] ) ^ ord( $c[ 0 ] );
        }

        return $result === 0;
}

}

