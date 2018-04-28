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
 *   kreXX Copyright (C) 2014-2018 Brainworxx GmbH
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

namespace Brainworxx\Includekrexx\ViewHelpers;

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use Brainworxx\Krexx\Service\Factory\Pool;

/**
 * Our fluid wraqpper for kreXX.
 *
 * @namespace
 *   When using TYPO3 6.2 until 8.4, you need to declare the namespace first:
 *   {namespace krexx=Brainworxx\Includekrexx\ViewHelpers}
 *   or
 *   <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
 *         xmlns:krexx="http://typo3.org/ns/Brainworxx/Includekrexx/ViewHelpers"
 *         data-namespace-typo3-fluid="true">
 *   TYPO3 8.5 and beyond don't need to do that anymore  ;-)
 *
 * @usage
 *   <krexx:debug>{_all}</krexx:debug>
 *   or
 *   <krexx:debug value="{my: 'value', to: 'analyse'}" />
 *   Use this part if you don't want fluid to escape your string or if you are
 *   stitching together an array.
 */
class DebugViewHelper extends AbstractViewHelper
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
     *
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
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
        Pool::createPool();

        \Krexx::$pool
            // Registering the alternative getter analysis, without the 'get' in
            // the functionname.
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Callback\\Iterate\\ThroughGetter',
                'Brainworxx\\Includekrexx\\Rewrite\\Analyse\\Callback\\Iterate\\ThroughGetter'
            )
            // Registering the fluid connector class.
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Code\\Connectors',
                'Brainworxx\\Includekrexx\\Rewrite\\Service\\Code\\Connectors'
            )
            // Registering the special source generation for methods.
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\Callback\\Iterate\\ThroughMethods',
                'Brainworxx\\Includekrexx\\Rewrite\\Analyse\\Callback\\Iterate\\ThroughMethods'
            )
            ->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Code\\Codegen',
                'Brainworxx\\Includekrexx\\Rewrite\\Service\\Code\\Codegen'
            );


        // We need other fluid caller finders, depending on the version.
        \Krexx::$pool->registry->set('DebugViewHelper', $this);

        if (version_compare(TYPO3_version, '8.4', '>')) {
            \Krexx::$pool->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Caller\\CallerFinder',
                'Brainworxx\\Includekrexx\\Rewrite\\Analyse\\Caller\\CallerFinderFluid'
            );
        } else {
            \Krexx::$pool->addRewrite(
                'Brainworxx\\Krexx\\Analyse\\Caller\\CallerFinder',
                'Brainworxx\\Includekrexx\\Rewrite\\Analyse\\Caller\\CallerFinderFluidOld'
            );
        }

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
        \Krexx::$pool->flushRewrite();

        return '';
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
