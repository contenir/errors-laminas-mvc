<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit\Factory;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Factory\ErrorListenerFactory;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Tests\Unit\Factory\Stub\ArrayContainer;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

#[Group('unit')]
#[Group('factory')]
final class ErrorListenerFactoryTest extends TestCase
{
    public function testBuildsListenerWithRepositoryFromContainer(): void
    {
        $container = $this->container([], $this->repo());

        $listener = (new ErrorListenerFactory())($container);

        self::assertInstanceOf(ErrorListener::class, $listener);
    }

    public function testFallsBackToDefaultViewTemplateWhenConfigOmitsIt(): void
    {
        $container = $this->container([], $this->repo());

        $listener = (new ErrorListenerFactory())($container);

        $event = $this->eventWith(404);
        $listener($event);
        self::assertSame('contenir/errors/fault', $event->getResult()->getTemplate());
    }

    public function testAppliesConfiguredViewTemplate(): void
    {
        $container = $this->container(['view_template' => 'site/custom-error'], $this->repo());

        $listener = (new ErrorListenerFactory())($container);

        $event = $this->eventWith(404);
        $listener($event);
        self::assertSame('site/custom-error', $event->getResult()->getTemplate());
    }

    public function testWiresLoggerInstanceDirectly(): void
    {
        $logger = new NullLogger();
        $container = $this->container(['logger' => $logger], $this->repo());

        // No throw, listener built.
        $listener = (new ErrorListenerFactory())($container);

        self::assertInstanceOf(ErrorListener::class, $listener);
    }

    public function testResolvesLoggerByServiceId(): void
    {
        $logger = new NullLogger();
        $container = new ArrayContainer([
            'config' => ['errors' => ['logger' => 'log.psr3']],
            ErrorPageRepositoryInterface::class => $this->repo(),
            'log.psr3' => $logger,
        ]);

        $listener = (new ErrorListenerFactory())($container);

        self::assertInstanceOf(ErrorListener::class, $listener);
    }

