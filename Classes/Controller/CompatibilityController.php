<?php
/**
 * @file
 *   Backend compatibility controller, from 8.x to 4.5
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
 *   kreXX Copyright (C) 2014-2016 Brainworxx GmbH
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

use Brainworxx\Krexx\View\Messages;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

// The mainproblem with 7.0 is, that compatibility6 may or may not be installed.
// If not, I have to put this thing here, hoping not to break anything!
if (!class_exists('Tx_Extbase_MVC_Controller_ActionController')) {
    /**
     * Class Tx_Extbase_MVC_Controller_ActionController
     */
    class Tx_Extbase_MVC_Controller_ActionController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
    {
    }
}
if (!class_exists('Tx_Extbase_MVC_Controller_Arguments')) {
    /**
     * Class Tx_Extbase_MVC_Controller_Arguments
     */
    class Tx_Extbase_MVC_Controller_Arguments extends \TYPO3\CMS\Extbase\Mvc\Controller\Arguments
    {
    }
}
if (!class_exists('t3lib_FlashMessage')) {
    /**
     * Class t3lib_FlashMessage
     */
    class t3lib_FlashMessage extends \TYPO3\CMS\Core\Messaging\FlashMessage
    {
    }
}

// The 7.3'er autoloader tries to include this file twice, probably
// because of the class mappings above. I need to make sure not to
// redeclare the Tx_Includekrexx_Controller_CompatibilityController and throw
// a fatal.
if (!class_exists('Tx_Includekrexx_Controller_CompatibilityController')) {

    /**
     * Class Tx_Includekrexx_Controller_IndexController
     *
     * This is not a real controller. It hosts all those ugly workarounds to keep
     * this extesion compatible back to 4.5. This  makes the other controllers
     * (hopefully) more readable.
     */
    class Tx_Includekrexx_Controller_CompatibilityController extends Tx_Extbase_MVC_Controller_ActionController
    {

        /**
         * List of all setting-names for which we are accepting values.
         *
         * @var array
         */
        protected $allowedSettingsNames = array(
            'skin',
            'memoryLeft',
            'maxRuntime',
            'folder',
            'maxfiles',
            'destination',
            'maxCall',
            'disabled',
            'detectAjax',
            'analyseProtected',
            'analysePrivate',
            'analyseTraversable',
            'debugMethods',
            'level',
            'analyseMethodsAtall',
            'analyseProtectedMethods',
            'analysePrivateMethods',
            'registerAutomatically',
            'backtraceAnalysis',
            'analyseConstants',
        );

        /**
         * List of all sections for which we are accepting values
         *
         * @var array
         */
        protected $allowedSections = array(
            'runtime',
            'output',
            'properties',
            'methods',
            'backtraceAndError',
        );

        /**
         * Wrapper for the FlashMessage, which was changed in 7.0.
         *
         * @param string $text
         *   The text of the message.
         * @param string $title
         *   The title of the message
         * @param integer $severity
         *   The severity of the message.
         */
        protected function addMessage($text, $title, $severity)
        {
            if (empty($text)) {
                // No text, no message.
                return;
            }
            if (!isset($this->flashMessageContainer)) {
                $this->addFlashMessage($text, $title, $severity);
            } else {
                $this->flashMessageContainer->add($text, $title, $severity);
            }
        }

        /**
         * Injects the arguments
         *
         * @param Tx_Extbase_MVC_Controller_Arguments $arguments
         *   The arguments from the call to the controller.
         */
        public function injectArguments(Tx_Extbase_MVC_Controller_Arguments $arguments)
        {
            $this->arguments = $arguments;
        }

        /**
         * Wrapper for the \TYPO3\CMS\Extbase\Utility\LocalizationUtility
         *
         * @param string $key
         *   The key we want to translate
         * @param null|array $args
         *   The strings from the controller we want to place inside the
         *   translation.
         *
         * @return string
         *   The translation itself.
         */
        protected function LLL($key, $args = null)
        {

            if ((int)TYPO3_version > 6) {
                // 7+ version.
                $result = LocalizationUtility::translate($key, 'includekrexx', $args);
            } else {
                // Version 4.5 until 6.2
                $result = Tx_Extbase_Utility_Localization::translate($key, 'includekrexx', $args);
            }

            return $result;
        }

        /**
         * Gets all messages from kreXX and translates them.
         *
         * @return array
         *   The translated messages.
         */
        protected function getTranslatedMessages()
        {
            $result = array();
            // Get the keys and the args.
            $keys = Messages::getKeys();

            foreach ($keys as $message) {
                // And translate them and add a linebreak.
                $result[] = $this->LLL($message['key'], $message['params']);
            }

            return $result;
        }

        /**
         * Assigns the content of a css file as a variable to the view.
         *
         * Since addJsFile and addCssFile got removed in 7.0, I have to resort to
         * adding stuff inline.
         *
         * @see
         *   All the template layouts renders the css.
         *
         * @param string $file
         *   Filename of the css file, located in the resource public dir.
         */
        protected function addCssToView($file)
        {
            if (class_exists('TYPO3\CMS\Core\Utility\GeneralUtility')) {
                $uri = GeneralUtility::getFileAbsFileName('EXT:includekrexx/Resources/Public/Css/' . $file);
            } else {
                $uri = \t3lib_div::getFileAbsFileName('EXT:includekrexx/Resources/Public/Css/' . $file);
            }

            if (is_readable($uri)) {
                $this->view->assign('css', file_get_contents($uri));
            }
        }
    }

}