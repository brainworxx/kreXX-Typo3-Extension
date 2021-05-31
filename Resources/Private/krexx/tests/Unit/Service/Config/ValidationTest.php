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

namespace Brainworxx\Krexx\Tests\Unit\Service\Config;

use Brainworxx\Krexx\Krexx;
use Brainworxx\Krexx\Service\Config\Fallback;
use Brainworxx\Krexx\Service\Config\Validation;
use Brainworxx\Krexx\Service\Plugin\NewSetting;
use Brainworxx\Krexx\Service\Plugin\Registration;
use Brainworxx\Krexx\Tests\Helpers\AbstractTest;
use ReflectionType;
use ReflectionGenerator;
use Reflector;
use stdClass;
use SplObjectStorage;

class ValidationTest extends AbstractTest
{

    const WHATEVER = 'whatever';

    /**
     * Testing the setting of the pool and the merging of the method blacklist
     *
     * @covers \Brainworxx\Krexx\Service\Config\Validation::__construct
     */
    public function testConstruct()
    {
        $className = 'someClass';
        $anotherClassName = 'anotherClass';
        $methodName = 'someMethod';
        $anotherMethodName = 'anotherMethod';

        Registration::addMethodToDebugBlacklist($className, $methodName);
        Registration::addMethodToDebugBlacklist($anotherClassName, $anotherMethodName);
        Registration::addClassToDebugBlacklist($className);
        Registration::addClassToDebugBlacklist($anotherClassName);

        $validation = new Validation(Krexx::$pool);

        $this->assertSame(Krexx::$pool, $this->retrieveValueByReflection('pool', $validation));
        $this->assertEquals(
            [$className => [$methodName], $anotherClassName => [$anotherMethodName]],
            $this->retrieveValueByReflection('methodBlacklist', $validation)
        );
        $this->assertEquals(
            [
                ReflectionType::class,
                ReflectionGenerator::class,
                Reflector::class,
                $className,
                $anotherClassName
            ],
            $this->retrieveValueByReflection('classBlacklist', $validation)
        );
    }

    /**
     * Testing, if a specific class is blacklisted for debug methods.
     *
     * @covers \Brainworxx\Krexx\Service\Config\Validation::isAllowedDebugCall
     */
    public function testIsAllowedDebugCall()
    {
        Registration::addClassToDebugBlacklist(stdClass::class);
        Registration::addMethodToDebugBlacklist(SplObjectStorage::class, 'readMailRealFast');

        $validation = new Validation(Krexx::$pool);
        $stdClass = new stdClass();
        $objectStorage = new SplObjectStorage();

        $this->assertFalse($validation->isAllowedDebugCall($stdClass, ''));
        $this->assertTrue($validation->isAllowedDebugCall($validation, ''));
        $this->assertFalse($validation->isAllowedDebugCall($objectStorage, 'readMailRealFast'));
        $this->assertTrue($validation->isAllowedDebugCall($validation, 'someMethod'));
    }