    public function testThrowsWhenResolvedLoggerServiceDoesNotImplementLoggerInterface(): void
    {
        $container = new ArrayContainer([
            'config' => ['errors' => ['logger' => 'not-a-logger']],
            ErrorPageRepositoryInterface::class => $this->repo(),
            'not-a-logger' => new \stdClass(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement Psr\Log\LoggerInterface');

        (new ErrorListenerFactory())($container);
    }

    public function testThrowsWhenLoggerConfigIsInvalidType(): void
    {
        $container = $this->container(['logger' => 42], $this->repo());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('config[errors][logger]');

        (new ErrorListenerFactory())($container);
    }

    public function testThrowsWhenLoggerConfigIsEmptyString(): void
    {
        $container = $this->container(['logger' => ''], $this->repo());

        $this->expectException(RuntimeException::class);

        (new ErrorListenerFactory())($container);
    }

    public function testWorksWhenContainerHasNoConfig(): void
    {
        $container = new ArrayContainer([
            ErrorPageRepositoryInterface::class => $this->repo(),
        ]);

        $listener = (new ErrorListenerFactory())($container);

        self::assertInstanceOf(ErrorListener::class, $listener);
    }

    public function testBuildsInMemoryRepositoryFromConfigPagesWhenNoServiceRegistered(): void
    {
        // Site doesn't register ErrorPageRepositoryInterface — factory falls
        // back to building one from the merged config (Laminas auto-merges
        // config/autoload/errors.local.php into $config['errors']['pages']).
        $container = new ArrayContainer([
            'config' => [
                'errors' => [
                    'pages' => [
                        404 => ['title' => 'Lost', 'body' => '<p>x</p>'],
                        500 => ['title' => 'Boom', 'body' => ''],
                    ],
                ],
            ],
        ]);

        $listener = (new ErrorListenerFactory())($container);
        $event    = $this->eventWith(404);
        $listener($event);

        self::assertSame('Lost', $event->getResult()->getVariable('title'));
        self::assertSame('<p>x</p>', $event->getResult()->getVariable('body'));
    }

    public function testFallbackRepositoryDoesNotInterceptStatusOutsideDefaultsAndConfig(): void
    {
        // 418 isn't in ConfigProvider::DEFAULT_PAGES and isn't admin-configured.
        $container = new ArrayContainer([
            'config' => [
                'errors' => [
                    'pages' => [
                        404 => ['title' => 'Lost', 'body' => ''],
                    ],
                ],
            ],
        ]);

        $listener = (new ErrorListenerFactory())($container);
        $event    = $this->eventWith(418);
        $listener($event);

        self::assertNull($event->getResult(), 'Statuses outside seeded defaults and admin config pass through.');
    }

    public function testFallbackRepositorySeedsBuiltInDefaultsWhenNoAdminPagesConfigured(): void
    {
        // No admin pages at all — the listener should still render the
        // package default for any status in ConfigProvider::DEFAULT_PAGES.
        $container = new ArrayContainer(['config' => ['errors' => []]]);

        $listener = (new ErrorListenerFactory())($container);
        $event    = $this->eventWith(404);
        $listener($event);

        self::assertSame(
            ConfigProvider::DEFAULT_PAGES[404]['title'],
            $event->getResult()->getVariable('title'),
        );
        self::assertSame(
            ConfigProvider::DEFAULT_PAGES[404]['body'],
            $event->getResult()->getVariable('body'),
        );
    }

    public function testAdminPagesOverridePackageDefaultsPerStatus(): void
    {
        // 404 is admin-configured (should win); 500 is left to defaults.
        $container = new ArrayContainer([
            'config' => [
                'errors' => [
                    'pages' => [
                        404 => ['title' => 'Site 404', 'body' => '<p>site</p>'],
                    ],
                ],
            ],
        ]);

        $listener = (new ErrorListenerFactory())($container);

        $event404 = $this->eventWith(404);
        $listener($event404);
        self::assertSame('Site 404', $event404->getResult()->getVariable('title'));

        $event500 = $this->eventWith(500);
        $listener($event500);
        self::assertSame(
            ConfigProvider::DEFAULT_PAGES[500]['title'],
            $event500->getResult()->getVariable('title'),
        );
    }

    public function testFallbackRepositorySkipsRowsWithNonIntegerKeys(): void
    {
        $container = new ArrayContainer([
            'config' => [
                'errors' => [
                    'pages' => [
                        'oops'  => ['title' => 'Bad', 'body' => ''],
                        404 => ['title' => 'Lost', 'body' => ''],
                    ],
                ],
            ],
        ]);

        $listener = (new ErrorListenerFactory())($container);
        $event    = $this->eventWith(404);
        $listener($event);

        self::assertSame('Lost', $event->getResult()->getVariable('title'));
    }

    public function testCustomLoggerInstanceReceivesLogCalls(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $container = $this->container(['logger' => $logger], $this->repo());

        $listener = (new ErrorListenerFactory())($container);
        $listener($this->eventWith(404));
    }

    private function repo(): InMemoryRepository
    {
        return new InMemoryRepository([new ErrorPage(404, 'Not found', '<p>Lost.</p>')]);
    }

    /**
     * @param array<string, mixed> $errorsConfig
     */
    private function container(array $errorsConfig, InMemoryRepository $repo): ArrayContainer
    {
        return new ArrayContainer([
            'config' => ['errors' => $errorsConfig],
            ErrorPageRepositoryInterface::class => $repo,
        ]);
    }

    private function eventWith(int $status): MvcEvent
    {
        $response = new HttpResponse();
        $response->setStatusCode($status);

        $request = new HttpRequest();
        $request->setUri('http://example.test/');

        $event = new MvcEvent();
        $event->setResponse($response);
        $event->setRequest($request);
        return $event;
    }
}
