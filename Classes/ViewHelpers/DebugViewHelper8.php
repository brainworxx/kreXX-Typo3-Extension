<?php
/**
 * @file
 *   Messages viewhelper substitute for the FlashMessagesViewHelper
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



// This is so dirty and evil.
// TYPO3 8.0 tries to resolve the old 4.5'er viewhelpers this way.
// If someone reads this and knows how to do this properly, please send
// a mail to:
// tobias.guelzow@brainworxx.de
//
// And dropping 4.5 support is out ouf the question.
namespace Tx_Includekrexx_ViewHelpers;

/**
 * Since we want to render the original Viewhelper, we can extend it directly.
 *
 * Class MessagesViewHelper
 * @package Tx_Includekrexx_ViewHelpers
 */
class DebugViewHelper extends \Tx_Includekrexx_ViewHelpers_DebugViewHelper
{
}