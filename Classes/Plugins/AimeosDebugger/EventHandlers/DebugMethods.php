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

namespace Brainworxx\Includekrexx\Plugins\AimeosDebugger\EventHandlers;

use Brainworxx\Krexx\Analyse\Callback\AbstractCallback;
use Brainworxx\Krexx\Analyse\Callback\Analyse\Debug;
use Brainworxx\Krexx\Analyse\Code\Connectors;
use Brainworxx\Krexx\Analyse\ConstInterface;
use Brainworxx\Krexx\Analyse\Model;
use Brainworxx\Krexx\Service\Factory\EventHandlerInterface;
use Brainworxx\Krexx\Service\Factory\Pool;

/**
 * Special DebugMethods
 *
 * @package Brainworxx\Includekrexx\Plugins\AimeosDebugger\EventHandlers
 */
class DebugMethods implements EventHandlerInterface, ConstInterface
{

    /**
     * Our pool.
     *
     * @var \Brainworxx\Krexx\Service\Factory\Pool
     */
    protected $pool;

    /**
     * Inject the pool.
     *
     * @param \Brainworxx\Krexx\Service\Factory\Pool $pool
     */
    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * Resolving the possible methods from the decorator pattern.
     *
     * @param AbstractCallback $callback
     *   The original callback.
     * @param \Brainworxx\Krexx\Analyse\Model|null $model
     *   The model, if available, so far.
     *
     * @throws \ReflectionException
     *
     * @return string
     *   The generated markup.
     */
    public function handle(AbstractCallback $callback, Model $model = null)
    {
        $output = '';
        $params = $callback->getParameters();
        /** @var \Brainworxx\Krexx\Service\Reflection\ReflectionClass $reflection */
        $reflection = $params[static::PARAM_REF];
        $data = $reflection->getData();

        // The 2019 version simplified much of the code, hence the configuration
        // handling here.
        $methods = [
            'getRefItems' => [
                // Aimeos 2018
                \Aimeos\MShop\Common\Item\ListRef\Base::class,
                // Aimeos 2019
                \Aimeos\MShop\Common\Item\ListRef\Iface::class
            ],
            'getPropertyItems' => [
                // Aimeos 2018
                \Aimeos\MShop\Product\Item\Iface::class,
                \Aimeos\MShop\Attribute\Item\Iface::class,
                \Aimeos\MShop\Media\Item\Iface::class,
                \Aimeos\MShop\Product\Item\Iface::class,
                // Aimeos 2019
                \Aimeos\MShop\Common\Item\PropertyRef\Iface::class,
            ],
            'getListItems' => [
                // Aimeos 2018 & 2019
                \Aimeos\MShop\Common\Item\ListRef\Iface::class,

            ]
        ];

        foreach ($methods as $method => $classNames) {
            foreach ($classNames as $className) {
                if ($data instanceof $className && $reflection->hasMethod($method)) {
                    $output .= $this->callDebugMethod($data, $method);
                    // We are done with this one. On to the next method.
                    break;
                }
            }
        }
        return $output;
    }

    /**
     * Call the debug method in the object, and render the result.
     *
     * @param object $data
     *   The object we are analysing.
     * @param string $methodName
     *   The debug method name.
     *
     * @return string
     *   The rendered html dom.
     */
    protected function callDebugMethod($data, $methodName)
    {
        $result = $data->$methodName();
        // We are expecting arrays, btw.
        if (empty($result) === false) {
            return $this->pool->render->renderExpandableChild(
                $this->pool->createClass(Model::class)
                    ->setName($methodName)
                    ->setType(static::TYPE_DEBUG_METHOD)
                    ->setNormal(static::UNKNOWN_VALUE)
                    ->setHelpid($methodName)
                    ->setConnectorType(Connectors::METHOD)
                    ->addParameter(static::PARAM_DATA, $result)
                    ->injectCallback(
                        $this->pool->createClass(Debug::class)
                    )
            );
        }

        return '';
    }
}