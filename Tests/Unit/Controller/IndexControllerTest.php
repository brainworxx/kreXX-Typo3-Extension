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

namespace Brainworxx\Includekrexx\Tests\Unit\Controller;

use Brainworxx\Includekrexx\Collectors\Configuration;
use Brainworxx\Includekrexx\Collectors\FormConfiguration;
use Brainworxx\Includekrexx\Controller\IndexController;
use Brainworxx\Includekrexx\Domain\Model\Settings;
use Brainworxx\Includekrexx\Tests\Helpers\AbstractTest;
use Brainworxx\Krexx\Krexx;
use Brainworxx\Krexx\Service\Config\Config;
use StdClass;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Install\Configuration\Context\LivePreset;
use TYPO3\CMS\Extbase\Mvc\Response;

class IndexControllerTest extends AbstractTest
{
    const NO_MORE_MESSAGES = 'No more messages here.';
    const CONTROLLER_NAMESPACE = '\\Brainworxx\\Includekrexx\\Controller\\';
    const REDIRECT_MESSAGE = 'We did have an redirect here.';

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
        if (method_exists($indexController, 'injectResponseFactory')) {
            $indexController->injectResponseFactory(new ResponseFactory());
        }
        $indexController->indexAction();

        $this->assertEquals(
            'accessDenied',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'We did not mock a BE session, hence no access for you!'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), static::NO_MORE_MESSAGES);
    }

    /**
     * Normal test of the index action.
     *
     * @covers \Brainworxx\Includekrexx\Controller\IndexController::indexAction
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::hasAccess
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::checkProductiveSetting
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::retrieveKrexxMessages
     * @covers \Brainworxx\Includekrexx\Controller\AbstractController::assignCssJs
     */
    public function testIndexActionNormal()
    {
        $jsCssFileContent = 'file content';
        $fileGetContents =  $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'file_get_contents');
        $fileGetContents->expects($this->exactly(2))
            ->will($this->returnValue($jsCssFileContent));

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
        $viewMock->expects($this->exactly(3))
            ->method('assign')
            ->withConsecutive(
                ['settings', $settingsModel],
                ['js', $jsCssFileContent],
                ['css', $jsCssFileContent]
            );

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
        $indexController->injectSettingsModel($settingsModel);
        $indexController->injectConfiguration($configurationMock);
        $indexController->injectFormConfiguration($configFeMock);
        if (method_exists($indexController, 'injectResponseFactory')) {
            $indexController->injectResponseFactory(new ResponseFactory());
        }
        $this->initFlashMessages($indexController);
        $this->setValueByReflection('view', $viewMock, $indexController);

        // Run it through like a tunnel on a marathon route.
        $this->simulatePackage('includekrexx', 'includekrexx/');
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
        $this->assertArrayNotHasKey(2, $this->flashMessageQueue->getMessages(), static::NO_MORE_MESSAGES);
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
        } catch (StopActionException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        }
        $this->assertTrue($exceptionWasThrown, static::REDIRECT_MESSAGE);

        $this->assertEquals(
            'accessDenied',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'We did not mock a BE session, hence no access for you!'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), static::NO_MORE_MESSAGES);
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

        $filePutContentsMock = $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'file_put_contents');
        $filePutContentsMock->expects($this->once())
            ->with(Krexx::$pool->config->getPathToIniFile(), $iniContent)
            ->will($this->returnValue(true));

        try {
            $indexController->saveAction($settingsMock);
        } catch (UnsupportedRequestTypeException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        } catch (StopActionException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        }
        $this->assertTrue($exceptionWasThrown, static::REDIRECT_MESSAGE);

        $this->assertEquals(
            'save.success.text',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'Expecting the success message here.'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), static::NO_MORE_MESSAGES);
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

        $filePutContentsMock = $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'file_put_contents');
        $filePutContentsMock->expects($this->once())
            ->with(Krexx::$pool->config->getPathToIniFile(), $iniContent)
            ->will($this->returnValue(false));

        try {
            $indexController->saveAction($settingsMock);
        } catch (UnsupportedRequestTypeException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        } catch (StopActionException $e) {
            // We expect this one.
            $exceptionWasThrown = true;
        }

        $this->assertTrue($exceptionWasThrown, static::REDIRECT_MESSAGE);

        $this->assertEquals(
            'file.not.writable',
            $this->flashMessageQueue->getMessages()[0]->getMessage(),
            'Expecting the failure message here.'
        );
        $this->assertArrayNotHasKey(1, $this->flashMessageQueue->getMessages(), static::NO_MORE_MESSAGES);
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

        $headerMock = $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'header');
        $headerMock->expects($this->never());

        $indexController = new IndexController();
        if (class_exists(Response::class) === true) {
            $responseMock = $this->createMock(Response::class);
            $responseMock->expects($this->any())
                ->method('shutdown');
            $this->setValueByReflection('response', $responseMock, $indexController);
        }

        $indexController->dispatchAction($serverRequestMock);
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
            [Config::LOG_FOLDER => __DIR__ . '/../../Fixtures/'],
            \Krexx::$pool->config
        );

        $controller = new IndexController();
        $this->setValueByReflection('request', $requestMock, $controller);
        $this->expectOutputString('Et dico vide nec, sed in mazim phaedrum voluptatibus. Eum clita meliore tincidunt ei, sed utinam pertinax theophrastus ad. Porro quodsi detracto ea pri. Et vis mollis voluptaria. Per ut saperet intellegam.');

        // Prevent the dispatcher from doing something stupid.
        $headerMock = $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'header');
        $headerMock->expects($this->exactly(2));
        $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'ob_flush');
        $this->getFunctionMock(static::CONTROLLER_NAMESPACE, 'flush');

        if (class_exists(Response::class) === true) {
            $responseMock = $this->createMock(Response::class);
            $responseMock->expects($this->any())->method('shutdown');
            $this->setValueByReflection('response', $responseMock, $controller);
        }

        $controller->dispatchAction();
    }
}
