<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit\Listener;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Repository\InMemoryRepository;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[Group('unit')]
#[Group('listener')]
final class ErrorListenerTest extends TestCase
{
    public function testNoopWhenResponseIsNotHttpResponse(): void
    {
        $listener = new ErrorListener($this->repoWith404());
        $event    = new MvcEvent();
        $event->setResponse($this->createMock(ResponseInterface::class));

        $listener($event);

        self::assertNull($event->getResult());
    }

    public function testNoopWhenStatusBelow400(): void
    {
        $listener = new ErrorListener($this->repoWith404());
        $event    = $this->eventWith(200);

        $listener($event);

        self::assertNull($event->getResult());
    }

    public function testNoopWhenRepositoryHasNoPageForStatus(): void
    {
        $listener = new ErrorListener(new InMemoryRepository());
        $event    = $this->eventWith(404);

        $listener($event);

        self::assertNull($event->getResult());
    }

    public function testNoopWhenRepositoryPageIsEmpty(): void
    {
        $listener = new ErrorListener(new InMemoryRepository([new ErrorPage(404, '', '')]));
        $event    = $this->eventWith(404);

        $listener($event);

        self::assertNull($event->getResult());
    }

    public function testSwapsViewModelTemplateWhenPageConfigured(): void
    {
        $listener = new ErrorListener($this->repoWith404());
        $event    = $this->eventWith(404);

        $listener($event);

        $result = $event->getResult();
        self::assertInstanceOf(ViewModel::class, $result);
        self::assertSame('contenir/errors/fault', $result->getTemplate());
    }

    public function testInjectsStatusTitleAndBodyVariables(): void
    {
        $listener = new ErrorListener($this->repoWith404());
        $event    = $this->eventWith(404);

        $listener($event);

        $vars = $event->getResult()->getVariables();
        self::assertSame(404, $vars['status']);
        self::assertSame('Not found', $vars['title']);
        self::assertSame('<p>Lost.</p>', $vars['body']);
    }

    public function testMakesViewModelTerminalToBypassLayout(): void
    {
        $listener = new ErrorListener($this->repoWith404());
        $event    = $this->eventWith(404);

        $listener($event);

        self::assertTrue($event->getResult()->terminate());
    }

    public function testReusesExistingViewModelWhenAlreadySet(): void
    {
        $existing = new ViewModel(['preserve_me' => 'yes']);
        $existing->setTemplate('error/index');

        $listener = new ErrorListener($this->repoWith404());
        $event    = $this->eventWith(404);
        $event->setResult($existing);

        $listener($event);

        self::assertSame($existing, $event->getResult());
        self::assertSame('contenir/errors/fault', $existing->getTemplate());
        // pre-existing variables remain (setVariables merges by default).
        self::assertSame('yes', $existing->getVariables()['preserve_me']);
    }

    public function testCreatesViewModelWhenResultIsNotOne(): void
    {
        $listener = new ErrorListener($this->repoWith404());
        $event    = $this->eventWith(404);
        $event->setResult('some non-viewmodel result');

        $listener($event);

        self::assertInstanceOf(ViewModel::class, $event->getResult());
    }

    public function testRespectsConfiguredCustomViewTemplate(): void
    {
        $listener = new ErrorListener(
            repository: $this->repoWith404(),
            viewTemplate: 'site/custom-error',
        );
        $event = $this->eventWith(404);

        $listener($event);

        self::assertSame('site/custom-error', $event->getResult()->getTemplate());
    }

    public function testHandlesAnyConfigured4xxOr5xxStatus(): void
    {
        $repo = new InMemoryRepository([
            new ErrorPage(403, 'Forbidden', '<p>nope</p>'),
            new ErrorPage(500, 'Oops', '<p>sorry</p>'),
        ]);
        $listener = new ErrorListener($repo);

        $forbiddenEvent = $this->eventWith(403);
        $listener($forbiddenEvent);
        self::assertSame('Forbidden', $forbiddenEvent->getResult()->getVariables()['title']);

        $serverEvent = $this->eventWith(500);
        $listener($serverEvent);
        self::assertSame('Oops', $serverEvent->getResult()->getVariables()['title']);
    }

    public function testLoggerNotInvokedWhenAbsent(): void
    {
        // Just confirms the no-logger path doesn't throw.
        $listener = new ErrorListener($this->repoWith404());
        $listener($this->eventWith(404));

        $this->expectNotToPerformAssertions();
    }

    public function testLogsInfoFor4xxResponses(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('HTTP 404 at'));
        $logger->expects($this->never())->method('error');

        $listener = new ErrorListener($this->repoWith404(), logger: $logger);
        $listener($this->eventWith(404, 'http://example.test/missing'));
    }

    public function testLogsErrorFor5xxResponses(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('HTTP 500 at'), self::isType('array'));
        $logger->expects($this->never())->method('info');

        $listener = new ErrorListener(
            repository: new InMemoryRepository([new ErrorPage(500, 'Oops', '<p>sorry</p>')]),
            logger: $logger,
        );
        $listener($this->eventWith(500, 'http://example.test/boom'));
    }

    public function testLogsExceptionContextWhenPresentOnEvent(): void
    {
        $exception = new RuntimeException('database is on fire');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->isType('string'),
                self::callback(static fn (array $ctx): bool => ($ctx['exception'] ?? null) === $exception),
            );

        $listener = new ErrorListener(
            repository: new InMemoryRepository([new ErrorPage(500, 'Oops', '<p>sorry</p>')]),
            logger: $logger,
        );

        $event = $this->eventWith(500);
        $event->setParam('exception', $exception);

        $listener($event);
    }

    public function testLogsEvenWhenNoPageConfigured(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $listener = new ErrorListener(
            repository: new InMemoryRepository(),
            logger: $logger,
        );

        $listener($this->eventWith(404));

        // And no view-model swap.
        // (assertion above is enough; expectations validate on tearDown.)
    }

    public function testSkipsLoggingFor2xxResponses(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method($this->anything());

        $listener = new ErrorListener($this->repoWith404(), logger: $logger);
        $listener($this->eventWith(200));
    }

    public function testDefaultViewTemplateConstantIsExposed(): void
    {
        self::assertSame('contenir/errors/fault', ErrorListener::DEFAULT_VIEW_TEMPLATE);
    }

    public function testHandlesEventWithoutRequestGracefully(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->equalTo('HTTP 404 at '));

        $listener = new ErrorListener($this->repoWith404(), logger: $logger);

        // No request set on the event.
        $response = new HttpResponse();
        $response->setStatusCode(404);
        $event = new MvcEvent();
        $event->setResponse($response);

        $listener($event);
    }

    public function testHandlesRequestThatLacksGetUriMethod(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->equalTo('HTTP 404 at '));

        $listener = new ErrorListener($this->repoWith404(), logger: $logger);

        $response = new HttpResponse();
        $response->setStatusCode(404);
        $event = new MvcEvent();
        $event->setResponse($response);
        $event->setRequest($this->createMock(RequestInterface::class));

        $listener($event);
    }

    private function repoWith404(): InMemoryRepository
    {
        return new InMemoryRepository([new ErrorPage(404, 'Not found', '<p>Lost.</p>')]);
    }

    private function eventWith(int $status, string $uri = 'http://example.test/'): MvcEvent
    {
        $response = new HttpResponse();
        $response->setStatusCode($status);

        $request = new HttpRequest();
        $request->setUri($uri);

        $event = new MvcEvent();
        $event->setResponse($response);
        $event->setRequest($request);
        return $event;
    }
}
