<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Laminas\Mvc\Module;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\EventManager\EventManager;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\ApplicationInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ModuleTest extends TestCase
{
    public function testGetConfigReturnsConfigProviderArray(): void
    {
        $config = (new Module())->getConfig();

        self::assertArrayHasKey('service_manager', $config);
        self::assertArrayHasKey('errors', $config);
        self::assertArrayHasKey('view_manager', $config);
    }

    public function testRenderPriorityConstantIsExposed(): void
    {
        self::assertSame(100, Module::RENDER_PRIORITY);
    }

    public function testAttachListenerSwapsViewModelOnRender(): void
    {
        $listener = $this->buildListener();
        $events   = new EventManager();

        (new Module())->attachListener($events, $listener);

        $event = $this->buildEvent(404);
        $event->setName(MvcEvent::EVENT_RENDER);
        $events->triggerEvent($event);

        self::assertSame('contenir/errors/fault', $event->getResult()->getTemplate());
    }

    public function testAttachListenerAlsoFiresOnRenderError(): void
    {
        $listener = $this->buildListener();
        $events   = new EventManager();

        (new Module())->attachListener($events, $listener);

        $event = $this->buildEvent(500);
        $event->setName(MvcEvent::EVENT_RENDER_ERROR);
        $events->triggerEvent($event);

        self::assertInstanceOf(ViewModel::class, $event->getResult());
        self::assertSame('contenir/errors/fault', $event->getResult()->getTemplate());
    }

    public function testOnBootstrapResolvesListenerAndAttachesIt(): void
    {
        $listener = $this->buildListener();
        $services = new ServiceManager(['services' => [ErrorListener::class => $listener]]);
        $events   = new EventManager();

        $application = $this->createMock(ApplicationInterface::class);
        $application->method('getServiceManager')->willReturn($services);
        $application->method('getEventManager')->willReturn($events);

        $bootstrapEvent = new MvcEvent();
        $bootstrapEvent->setApplication($application);

        (new Module())->onBootstrap($bootstrapEvent);

        $renderEvent = $this->buildEvent(404);
        $renderEvent->setName(MvcEvent::EVENT_RENDER);
        $events->triggerEvent($renderEvent);

        self::assertSame('contenir/errors/fault', $renderEvent->getResult()->getTemplate());
    }

    private function buildListener(): ErrorListener
    {
        return new ErrorListener(new InMemoryRepository([
            new ErrorPage(404, 'Not found', '<p>Lost.</p>'),
            new ErrorPage(500, 'Oops', '<p>Sorry.</p>'),
        ]));
    }

    private function buildEvent(int $status): MvcEvent
    {
        $response = new HttpResponse();
        $response->setStatusCode($status);

        $event = new MvcEvent();
        $event->setResponse($response);
        return $event;
    }
}
