<?php
/**
 * @file
 *   ext_localconf.php stuff for kreXX
 *   kreXX: Krumo eXXtended
 *
 *   kreXX is a debugging tool, which displays structured information
 *   about any PHP object. It is a nice replacement for print_r() or var_dump()
 *   which are used by a lot of PHP developers.
 *
 *   kreXX is a fork of Krumo, which was originally written by:
 *   Kaloyan K. Tsvetkov <kaloyan@kaloyan.info>
 *
 * @author brainworXX GmbH <info@brainworxx.de>
 *
 * @license http://opensource.org/licenses/LGPL-2.1
 *   GNU Lesser General Public License Version 2.1
 *
 *   kreXX Copyright (C) 2014-2015 Brainworxx GmbH
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

// One of the main problems with the extension manager is, that it does not
// clear *ALL* of the caches, even if you tell him to. The ext_localconf cache
// may not be cleared when updating an extension.
// When the extension changes, the ext_localconf changes, too. But if it is
// cached in a file and old, not-anymore-existing methods from the now changed
// extension are called, this may result in a PHP Fatal or WSOD.
// The solution is, to simply outsource this stuff in another file, and
// then just call this file. This may not be the fastest solution, but kreXX
// is a debugging tool which should not be used on a productive system.


if (! defined('TYPO3_MODE')) {
  die('Access denied.');
}

if ((int)TYPO3_version < 7) {
  $filename = t3lib_extMgm::extPath($_EXTKEY, 'Resources/Private/krexx/Krexx.php');
}
else {
  $filename = TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Resources/Private/krexx/Krexx.php');
}
if (file_exists($filename) && !class_exists('Krexx')) {
  // We load the kreXX library.
  // 7.3 is able to autoload krexx before this point.
  // We will not include it again!
  include_once $filename;
}
// We point kreXX to its ini file.
// For some reasons, this class may or may not be declared in 6.2 during an
// update.
if (class_exists('Brainworxx\Krexx\Framework\Config')) {
  \Brainworxx\Krexx\Framework\Config::setPathToIni(PATH_site . 'uploads/tx_includekrexx/Krexx.ini');
}

// Typo3 7.4 does not autoload our controller anymore, so we do this here.
if (!class_exists('Tx_Includekrexx_Controller_IndexController') && (int)TYPO3_version > 6) {
  include_once (TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Classes/Controller/IndexController.php'));
  include_once (TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Classes/ViewHelpers/DebugViewHelper.php'));
}
