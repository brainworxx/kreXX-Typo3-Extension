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

namespace Brainworxx\Includekrexx\Tests\Unit\Controller;

use Brainworxx\Includekrexx\Collectors\AbstractCollector;
use Brainworxx\Includekrexx\Collectors\Configuration;
use Brainworxx\Includekrexx\Collectors\FormConfiguration;
use Brainworxx\Includekrexx\Controller\IndexController;
use Brainworxx\Includekrexx\Domain\Model\Settings;
use Brainworxx\Includekrexx\Tests\Helpers\AbstractTest;
use Brainworxx\Includekrexx\Tests\Helpers\FlashMessageQueue;
use Brainworxx\Krexx\Krexx;
use Brainworxx\Krexx\Service\Config\Config;
use StdClass;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\Controller\AbstractController;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Install\Configuration\Context\LivePreset;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class IndexControllerTest extends AbstractTest
{

    /**
     * @var FlashMessageQueue
     */
    protected $flashMessageQueue;

    /**
     * Short circuiting the flash messages.
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Controller\AbstractController $controller
     */
    protected function initFlashMessages(AbstractController $controller)
    {
        $this->flashMessageQueue = new FlashMessageQueue();

        $controllerContextMock = $this->createMock(ControllerContext::class);
        $controllerContextMock->expects($this->any())
            ->method('getFlashMessageQueue')
            ->will($this->returnValue($this->flashMessageQueue));
        $this->setValueByReflection('controllerContext', $controllerContextMock, $controller);
    }

    /**
     * Mock a backend user and inject it.
     */
    protected function mockBeUser()
    {
        $userMock = $this->createMock(BackendUserAuthentication::class);
        $userMock->expects($this->once())
            ->method('check')
            ->with('modules', AbstractCollector::PLUGIN_NAME)
            ->will($this->returnValue(true));

        $GLOBALS['BE_USER'] = $userMock;
    }

    /**
     * The tings you do, to have a simple redirect . . .
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Controller\AbstractController $controller
     */
    protected function prepareRedirect(AbstractController $controller)
    {
        $request = new StdClass();
        $this->setValueByReflection('request', $request, $controller);
    }

    /**
     * Test the index action, without access.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::indexAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::hasAccess
     */
    public function testIndexActionNoAccess()
    {
        $indexController = new IndexController();
        $this->initFlashMessages($indexController);

        $indexController->indexAction();

        $this->assertEquals(
            'accessDenied',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'We did not mock a BE session, hence no access for you!'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), 'No more messages here.');
    }

    /**
     * Normal test of the index action.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::indexAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::hasAccess
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::checkProductiveSetting
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::retrieveKrexxMessages
     */
    public function testIndexActionNormal()
    {
        // Prepare a BE user.
        $this->mockBeUser();

        // Prepare a productive setting.
        $presetMock = $this->createMock(LivePreset::class);
        $presetMock->expects($this->once())
            ->method('isActive')
            ->will($this->returnValue(true));

        // Prepare a message from kreXX.
        $messageFromKrexx = 'some key';
        Krexx::$pool->messages->addMessage($messageFromKrexx);

        // Prepare the model
        $settingsModel = new Settings();

        // Mock the view.
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->expects($this->once())
            ->method('assign')
            ->with('settings', $settingsModel);

        // Prepare the collectors
        $configurationMock = $this->createMock(Configuration::class);
        $configurationMock->expects($this->once())
            ->method('assignData')
            ->with($viewMock);
        $configFeMock = $this->createMock(FormConfiguration::class);
        $configFeMock->expects($this->once())
            ->method('assignData')
            ->with($viewMock);

        // Inject it, like there is no tomorrow.
        $indexController = new IndexController();
        $indexController->injectLivePreset($presetMock);
        $indexController->injectSettings($settingsModel);
        $indexController->injectConfiguration($configurationMock);
        $indexController->injectFormConfiguration($configFeMock);
        $this->initFlashMessages($indexController);
        $this->setValueByReflection('view', $viewMock, $indexController);

        // Run it through like a tunnel on a marathon route.
        $indexController->indexAction();

        // Test for the kreXX messages.
        $this->assertEquals(
            'debugpreset.warning.message',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'Simulation productive settings.'
        );
        $this->assertEquals(
            $messageFromKrexx,
            $this->flashMessageQueue->getMessages()[1]->getMessage(),
            'A message from kreXX'
        );
        $this->assertArrayNotHasKey(2, $this->flashMessageQueue->getMessages(), 'No more messages here.');
    }

    /**
     * Test the redirect when having no access for the save action.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::saveAction
     */
    public function testSaveActionNoAccess()
    {
        $indexController = new IndexController();
        $this->initFlashMessages($indexController);
        $this->prepareRedirect($indexController);

        $settingsModel = new Settings();

        try {
            $indexController->saveAction($settingsModel);
        } catch (UnsupportedRequestTypeException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        }
        $this->assertTrue($exceptionWasThrown, 'We did have an redirect here.');

        $this->assertEquals(
            'accessDenied',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'We did not mock a BE session, hence no access for you!'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), 'No more messages here.');
    }

    /**
     * Testing the saving of the ini file.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::saveAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::retrieveKrexxMessages
     */
    public function testSaveActionNormal()
    {
        $this->mockBeUser();

        $indexController = new IndexController();
        $this->initFlashMessages($indexController);
        $this->prepareRedirect($indexController);

        $iniContent = 'oh joy, even more settings . . .';

        $settingsMock = $this->createMock(Settings::class);
        $settingsMock->expects($this->once())
            ->method('generateIniContent')
            ->will($this->returnValue($iniContent));

        $filePutContentsMock = $this->getFunctionMock('\\Brainworxx\\Includekrexx\\Controller\\', 'file_put_contents');
        $filePutContentsMock->expects($this->once())
            ->with(Krexx::$pool->config->getPathToIniFile(), $iniContent)
            ->will($this->returnValue(true));

        try {
            $indexController->saveAction($settingsMock);
        } catch (UnsupportedRequestTypeException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        }
        $this->assertTrue($exceptionWasThrown, 'We did have an redirect here.');

        $this->assertEquals(
            'save.success.text',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'Expecting the success message here.'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), 'No more messages here.');
    }

    /**
     * Testing the saving of the ini file.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::saveAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::retrieveKrexxMessages
     */
    public function testSaveActionNoWriteAccess()
    {
        $this->mockBeUser();

        $indexController = new IndexController();
        $this->initFlashMessages($indexController);
        $this->prepareRedirect($indexController);

        $iniContent = 'oh joy, even more settings . . .';

        $settingsMock = $this->createMock(Settings::class);
        $settingsMock->expects($this->once())
            ->method('generateIniContent')
            ->will($this->returnValue($iniContent));

        $filePutContentsMock = $this->getFunctionMock('\\Brainworxx\\Includekrexx\\Controller\\', 'file_put_contents');
        $filePutContentsMock->expects($this->once())
            ->with(Krexx::$pool->config->getPathToIniFile(), $iniContent)
            ->will($this->returnValue(false));

        try {
            $indexController->saveAction($settingsMock);
        } catch (UnsupportedRequestTypeException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        }
        $this->assertTrue($exceptionWasThrown, 'We did have an redirect here.');

        $this->assertEquals(
            'file.not.writable',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'Expecting the failure message here.'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), 'No more messages here.');
    }

    /**
     * Testing the dispatching without access.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::dispatchAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::createResponse
     */
    public function testDispatchActionNoAccess()
    {
        $serverRequestMock = $this->createMock(ServerRequest::class);
        $request = [
            'tx_includekrexx_tools_includekrexxkrexxconfiguration' => [
                'id' => 123
            ]
        ];
        $serverRequestMock->expects($this->once())
            ->method('getQueryParams')
            ->will($this->returnValue($request));

        $headerMock = $this->getFunctionMock('\\Brainworxx\\Includekrexx\\Controller\\', 'header');
        $headerMock->expects($this->never());

        // Mocking a class via StdClass. I love this job.
        $nullResponseMock = new StdClass();
        $objectManagerMock = $this->createMock(ObjectManager::class);
        $objectManagerMock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($nullResponseMock));
        $this->injectIntoGeneralUtility(ObjectManager::class, $objectManagerMock);

        $indexController = new IndexController();
        $this->assertSame($nullResponseMock, $indexController->dispatchAction($serverRequestMock));
    }

    /**
     * Testing the normal dispatching of a file.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::dispatchAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::createResponse
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::dispatchFile
     */
    public function testDispatchActionNormal()
    {
        $this->mockBeUser();

        $requestMock = $this->createMock(Request::class);
        $requestMock->expects($this->once())
            ->method('getArgument')
            ->with('id')
            ->will($this->returnValue('123458'));

        // Use the files inside the fixture folder.
        $this->setValueByReflection(
            'directories',
            [Config::LOG_FOLDER =>__DIR__ . '/../../Fixtures/'],
            \Krexx::$pool->config
        );

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('shutdown');

        $objectManagerMock = $this->createMock(ObjectManager::class);
        $objectManagerMock->expects($this->once())
            ->method('get')
            ->with(ResponseInterface::class)
            ->will($this->returnValue($responseMock));

        $controller = new IndexController();
        $this->setValueByReflection('objectManager', $objectManagerMock, $controller);
        $this->setValueByReflection('request', $requestMock, $controller);

        $this->expectOutputString('Et dico vide nec, sed in mazim phaedrum voluptatibus. Eum clita meliore tincidunt ei, sed utinam pertinax theophrastus ad. Porro quodsi detracto ea pri. Et vis mollis voluptaria. Per ut saperet intellegam.');

        // Prevent the dispatcher from doing something stupid.
        $headerMock = $this->getFunctionMock('\\Brainworxx\\Includekrexx\\Controller\\', 'header');
        $headerMock->expects($this->exactly(2));
        $this->getFunctionMock('\\Brainworxx\\Includekrexx\\Controller\\', 'ob_flush');
        $this->getFunctionMock('\\Brainworxx\\Includekrexx\\Controller\\', 'flush');

        $this->assertSame($responseMock, $controller->dispatchAction());
    }
}