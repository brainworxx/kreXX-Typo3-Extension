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

namespace Brainworxx\Krexx\View\Skins\SmokyGrey;

use Brainworxx\Krexx\Analyse\Model;

/**
 * Trait SingleChild
 * @deprecated
 *   Since 4.0.0. Use ExpandableChild in stead.
 *
 * @codeCoverageIgnore
 *   We will not test deprecated code.
 *
 * @package Brainworxx\Krexx\View\Skins\SmokyGrey
 */
trait SingleChild
{
    private $markerSingleChild = [
        '{language}',
        '{addjson}'
    ];

    /**
     * {@inheritDoc}
     *
     * @deprecated
     *   Since 4.0.0. Use renderExpandableChild instead.
     *
     * @codeCoverageIgnore
     *   We will not test deprecated methods.
     */
    public function renderSingleChild(Model $model): string
    {
        // We need to fetch the parent stuff first, because the str_replace
        // works through its parameters from left to right. This means in this
        // context, that we need to do the code generation first by fetching
        // the parent, and then adding the help stuff here.
        // And no, we do not do the code generation twice to avoid fetching
        // the parentStuff in a local variable. (Not to mention code duplication
        // by simply copying the parent method.)
        $parentStuff = parent::renderSingleChild($model);

        // Replace the source button and set the json.
        return str_replace(
            $this->markerSingleChild,
            [
                $model->getConnectorLanguage(),
                $this->generateDataAttribute(static::DATA_ATTRIBUTE_JSON, $this->encodeJson($model->getJson()))
            ],
            $parentStuff
        );
    }

    /**
     * Getter of the single child for unit tests.
     *
     * @codeCoverageIgnore
     *   We are not testing the unit tests.
     *
     * @return array
     *   The marker array.
     */
    public function getMarkerSingleChild(): array
    {
        return $this->markerSingleChild;
    }
}
