<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit;

use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Factory\ErrorListenerFactory;
use Contenir\Errors\Laminas\Mvc\Factory\FileRepositoryFactory;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    public function testInvokeReturnsServiceManagerErrorsAndViewManagerKeys(): void
    {
        $config = (new ConfigProvider())();

        self::assertArrayHasKey('service_manager', $config);
        self::assertArrayHasKey('errors', $config);
        self::assertArrayHasKey('view_manager', $config);
    }

    public function testRegistersRepositoryFactory(): void
    {
        $deps = (new ConfigProvider())->getDependencies();

        self::assertSame(
            FileRepositoryFactory::class,
            $deps['factories'][ErrorPageRepositoryInterface::class]
        );
    }

    public function testRegistersListenerFactory(): void
    {
        $deps = (new ConfigProvider())->getDependencies();

        self::assertSame(
            ErrorListenerFactory::class,
            $deps['factories'][ErrorListener::class]
        );
    }

    public function testErrorsDefaultsHaveExpectedKeys(): void
    {
        $defaults = (new ConfigProvider())->getErrorsDefaults();

        self::assertArrayHasKey('file', $defaults);
        self::assertArrayHasKey('view_template', $defaults);
        self::assertArrayHasKey('logger', $defaults);
    }

    public function testErrorsDefaultsAreUnconfiguredExceptViewTemplate(): void
    {
        $defaults = (new ConfigProvider())->getErrorsDefaults();

        self::assertNull($defaults['file']);
        self::assertNull($defaults['logger']);
        self::assertSame('contenir/errors/fault', $defaults['view_template']);
    }

    public function testViewManagerConfigPointsAtShippedTemplateDirectory(): void
    {
        $vm = (new ConfigProvider())->getViewManagerConfig();

        self::assertArrayHasKey('template_path_stack', $vm);
        self::assertCount(1, $vm['template_path_stack']);
        self::assertDirectoryExists($vm['template_path_stack'][0]);
        self::assertFileExists($vm['template_path_stack'][0] . '/contenir/errors/fault.phtml');
    }

    public function testDefaultViewTemplateConstantMatchesDefaults(): void
    {
        self::assertSame(
            ConfigProvider::DEFAULT_VIEW_TEMPLATE,
            (new ConfigProvider())->getErrorsDefaults()['view_template']
        );
    }
}
