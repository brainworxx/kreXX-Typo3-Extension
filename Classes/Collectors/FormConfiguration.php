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
 *   kreXX Copyright (C) 2014-2019 Brainworxx GmbH
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

namespace Brainworxx\Includekrexx\Collectors;

use Brainworxx\Includekrexx\Bootstrap\Bootstrap;
use Brainworxx\Includekrexx\Controller\AbstractController;
use Brainworxx\Krexx\Service\Config\Fallback;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FormConfiguration extends AbstractCollector
{
    /**
     * Assigning the form configuration to the view.
     *
     * @param \TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view
     */
    public function assignData(ViewInterface $view)
    {
        if ($this->hasAccess() === false) {
            // No access.
            return;
        }

        $dropdown = array(
            'full' => LocalizationUtility::translate('full', Bootstrap::EXT_KEY),
            'display' => LocalizationUtility::translate('display', Bootstrap::EXT_KEY),
            'none' => LocalizationUtility::translate('none', Bootstrap::EXT_KEY)
        );

        $iniConfig = $this->pool->config->iniConfig;
        $config = array();
        foreach ($this->pool->config->feConfigFallback as $settingsName => $fallback) {
            $config[$settingsName] = array();
            $config[$settingsName]['name'] = $settingsName;
            $config[$settingsName]['options'] = $dropdown;
            $config[$settingsName]['useFactorySettings'] = false;
            $config[$settingsName]['value'] =  $this->convertKrexxFeSetting(
                $iniConfig->getFeConfigFromFile($settingsName)
            );
            $config[$settingsName]['fallback'] = $dropdown[
                $this->convertKrexxFeSetting(
                    $iniConfig->feConfigFallback[$settingsName][$iniConfig::RENDER]
                )
            ];

            // Check if we have a value. If not, we need to load the
            // factory settings. We also need to set the info, if we
            // are using the factory settings, at all.
            if (is_null($config[$settingsName]['value'])) {
                $config[$settingsName]['value'] = $this->convertKrexxFeSetting(
                    $iniConfig->feConfigFallback[$settingsName][$iniConfig::RENDER]
                );
                $config[$settingsName]['useFactorySettings'] = true;
            }
        }

        $view->assign('formConfig', $config);
    }

    /**
     * Converts the kreXX FE config setting.
     *
     * Letting people choose what kind of form element will
     * be used does not really make sense. We will convert the
     * original kreXX settings to a more usable form for the editor.
     *
     * @param array $values
     *   The values we want to convert.
     *
     * @return string|null
     *   The converted values.
     */
    protected function convertKrexxFeSetting($values)
    {
        $result = null;
        if (is_array($values)) {
            // Explanation:
            // full -> is editable and values will be accepted
            // display -> we will only display the settings
            // The original values include the name of a template partial
            // with the form element.
            if ($values[Fallback::RENDER_TYPE] == Fallback::RENDER_TYPE_NONE) {
                // It's not visible, thus we do not accept any values from it.
                $result = 'none';
            }
            if ($values[Fallback::RENDER_EDITABLE] == Fallback::VALUE_TRUE &&
                $values[Fallback::RENDER_TYPE] != Fallback::RENDER_TYPE_NONE
            ) {
                // It's editable and visible.
                $result = 'full';
            }
            if ($values[Fallback::RENDER_EDITABLE] == Fallback::VALUE_FALSE &&
                $values[Fallback::RENDER_TYPE] != Fallback::RENDER_TYPE_NONE
            ) {
                // It's only visible.
                $result = 'display';
            }
        }
        return $result;
    }
}
