<?php
/**
* Copyright (c) 2015, Andreas Böhler <dev@aboehler.at>
* This file is licensed under the Affero General Public License version 3 or later.
* See the COPYING-README file.
*/
/** @var $this \OCP\Route\IRouter */
$this->create('user_typo3_ajax_settings', 'ajax/settings.php')->actionInclude('user_typo3/ajax/settings.php');
