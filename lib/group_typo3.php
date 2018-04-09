<?php

namespace OCA\user_typo3;

use \OCA\user_typo3\lib\Helper;
use OCP\Util;

class OC_GROUP_TYPO3 extends \OC\Group\Backend
{
    protected $settings;
    protected $helper;

    public function __construct()
    {
        $this -> helper = new \OCA\user_typo3\lib\Helper();
        $domain = \OC::$server->getRequest()->getServerHost();
        $this -> settings = $this -> helper -> loadSettingsForDomain($domain);
        $this -> helper -> connectToDb($this -> settings);        
        return false;
    }

    public function getUserGroups($uid) {
        $rows = $this -> helper -> runQuery('getUserGroups', array('uid' => $uid), false, true);
        if($rows === false)
        {
            Util::writeLog('OC_USER_TYPO3', "Found no group", Util::DEBUG);
            return [];
        }
        $groups = array();
        foreach($rows as $row)
        {
            $groups[] = $row['title'];
        }
        if (!empty($this->settings['set_admin_groups'])) {
            // If the user is in one of the selected administrator groups, the nextcloud admin group is added to the list
            if (!in_array(Helper::OC_ADMIN_GROUP, $groups)) {
                $adminGroups = explode('|', $this->settings['set_admin_groups']);
                if (count(array_intersect($adminGroups, $groups)) > 0) {

                    $groups[] = Helper::OC_ADMIN_GROUP;
                }
            }
        }
        return $groups;
    }

    public function getGroups($search = '', $limit = null, $offset = null) {
        $search = "%".$search."%";
        $rows = $this -> helper -> runQuery('getGroups', array('search' => $search), false, true, array('limit' => $limit, 'offset' => $offset));
        if($rows === false)
        {
            return [];
        }   
        $groups = array();
        foreach($rows as $row)
        {
            $groups[] = $row['title'];
        }
        // we need to add admin group to the groups supported by this app so typo3 users are processed when viewing this group in backend
        if (!in_array(Helper::OC_ADMIN_GROUP, $groups)) {
            $groups[] = Helper::OC_ADMIN_GROUP;
        }
        return $groups;
    }

    public function usersInGroup($gid, $search = '', $limit = null, $offset = null) {
        $rows = $this -> helper -> runQuery('getGroupUsers', array('gid' => $gid), false, true);
        if($rows === false)
        {
            Util::writeLog('OC_USER_TYPO3', "Found no users for group", Util::DEBUG);
            return [];
        }
        $users = array();
        foreach($rows as $row)
        {
            $users[] = $row['username'];
        } 
        return $users;
    }

    public function countUsersInGroup($gid, $search = '') {
        $search = "%".$search."%";
        $count = $this -> helper -> runQuery('countUsersInGroup', array('gid' => $gid, 'search' => $search));
        if($count === false)
        {
            return 0;
        } else {
            return intval(reset($count));
        }
    }
}
?>
