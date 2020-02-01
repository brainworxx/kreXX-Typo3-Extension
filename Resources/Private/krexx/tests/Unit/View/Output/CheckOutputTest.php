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

namespace Brainworxx\Krexx\Tests\Unit\View\Output;

use Brainworxx\Krexx\Krexx;
use Brainworxx\Krexx\Service\Factory\Pool;
use Brainworxx\Krexx\Tests\Helpers\AbstractTest;
use Brainworxx\Krexx\View\Output\CheckOutput;

class CheckOutputTest extends AbstractTest
{
    /**
     * Prevent the mocking of a browser output.
     *
     * {@inheritDoc}
     */
    protected function setUp()
    {
        Pool::createPool();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        parent::tearDown();

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        unset($_SERVER[CheckOutput::REMOTE_ADDRESS]);
    }

    /**
     * Test the setting of the pool.
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::__construct
     */
    public function testConstruct()
    {
        $checkOutput = new CheckOutput(Krexx::$pool);
        $this->assertSame(Krexx::$pool, $this->retrieveValueByReflection('pool', $checkOutput));
    }

    /**
     * Test the ajax detection.
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::isAjax
     */
    public function testIsAjax()
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

        $checkOutput = new CheckOutput(Krexx::$pool);
        $this->assertTrue($checkOutput->isAjax());

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse($checkOutput->isAjax());
    }

    /**
     * Test the cli detection, with a cli mock
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::isCli
     */
    public function testIsCliCli()
    {
        $sapiMock = $this->getFunctionMock('\\Brainworxx\\Krexx\\View\\Output\\', 'php_sapi_name');
        $sapiMock->expects($this->once())
            ->will($this->returnValue('cli'));

        $checkOutput = new CheckOutput(Krexx::$pool);
        $this->assertTrue($checkOutput->isCli());
    }

    /**
     * Test the cli detection with something else  mock.
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::isCli
     */
    public function testIsCliOther()
    {
        $sapiMock = $this->getFunctionMock('\\Brainworxx\\Krexx\\View\\Output\\', 'php_sapi_name');
        $sapiMock->expects($this->once())
            ->will($this->returnValue('not cli'));

        $checkOutput = new CheckOutput(Krexx::$pool);
        $this->assertFalse($checkOutput->isCli());
    }

    /**
     * Test the detection of already send HTML output.
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::isOutputHtml
     */
    public function testIsOutputHtmlHtml()
    {
        $headerMock = $this->getFunctionMock('\\Brainworxx\\Krexx\\View\\Output\\', 'headers_list');
        $headerMock->expects($this->once())
            ->will($this->returnValue(['whatever: some header', 'content-type: html']));

        $checkOutput = new CheckOutput(Krexx::$pool);
        $this->assertTrue($checkOutput->isOutputHtml());
    }

    /**
     * Test the detection of already send PDF output.
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::isOutputHtml
     */
    public function testIsOutputHtmlPdf()
    {
        $headerMock = $this->getFunctionMock('\\Brainworxx\\Krexx\\View\\Output\\', 'headers_list');
        $headerMock->expects($this->once())
            ->will($this->returnValue(['whatever: some header', 'Content-type:application/pdf']));

        $checkOutput = new CheckOutput(Krexx::$pool);
        $this->assertFalse($checkOutput->isOutputHtml());
    }

    /**
     * Test if the remote address is allowed to trigger kreXX
     *
     * @covers \Brainworxx\Krexx\View\Output\CheckOutput::isAllowedIp
     */
    public function testIsAllowedIp()
    {
        // Disable CLI mode.
        $sapiMock = $this->getFunctionMock('\\Brainworxx\\Krexx\\View\\Output\\', 'php_sapi_name');
        $sapiMock->expects($this->any())
            ->will($this->returnValue('browser'));
        $_SERVER[CheckOutput::REMOTE_ADDRESS] = '1.2.3.4';
        $checkOutput = new CheckOutput(Krexx::$pool);


        $whitelist = '*';
        $this->assertTrue($checkOutput->isAllowedIp($whitelist), 'Allow everything.');

        $whitelist = '1.2.3.4';
        $this->assertTrue($checkOutput->isAllowedIp($whitelist), 'Current IP is in whitelist');

        $whitelist = '1.2.*';
        $this->assertTrue($checkOutput->isAllowedIp($whitelist), 'Current IP is in wildcards whitelist');

        $whitelist = '1.5.3.4';
        $this->assertFalse($checkOutput->isAllowedIp($whitelist), 'Wrong IP.');

        $whitelist = '1.5.*';
        $this->assertFalse($checkOutput->isAllowedIp($whitelist), 'Wrong IP with wildcards.');

        $whitelist = '1.5.*, 1.6.*, 1.2.*';
        $this->assertTrue($checkOutput->isAllowedIp($whitelist), 'Alowed IP ranges.');

        $whitelist = '1.5.*, 1.6.*, 1.7.*';
        $this->assertFalse($checkOutput->isAllowedIp($whitelist), 'Wrong IP ranges.');

        unset($_SERVER[CheckOutput::REMOTE_ADDRESS]);
        $whitelist = '1.5.*, 1.6.*, 1.7.*';
        $this->assertFalse(
            $checkOutput->isAllowedIp($whitelist),
            'Someone messed with the server array. We disable this one, just in case.'
        );

        $whitelist = '*';
        $this->assertTrue($checkOutput->isAllowedIp($whitelist), 'Allow everything, with a messed up server array.');
    }
}
