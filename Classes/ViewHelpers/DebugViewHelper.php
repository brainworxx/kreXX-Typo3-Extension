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

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

// The main problem with 7.0 is, that compatibility6 may or may not be installed.
// If not, I have to put his thing here, hoping not to break anything!
if (!class_exists('Tx_Fluid_Core_ViewHelper_AbstractViewHelper')) {
    /**
     * Class Tx_Fluid_Core_ViewHelper_AbstractViewHelper
     */
    abstract class Tx_Fluid_Core_ViewHelper_AbstractViewHelper extends AbstractViewHelper
    {
    }
}
// For some reasons, TYPO3 7.6 manages to load this file multiple times, causing
// a fatal.
if (class_exists('Tx_Includekrexx_ViewHelpers_DebugViewHelper')) {
    return;
}

/**
 * Class Tx_Includekrexx_ViewHelpers_DebugViewHelper
 *
 * @namespace
 *   When using TYPO3 4.5 until 8.4, you need to declare the namespace first:
 *   {namespace krexx=Tx_Includekrexx_ViewHelpers}
 *   TYPO3 8.5 and beyond don't need to do that anymore  ;-)
 *
 * @usage
 *   <krexx:debug>{_all}</krexx:debug>
 *   or
 *   <krexx:debug value="{my: 'value', to: 'analyse'}" />
 *   Use this part if you don't want fluid to escape your string or if you are
 *   stitching together an array.
 */
class Tx_Includekrexx_ViewHelpers_DebugViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper
{

    /**
     * No escaping for the rendered children, we want then as they are.
     *
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * We do not have any output.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * {@inheritdoc}
     */
    public function initializeArguments()
    {
        $this->registerArgument('value', 'mixed', 'The variable we want to analyse.', false);
    }

    /**
     * A wrapper for kreXX();
     *
     * @return string
     *   Returns an empty string.
     */
    public function render()
    {
        Krexx::$pool
            // Registering the alternative getter analysis, without the 'get' in
            // the functionname.
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Callback\\Iterate\\ThroughGetter',
                'Tx_Includekrexx_Rewrite_AnalysisCallbackIterateTroughGetter'
            )
            // Registering the fluid connector class.
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Code\\Connectors',
                'Tx_Includekrexx_Rewrite_ServiceCodeConnectors'
            )
            // Registering the special source generation for methods.
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\Callback\\Iterate\\ThroughMethods',
                'Tx_Includekrexx_Rewrite_AnalyseCallbackIterateThroughMethods'
            )
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Code\\Codegen',
                'Tx_Includekrexx_Rewrite_ServiceCodeCodegen'
            );


        // We need other fluid caller finders, depending on the version.
        // In case that anybody is actually reading this:
        // Right now, I have no idea how stable this is. Both rewrites are kind
        // of hacky, using reflections left and right.
        // The "old" CallerFinder is still arround. So, if anybody is really using
        // this code (and reading it), you may want to rollback to:
//        Krexx::$pool->addRewrite(
//            'Brainworxx\\Krexx\\Analyse\\Caller\\CallerFinder',
//            'Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderNothing'
//        );
        Krexx::$pool->registry->set('DebugViewHelper', $this);

        if (version_compare(TYPO3_version, '8.4', '>')) {
            Krexx::$pool->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Caller\\CallerFinder',
                'Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderFluid'
            );
        } else {
            Krexx::$pool->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Caller\\CallerFinder',
                'Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderFluidOld'
            );
        }

        // Trigger the file loading, which may or may not be done by TYPO3.
        $this->fileLoading();

        $found  = false;
        if (!is_null($this->arguments['value'])) {
            krexx($this->arguments['value']);
            $found = true;
        }

        $children = $this->renderChildren();
        if (!is_null($children)) {
            krexx($children);
            $found = true;
        }

        if (!$found) {
            // Both are NULL, we must tell the dev!
            krexx(null);
        }

        // Reset all rewrites to the global ones.
        Krexx::$pool->flushRewrite();

        return '';
    }

    /**
     * "Autoloading" for files that do not get autoloaded anymore, but are
     * needed for the code above.
     */
    protected function fileLoading()
    {
        static $once = false;
        // We do this only once.
        if ($once) {
            return;
        }

        $once = true;
        if (version_compare(TYPO3_version, '7.2', '>')) {
            $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('includekrexx');

            if (!class_exists('Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderFluid')) {
                include_once($extPath . 'Classes/Rewrite/AnalysisCallerCallerFinderFluid.php');
            }
            if (!class_exists('Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderNothing')) {
                include_once($extPath . 'Classes/Rewrite/AnalysisCallerCallerFinderNothing.php');
            }
            if (!class_exists('Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderFluidOld')) {
                include_once($extPath . 'Classes/Rewrite/AnalysisCallerCallerFinderFluidOld.php');
            }
            if (!class_exists('Tx_Includekrexx_Rewrite_AnalysisCallbackIterateTroughGetter')) {
                include_once($extPath . 'Classes/Rewrite/AnalysisCallbackIterateTroughGetter.php');
            }
            if (!class_exists('Tx_Includekrexx_Rewrite_ServiceCodeConnectors')) {
                include_once($extPath . 'Classes/Rewrite/ServiceCodeConnectors.php');
            }
            if (!class_exists('Tx_Includekrexx_Rewrite_AnalyseCallbackIterateThroughMethods')) {
                include_once($extPath . 'Classes/Rewrite/AnalyseCallbackIterateThroughMethods.php');
            }
            if (!class_exists('Tx_Includekrexx_Rewrite_ServiceCodeCodegen')) {
                include_once($extPath . 'Classes/Rewrite/ServiceCodeCodegen.php');
            }
        }
    }

    /**
     * Getter for the view
     *
     * @return \TYPO3Fluid\Fluid\View\ViewInterface|\TYPO3\CMS\Fluid\View\AbstractTemplateView
     */
    public function getView()
    {
        return $this->viewHelperVariableContainer->getView();
    }

    /**
     * Getter for the rendering context
     *
     * @return \TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface|\TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface
     */
    public function getRenderingContext()
    {
        return $this->renderingContext;
    }
}
