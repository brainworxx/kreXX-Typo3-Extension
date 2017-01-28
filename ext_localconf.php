<?php
/**
 * kreXX: Krumo eXXtended
 *
 * kreXX is a debugging tool, which displays structured information
 * about any PHP object. It is a nice replacement for print_r() or var_dump()
 * which are used by a lot of PHP developers.
 *
 * kreXX is a fork of Krumo, which was originally written by:
 * Kaloyan K. Tsvetkov <kaloyan@kaloyan.info>
 *
 * @author
 *   brainworXX GmbH <info@brainworxx.de>
 *
 * @license
 *   http://opensource.org/licenses/LGPL-2.1
 *
 *   GNU Lesser General Public License Version 2.1
 *
 *   kreXX Copyright (C) 2014-2017 Brainworxx GmbH
 *
 *   This library is free software; you can redistribute it and/or modify it
 *   under the terms of the GNU Lesser General Public License as published by
 *   the Free Software Foundation; either version 2.1 of the License, or (at
 *   your option) any later version.
 *   This library is distributed in the hope that it will be useful, but WITHOUT
 *   ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 *   FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License
 *   for more details.
 *   You should have received a copy of the GNU Lesser General Public License
 *   along with this library; if not, write to the Free Software Foundation,
 *   Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (class_exists('\\TYPO3\\CMS\\Core\\Utility\\ExtensionManagementUtility')) {
    // 6.0 ++
    $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY);
} else {
    // The old way.
    $extPath = t3lib_extMgm::extPath($_EXTKEY);
}


// Do some "autoloading" stuff which may or may not be done by TYPO3
// automatically, depending on the version.
if (version_compare(TYPO3_version, '7.2', '>')) {
    // TYPO3 7.3 / 7.4 does not autoload our classes anymore, so we do this here.
    if (!class_exists('Tx_Includekrexx_Controller_CompatibilityController')) {
        include_once($extPath . 'Classes/Controller/CompatibilityController.php');
    }
    if (!class_exists('Tx_Includekrexx_Controller_FormConfigController')) {
        include_once($extPath . 'Classes/Controller/FormConfigController.php');
    }
    if (!class_exists('Tx_Includekrexx_Controller_LogController')) {
        include_once($extPath . 'Classes/Controller/LogController.php');
    }
    if (!class_exists('Tx_Includekrexx_Controller_HelpController')) {
        include_once($extPath . 'Classes/Controller/HelpController.php');
    }
    if (!class_exists('Tx_Includekrexx_Controller_ConfigController')) {
        include_once($extPath . 'Classes/Controller/ConfigController.php');
    }
    if (!class_exists('Tx_Includekrexx_Controller_CookieController')) {
        include_once($extPath . 'Classes/Controller/CookieController.php');
    }
    if (!class_exists('Tx_Includekrexx_ViewHelpers_MessagesViewHelper')) {
        include_once($extPath . 'Classes/ViewHelpers/MessagesViewHelper.php');
    }
    if (!class_exists('Tx_Includekrexx_ViewHelpers_DebugViewHelper')) {
        include_once($extPath . 'Classes/ViewHelpers/DebugViewHelper.php');
    }

    if (version_compare(TYPO3_version, '8.0' ,'>=')) {
        // Some special compatibility stuff for 8.0 , Fluid and it's ViewHelpers.
        if (!class_exists('\\Tx_Includekrexx_ViewHelpers\\MessagesViewHelper')) {
            include_once($extPath . 'Classes/ViewHelpers/MessagesViewHelper8.php');
        }
        if (!class_exists('\\Tx_Includekrexx_ViewHelpers\\DebugViewHelper')) {
            include_once($extPath . 'Classes/ViewHelpers/DebugViewHelper8.php');
        }
    }
}

if (!class_exists('Brainworxx\\Krexx\\Service\\Config\\Fallback')) {
    include_once($extPath . 'Resources/Private/krexx/src/service/config/Fallback.php');
}
if (!class_exists('Brainworxx\\Krexx\\Service\\Config\\Security')) {
    include_once($extPath . 'Resources/Private/krexx/src/service/config/Security.php');
}
if (!class_exists('Tx_Includekrexx_Rewrite_ServiceConfigSecurity')) {
    include_once($extPath . 'Classes/Rewrite/ServiceConfigSecurity.php');
}
$krexxFile = $extPath . 'Resources/Private/krexx/Krexx.php';


// Add our specific overwrites.
// When we include the kreXX mainfile, it gets bootstrapped.
// But then it is already to late for these overwrites.
// To prevent a reset of all classes, we store these info here.
// When overwriting a class at a later date (for example, in our fluid debugger)
// we may need to reset some of the singletons in the $pool, or even create a
// new pool.
$GLOBALS['kreXXoverwrites'] = array(
    'Brainworxx\\Krexx\\Service\\Config\\Security' => 'Tx_Includekrexx_Rewrite_ServiceConfigSecurity'
);

// We load the kreXX library.
// The class__existst triggers the composer autoloading, if available.
// It not, we use the bundeled version wich comes with the externsion.
if (file_exists($krexxFile) && !class_exists('Krexx')) {
    include_once $krexxFile;
}