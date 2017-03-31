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

use Brainworxx\Krexx\Analyse\Caller\AbstractCaller;

/**
 * "Caller finder" for fluid, without the ability to find anything.
 *
 * I tried to get this stable, using a lot of reflections to get access to
 * what was actually rendered.
 * Providing a solution from 4.5 till 8.6 proved to be error prone, and before
 * knowingly adding another potential showstopper, we decided to add nothing.
 * Not to mention that there are probably other showstoppers in includekrexx
 * that we have no idea of.
 */
class Tx_Includekrexx_Rewrite_AnalysisCallerCallerFinderFluid extends AbstractCaller
{
    /**
     * Find nothing.
     *
     * {@inheritdoc}
     */
    public function findCaller()
    {
        return array(
            'file' => 'filename is not available in fluid',
            'line' => 'n/a',
            'varname' => 'fluidvar',
        );

    }
}
