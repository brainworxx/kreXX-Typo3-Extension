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
 *   kreXX Copyright (C) 2014-2021 Brainworxx GmbH
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

declare(strict_types=1);

namespace Brainworxx\Includekrexx\Domain\Model;

use Brainworxx\Includekrexx\Collectors\AbstractCollector;
use Brainworxx\Includekrexx\Controller\IndexController;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalyseGetter;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalysePrivate;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalysePrivateMethods;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalyseProtected;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalyseProtectedMethods;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalyseScalar;
use Brainworxx\Includekrexx\Domain\Model\Settings\AnalyseTraversable;
use Brainworxx\Includekrexx\Domain\Model\Settings\ArrayCountLimit;
use Brainworxx\Includekrexx\Domain\Model\Settings\DebugMethods;
use Brainworxx\Includekrexx\Domain\Model\Settings\Destination;
use Brainworxx\Includekrexx\Domain\Model\Settings\DetectAjax;
use Brainworxx\Includekrexx\Domain\Model\Settings\Disabled;
use Brainworxx\Includekrexx\Domain\Model\Settings\Iprange;
use Brainworxx\Includekrexx\Domain\Model\Settings\Level;
use Brainworxx\Includekrexx\Domain\Model\Settings\LogFileWriter;
use Brainworxx\Includekrexx\Domain\Model\Settings\MaxCall;
use Brainworxx\Includekrexx\Domain\Model\Settings\Maxfiles;
use Brainworxx\Includekrexx\Domain\Model\Settings\MaxRuntime;
use Brainworxx\Includekrexx\Domain\Model\Settings\MaxStepNumber;
use Brainworxx\Includekrexx\Domain\Model\Settings\MemoryLeft;
use Brainworxx\Includekrexx\Domain\Model\Settings\Skin;
use Brainworxx\Includekrexx\Domain\Model\Settings\UseScopeAnalysis;
use Brainworxx\Krexx\Krexx;
use Brainworxx\Krexx\Service\Factory\Pool;

/**
 * Abusing the TYPO3 attribute mapper, to save our settings.
 *
 * @package Brainworxx\Includekrexx\Domain\Model
 */
class Settings
{
    use Disabled;
    use Iprange;
    use DetectAjax;
    use Skin;
    use Destination;
    use Maxfiles;
    use UseScopeAnalysis;
    use MaxStepNumber;
    use ArrayCountLimit;
    use Level;
    use AnalyseProtected;
    use AnalysePrivate;
    use AnalyseScalar;
    use AnalyseTraversable;
    use AnalyseProtectedMethods;
    use AnalysePrivateMethods;
    use AnalyseGetter;
    use DebugMethods;
    use MaxCall;
    use MaxRuntime;
    use MemoryLeft;
    use LogFileWriter;

    /**
     * @var string
     */
    protected $factory;

    /**
     * @param string $factory
     */
    public function setFactory(string $factory)
    {
        $this->factory = $factory;
    }

    /**
     * We iterate through the fallback array to generate the content of the
     * ini file.
     *
     * @return string
     *   The generated contend of the ini file.
     */
    public function generateIniContent(): string
    {
        Pool::createPool();

        $moduleSettings = [];
        // Process the settings.
        $result = $this->processGroups($moduleSettings) . $this->processFeEditing($moduleSettings);

        /** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $user */
        $user = $GLOBALS['BE_USER'];
        // Save the last settings to the backend user, so we can retrieve it later.
        if (!isset($user->uc[AbstractCollector::MODULE_DATA][IndexController::MODULE_KEY])) {
            $user->uc[AbstractCollector::MODULE_DATA][IndexController::MODULE_KEY] = [];
        }
        $user->uc[AbstractCollector::MODULE_DATA][IndexController::MODULE_KEY] = array_merge(
            $user->uc[AbstractCollector::MODULE_DATA][IndexController::MODULE_KEY],
            $moduleSettings
        );
        $user->writeUC();

        return $result;
    }

    /**
     * Process the normal groups of the ini.
     *
     * @param array $moduleSettings
     *   The module settings. We store these in the user data.
     *
     * @return string
     *   The generated ini content.
     */
    protected function processGroups(array &$moduleSettings): string
    {
        $result = '';
        $validation = Krexx::$pool->config->validation;

        foreach (Krexx::$pool->config->configFallback as $group => $settings) {
            $result .= '[' . $group . ']' . "\n";
            foreach ($settings as $settingName) {
                if (
                    !is_null($this->$settingName) &&
                    $validation->evaluateSetting($group, $settingName, $this->$settingName)
                ) {
                    $result .= $settingName . ' = "' . $this->$settingName . '"'  . "\n";
                    $moduleSettings[$settingName] = $this->$settingName;
                }
            }
        }

        return $result;
    }

    /**
     * Generate the frontend editing part.
     *
     * @param array $moduleSettings
     *   The module settings. We store these in the user data.
     *
     * @return string
     *   The generated ini content.
     */
    protected function processFeEditing(array &$moduleSettings): string
    {
        $result = '[feEditing]' . "\n";
        $allowedValues = ['full', 'display', 'none'];
        foreach (Krexx::$pool->config->feConfigFallback as $settingName => $settings) {
            $settingNameInModel = 'form' . $settingName;
            if (
                $settings['render']['Editable'] === 'true' &&
                in_array($this->$settingNameInModel, $allowedValues)
            ) {
                $result .= $settingName . ' = "' . $this->$settingNameInModel . '"'  . "\n";
                $moduleSettings[$settingNameInModel] = $this->$settingNameInModel;
            }
        }

        return $result;
    }
}
