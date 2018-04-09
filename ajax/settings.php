<?php

/**
 * ownCloud - user_typo3
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

 /**
  * This is the AJAX portion of the settings page.
  * 
  * It can:
  *   - Verify the connection settings
  *   - Load autocomplete values for tables
  *   - Load autocomplete values for columns
  *   - Save settings for a given domain
  *   - Load settings for a given domain
  * 
  * It always returns JSON encoded responses
  */
 
namespace OCA\user_typo3;

// Init owncloud

// Check if we are a user
\OCP\User::checkAdminUser();
\OCP\JSON::checkAppEnabled('user_typo3');

// CSRF checks
\OCP\JSON::callCheck();


$helper = new \OCA\user_typo3\lib\Helper;

$l = \OC::$server->getL10N('user_typo3');

$params = $helper -> getParameterArray();
$response = new \OCP\AppFramework\Http\JSONResponse();

// Check if the request is for us
if(isset($_POST['appname']) && ($_POST['appname'] === 'user_typo3') && isset($_POST['function']) && isset($_POST['domain']))
{
    $domain = $_POST['domain'];
    switch($_POST['function'])
    {
        // Save the settings for the given domain to the database
        case 'saveSettings':
            $parameters = array('host' => $_POST['sql_hostname'],
                'password' => $_POST['sql_password'],
                'user' => $_POST['sql_username'],
                'dbname' => $_POST['sql_database'],
                'tablePrefix' => ''
            );
            
            // Check if the table exists
            if(!$helper->verifyTable($parameters, $_POST['sql_driver'], 'fe_users'))
            {
                $response->setData(array('status' => 'error',
                            'data' => array('message' => $l -> t('The selected SQL table fe_users does not exist!'))));
                break;
            }
            if(!$helper->verifyTable($parameters, $_POST['sql_driver'], 'fe_groups'))
            {
                $response->setData(array('status' => 'error',
                    'data' => array('message' => $l -> t('The selected SQL table fe_groups does not exist!'))));
                break;
            }

            // If we reach this point, all settings have been verified
            foreach($params as $param)
            {
                // Special handling for checkbox fields
                if(isset($_POST[$param]))
                {
                    if($param === 'set_strip_domain')
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', 'set_strip_domain_'.$domain, 'true');
                    } 
                    elseif($param === 'set_allow_pwchange')
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', 'set_allow_pwchange_'.$domain, 'true');
                    }
                    elseif($param === 'set_active_invert')
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', 'set_active_invert_'.$domain, 'true');
                    }
                    else
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', $param.'_'.$domain, $_POST[$param]);
                    }
                } else
                {
                    if($param === 'set_strip_domain')
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', 'set_strip_domain_'.$domain, 'false');
                    }
                    elseif($param === 'set_allow_pwchange')
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', 'set_allow_pwchange_'.$domain, 'false');
                    }
                    elseif($param === 'set_active_invert')
                    {
                        \OC::$server->getConfig()->setAppValue('user_typo3', 'set_active_invert_'.$domain, 'false');
                    }
                }
            }
            $response->setData(array('status' => 'success',            
                    'data' => array('message' => $l -> t('Application settings successfully stored.'))));
        break;

        // Load the settings for a given domain
        case 'loadSettingsForDomain':
            $retArr = array();
            foreach($params as $param)
            {
                $retArr[$param] = \OC::$server->getConfig()->getAppValue('user_typo3', $param.'_'.$domain, '');
            }
            $response->setData(array('status' => 'success',            
                            'settings' => $retArr));
        break;

        // Try to verify the database connection settings
        case 'verifySettings':
            $cm = new \OC\DB\ConnectionFactory(\OC::$server->getSystemConfig());

            if(!isset($_POST['sql_driver']))
            {
                $response->setData(array('status' => 'error',
                            'data' => array('message' => $l -> t('Error connecting to database: No driver specified.'))));
                break;
            }

            if(($_POST['sql_hostname'] === '') || ($_POST['sql_database'] === ''))
            {
                $response->setData(array('status' => 'error',
                        'data' => array('message' => $l -> t('Error connecting to database: You must specify at least host and database'))));
                break;
            }

            $parameters = array('host' => $_POST['sql_hostname'],
                'password' => $_POST['sql_password'],
                'user' => $_POST['sql_username'],
                'dbname' => $_POST['sql_database'],
                'tablePrefix' => ''
            );

            try {
                $conn = $cm -> getConnection($_POST['sql_driver'], $parameters);
                $response->setData(array('status' => 'success',
                            'data' => array('message' => $l -> t('Successfully connected to database'))));
            }
            catch(\Exception $e)
            {
                $response->setData(array('status' => 'error',
                            'data' => array('message' => $l -> t('Error connecting to database: ').$e->getMessage())));
            }
        break;
    }

} else
{
    // If the request was not for us, set an error message
    $response->setData(array('status' => 'error', 
                    'data' => array('message' => $l -> t('Not submitted for us.'))));
}

// Return the JSON array
echo $response->render();
