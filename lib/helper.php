<?php

/**
 * nextCloud - user_typo3
 *
 * @author Andreas Böhler and contributors
 * @copyright 2012-2015 Andreas Böhler <dev (at) aboehler (dot) at>
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

namespace OCA\user_typo3\lib;
use OCP\IConfig;
use OCP\Util;

class Helper {

    const OC_ADMIN_GROUP = 'admin';
    protected $db;
    protected $db_conn;
    protected $settings;

    /**
     * The default constructor initializes some parameters
     */
    public function __construct()
    {
        $this->db_conn = false;
    }

    /**
     * Return an array with all supported parameters
     * @return array Containing strings of the parameters
     */
    public function getParameterArray()
    {
        $params = array(
            'sql_hostname',
            'sql_username',
            'sql_password',
            'sql_database',
            'sql_driver',
            'set_default_domain',
            'set_strip_domain',
            'set_crypt_type',
            'set_mail_sync_mode',
            'set_allow_pwchange',
            'set_admin_groups',
            'set_import_groups'
        );

        return $params;
    }

    /**
     * Load the settings for a given domain. If the domain is not found,
     * the settings for 'default' are returned instead.
     * @param string $domain The domain name
     * @return array of settings
     */
    public function loadSettingsForDomain($domain)
    {
        Util::writeLog('OC_USER_TYPO3', "Trying to load settings for domain: " . $domain, Util::DEBUG);
        $settings = array();
        $sql_host = \OC::$server->getConfig()->getAppValue('user_typo3', 'sql_hostname_'.$domain, '');
        if($sql_host === '')
        {
            $domain = 'default';
        }
        $params = $this -> getParameterArray();
        foreach($params as $param)
        {
            $settings[$param] = \OC::$server->getConfig()->getAppValue('user_typo3', $param.'_'.$domain, '');
        }
        Util::writeLog('OC_USER_TYPO3', "Loaded settings for domain: " . $domain, Util::DEBUG);
        return $settings;
    }

    /**
     * Run a given query type and return the results
     * @param string $type The type of query to run
     * @param array $params The parameter array of the query (i.e. the values to bind as key-value pairs)
     * @param bool $execOnly Only execute the query, but don't fetch the results (optional, default = false)
     * @param bool $fetchArray Fetch an array instead of a single row (optional, default=false)
     * @param array $limits use the given limits for the query (optional, default = empty)
     * @return mixed
     */
    public function runQuery($type, $params, $execOnly = false, $fetchArray = false, $limits = array())
    {
        Util::writeLog('OC_USER_TYPO3', "Entering runQuery for type: " . $type, Util::DEBUG);
        if(!$this -> db_conn)
            return false;

        switch($type)
        {
            case 'getMail':
                $query = "SELECT email FROM fe_users WHERE username = :uid";
            break;

            case 'setMail':
                $query = "UPDATE fe_users SET email = :currMail WHERE username = :uid";
            break;

            case 'getPass':
                $additionalWhereClause = '1=1 AND deleted = 0 AND disable = 0';
                $query = "SELECT password FROM fe_users WHERE username = :uid AND $additionalWhereClause";
            break;

            case 'setPass':
                $query = "UPDATE fe_users SET password = :enc_password WHERE username = :uid";
            break;

            case 'countUsers':
                $additionalWhereClause = '1=1 AND fe_users.deleted = 0 AND fe_users.disable = 0';
                $additionalWhereClause .= $this->getUsergroupsAdditionalWhereClause($params);

                $query = "SELECT COUNT(DISTINCT fe_users.uid) FROM fe_users LEFT JOIN fe_groups ON FIND_IN_SET(fe_groups.uid, fe_users.usergroup) WHERE username LIKE :search AND $additionalWhereClause";
            break;

            case 'getUsers':
                $additionalWhereClause = '1=1 AND fe_users.deleted = 0 AND fe_users.disable = 0';
                $additionalWhereClause .= $this->getUsergroupsAdditionalWhereClause($params);

                $query = "SELECT fe_users.username FROM fe_users LEFT JOIN fe_groups ON FIND_IN_SET(fe_groups.uid, fe_users.usergroup) WHERE fe_users.username LIKE :search AND $additionalWhereClause ORDER BY fe_users.username";
            break;

            case 'userExists':
                $additionalWhereClause = '1=1 AND deleted = 0 AND disable = 0';
                $query = "SELECT username FROM fe_users WHERE username = :uid AND $additionalWhereClause";
            break;

            case 'getDisplayName':
                $additionalWhereClause = '1=1 AND deleted = 0 AND disable = 0';
                $query = "SELECT `name` FROM fe_users WHERE username = :uid AND $additionalWhereClause";
            break;

            case 'mysqlEncryptSalt':
                $query = "SELECT ENCRYPT(:pw, :salt);";
            break;

            case 'mysqlEncrypt':
                $query = "SELECT ENCRYPT(:pw);";
            break;

            case 'mysqlPassword':
                $query = "SELECT PASSWORD(:pw);";
            break;

            case 'getUserGroups':
                $additionalWhereClause = '1=1';
                $additionalWhereClause .= $this->getUsergroupsAdditionalWhereClause($params);

                $query = "SELECT fe_groups.title FROM fe_groups LEFT JOIN `fe_users` ON FIND_IN_SET(fe_groups.uid, fe_users.usergroup) WHERE fe_users.username = :uid AND $additionalWhereClause";
            break;

            case 'getGroups':
                $additionalWhereClause = '1=1';
                $additionalWhereClause .= $this->getUsergroupsAdditionalWhereClause($params);

                $query = "SELECT title FROM fe_groups WHERE title LIKE :search AND $additionalWhereClause";
            break;

            case 'getGroupUsers':
                $groupParams = [ ':gid' ];
                if ($params['gid'] == self::OC_ADMIN_GROUP) {
                    $adminGroups = explode('|', $this->settings['set_admin_groups']);
                    foreach ($adminGroups as $index => $adminGroup) {
                        $params['adminid' . $index] = $adminGroup;
                        $groupParams[] = ':adminid' . $index;
                    }
                }
                $inQuery = implode(', ', $groupParams);

                $query = "SELECT DISTINCT fe_users.username FROM fe_users LEFT JOIN fe_groups ON FIND_IN_SET(fe_groups.uid, fe_users.usergroup) WHERE fe_groups.title IN ($inQuery)";
                break;

            case 'countUsersInGroup':
                $groupParams = [ ':gid' ];
                if ($params['gid'] == self::OC_ADMIN_GROUP) {
                    $adminGroups = explode('|', $this->settings['set_admin_groups']);
                    foreach ($adminGroups as $index => $adminGroup) {
                        $params['adminid' . $index] = $adminGroup;
                        $groupParams[] = ':adminid' . $index;
                    }
                }
                $inQuery = implode(', ', $groupParams);

                $query = "SELECT count(DISTINCT fe_users.uid) FROM fe_users LEFT JOIN fe_groups ON FIND_IN_SET(fe_groups.uid, fe_users.usergroup) WHERE fe_groups.title IN ($inQuery) AND fe_users.username LIKE :search";
                break;

            case 'getAllGroups':
                $query = "SELECT title FROM fe_groups WHERE title LIKE :search";
                break;
        }

        if(isset($limits['limit']) && $limits['limit'] !== null)
        {
            $limit = intval($limits['limit']);
            $query .= " LIMIT ".$limit;
        }

        if(isset($limits['offset']) && $limits['offset'] !== null)
        {
            $offset = intval($limits['offset']);
            $query .= " OFFSET ".$offset;
        }

        Util::writeLog('OC_USER_TYPO3', "Preparing query: $query", Util::DEBUG);
        $result = $this -> db -> prepare($query);
        foreach($params as $param => $value)
        {
            $result -> bindValue(":".$param, $value);
        }
        Util::writeLog('OC_USER_TYPO3', "Executing query...", Util::DEBUG);
        if(!$result -> execute())
        {
            $err = $result -> errorInfo();
            Util::writeLog('OC_USER_TYPO3', "Query failed: " . $err[2], Util::DEBUG);
            return false;
        }
        if($execOnly === true)
        {
            return true;
        }
        Util::writeLog('OC_USER_TYPO3', "Fetching result...", Util::DEBUG);
        if($fetchArray === true)
            $row = $result -> fetchAll();
        else
            $row = $result -> fetch();

        if(!$row)
        {
            return false;
        }
        return $row;
    }

    /**
     * Connect to the database using ownCloud's DBAL
     * @param array $settings The settings for the connection
     * @return bool
     */
    public function connectToDb($settings)
    {
        $this -> settings = $settings;
        $cm = new \OC\DB\ConnectionFactory(\OC::$server->getSystemConfig());
        $parameters = array('host' => $this -> settings['sql_hostname'],
                'password' => $this -> settings['sql_password'],
                'user' => $this -> settings['sql_username'],
                'dbname' => $this -> settings['sql_database'],
                'tablePrefix' => ''
            );
        try
        {
            $this -> db = $cm -> getConnection($this -> settings['sql_driver'], $parameters);
            $this -> db -> query("SET NAMES 'UTF8'");
            $this -> db_conn = true;
            return true;
        }
        catch (\Exception $e)
        {
            Util::writeLog('OC_USER_TYPO3', 'Failed to connect to the database: ' . $e -> getMessage(), Util::ERROR);
            $this -> db_conn = false;
            return false;
        }
    }

    /**
     * Check if a given table exists
     * @param array $parameters The connection parameters
     * @param string $sql_driver The SQL driver to use
     * @param string $table The table name to check
     * @param array True if found, otherwise false
     */
    public function verifyTable($parameters, $sql_driver, $table)
    {
        $tablesWithSchema = $this->getTables($parameters, $sql_driver, true);
        $tablesWithoutSchema = $this->getTables($parameters, $sql_driver, false);
        return in_array($table, $tablesWithSchema, true) || in_array($table, $tablesWithoutSchema, true);
    }

    /**
     * Retrieve a list of tables for the given connection parameters
     * @param array $parameters The connection parameters
     * @param string $sql_driver The SQL driver to use
     * @param boolean $schema Return table name with schema
     * @return array The found tables, empty if an error occurred
     */
    public function getTables($parameters, $sql_driver, $schema = true)
    {
        $cm = new \OC\DB\ConnectionFactory(\OC::$server->getSystemConfig());
        try {
            $conn = $cm -> getConnection($sql_driver, $parameters);
            $platform = $conn -> getDatabasePlatform();

            $queryTables = $platform->getListTablesSQL();
            $queryViews = $platform->getListViewsSQL($parameters['dbname']);
            $ret = array();

            $result = $conn->executeQuery($queryTables);
            while ($row = $result->fetch()) {
                $name = $this->getTableNameFromRow($sql_driver, $parameters['dbname'], $row, $schema);
                $ret[] = $name;
            }

            $result = $conn->executeQuery($queryViews);
            while ($row = $result->fetch()) {
                $name = $this->getViewNameFromRow($sql_driver, $row, $schema);
                $ret[] = $name;
            }
            return $ret;
        }
        catch(\Exception $e)
        {
            return array();
        }
    }

    /**
     * Retrieve table name from database list table SQL
     * @param string $sql_driver The SQL driver to use
     * @param string $dbname The database name
     * @param array $row Query result row
     * @param boolean $schema Return table name with schema
     * @return string Table name
     */
    public function getTableNameFromRow($sql_driver, $dbname, $row, $schema)
    {
        switch ($sql_driver) {
            case 'mysql':
                return $row['Tables_in_' . $dbname];
            case 'pgsql':
                if ($schema) {
                    return $row['schema_name'] . '.' . $row['table_name'];
                } else {
                    return $row['table_name'];
                }
            default:
                return null;
        }
    }

    /**
     * Retrieve view name from database list table SQL
     * @param string $sql_driver The SQL driver to use
     * @param array $row Query result row
     * @param boolean $schema Return table name with schema
     * @return string Table name
     */
    public function getViewNameFromRow($sql_driver, $row, $schema)
    {
        switch ($sql_driver) {
            case 'mysql':
                return $row['TABLE_NAME'];
            case 'pgsql':
                if ($schema) {
                    return $row['schemaname'] . '.' . $row['viewname'];
                } else {
                    return $row['viewname'];
                }
            default:
                return null;
        }
    }

    protected function getUsergroupsAdditionalWhereClause(&$params)
    {
        $additionalWhereClause = '';
        if (!empty($this->settings['set_import_groups'])) {
            $userGroups = array_merge(
                explode('|', $this->settings['set_import_groups']),
                explode('|', $this->settings['set_admin_groups'])
            );
            $usergroupParams = [];
            foreach ($userGroups as $index => $userGroup) {
                $params['usergroupid' . $index] = $userGroup;
                $usergroupParams[] = ':usergroupid' . $index;
            }
            if (!empty($usergroupParams)) {
                $additionalWhereClause = ' AND fe_groups.title IN (' . implode(', ', $usergroupParams) . ')';
            }
        }

        return $additionalWhereClause;
    }


}