    /**
     * Testing the validation of settings.
     *
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evaluateSetting
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalBool
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalDebugMethods
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalDestination
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalInt
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalIpRange
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalMaxRuntime
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evalSkin
     */
    public function testEvaluateSetting()
    {
        $iniGet = $this->getFunctionMock('\\Brainworxx\\Krexx\\Service\\Config\\', 'ini_get');
        $iniGet->expects($this->exactly(2))
            ->with('max_execution_time')
            ->will($this->returnValue('123'));

        Registration::addMethodToDebugBlacklist('forbiddenclass', 'forbiddenOne');
        $validation = new Validation(Krexx::$pool);

        // Disallowed frontend editing settings.
        $disallowedSettings = $this->retrieveValueByReflection('feConfigNoEdit', $validation);
        foreach ($disallowedSettings as $settingName) {
            $this->assertFalse($validation->evaluateSetting($validation::SECTION_FE_EDITING, $settingName, static::WHATEVER));
        }

        // Testing each config with a valid value and wist garbage.
        $settingList = $this->retrieveValueByReflection('feConfigFallback', $validation);
        $testData = [
            Fallback::EVAL_BOOL => [
                'true' => true,
                'false' => true,
                static::WHATEVER => false
            ],
            Fallback::EVAL_DEBUG_METHODS => [
                'method1,method2' => true,
                'method 1,method2' => false,
            ],
            Fallback::EVAL_INT => [
                '5' => true,
                'five' => false
            ],
            Fallback::EVAL_DESTINATION => [
                'browser' => true,
                'file' => true,
                'nowhere' => false
            ],
            Fallback::EVAL_SKIN => [
                'hans' => true,
                'smokygrey' => true,
                'bernd' => false
            ],
            Fallback::EVAL_IP_RANGE => [
                'some values' => true,
                '' => false
            ],
            Fallback::EVAL_MAX_RUNTIME => [
                'seven' => false,
                '42' => true,
                '99999' => false
            ]
        ];

        // Nice, huh?
        foreach ($settingList as $name => $setting) {
            foreach ($testData[$setting[$validation::EVALUATE]] as $value => $expected) {
                $this->assertEquals(
                    $expected,
                    $validation->evaluateSetting(
                        $setting[$validation::SECTION],
                        $name,
                        $value
                    ),
                    'name: "' . $name . '", test value: "' . $value .
                    '", validation method: "' . $setting[$validation::EVALUATE] . '"'
                );
            }
        }

        // Two special tests for booleans, which can not be array keys.
        $this->assertEquals(
            true,
            $validation->evaluateSetting('some group', Fallback::SETTING_DISABLED, true)
        );
        $this->assertEquals(
            true,
            $validation->evaluateSetting('some group', Fallback::SETTING_DISABLED, false)
        );
    }

    /**
     * Crete a custom setting, and then evaluate it.
     *
     * @covers \Brainworxx\Krexx\Service\Config\Validation::evaluateSetting
     */
    public function testEvaluateSettingCustom()
    {
        $settingName = 'editableBoolean';
        $sectionName = 'someWhere';

        $customSetting = new NewSetting();
        $customSetting->setName($settingName)
            ->setValidation($customSetting::EVAL_BOOL)
            ->setSection($sectionName)
            ->setRenderType(NewSetting::RENDER_TYPE_SELECT)
            ->setIsEditable(true)
            ->setDefaultValue('true')
            ->setIsFeProtected(false);
        Registration::addNewSettings($customSetting);

        $anotherSettingName = 'notEditableInput';
        $customSetting = new NewSetting();
        $customSetting->setName($anotherSettingName)
            ->setValidation($customSetting::EVAL_DEBUG_METHODS)
            ->setSection($sectionName)
            ->setRenderType(NewSetting::RENDER_TYPE_INPUT)
            ->setIsEditable(false)
            ->setDefaultValue('true')
            ->setIsFeProtected(true);
        Registration::addNewSettings($customSetting);

        $callbackSettingName = 'callbackSetting';
        $callback = function ($value) {
            return $value === static::WHATEVER;
        };
        $callbackSetting = new NewSetting();
        $callbackSetting->setName($callbackSettingName)
            ->setValidation($callback)
            ->setSection($sectionName)
            ->setRenderType(NewSetting::RENDER_TYPE_NONE)
            ->setIsEditable(true)
            ->setDefaultValue(static::WHATEVER)
            ->setIsFeProtected(false);
        Registration::addNewSettings($callbackSetting);

        $validation = new Validation(Krexx::$pool);

        $this->assertTrue(
            $validation->evaluateSetting($sectionName, $settingName, false),
            'Simple, editable boolean.'
        );
        $this->assertFalse(
            $validation->evaluateSetting($validation::SECTION_FE_EDITING, $anotherSettingName, 'Barf!'),
            'Test the cookie editing. It is protected and must fail.'
        );
        $this->assertFalse(
            $validation->evaluateSetting($sectionName, $callbackSettingName, 'nothing to see here.'),
            'Test the usage of the callback. Wrong value here.'
        );
        $this->assertTrue(
            $validation->evaluateSetting($sectionName, $callbackSettingName, static::WHATEVER),
            'Test the usage of the callback. Right'
        );
    }
}
