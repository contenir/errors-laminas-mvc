<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Listener;

use Contenir\Errors\ErrorPageRepositoryInterface;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Swaps the result ViewModel with admin-authored content for any 4xx/5xx
 * response that has a configured page in the repository.
 *
 * Listens at RENDER (high priority). By the time RENDER fires, the response
 * status is settled — RouteNotFoundStrategy has set 404, ExceptionStrategy
 * has set 500, or the controller has called setStatusCode(403). One hook
 * handles every path uniformly.
 *
 * When no page is configured, the framework's default rendering proceeds
 * unchanged — the listener is non-invasive on first install.
 *
 * Logging is independent of admin overrides: every intercepted 4xx/5xx is
 * surfaced to the optional PSR-3 logger so Sites can observe error volume
 * regardless of whether they have authored a custom page.
 */
final class ErrorListener
{
    public const DEFAULT_VIEW_TEMPLATE = 'contenir/errors/fault';

    /**
     * Event name used to signal page-cache opt-out. Matches the
     * EVENT_DISABLE constant published by contenir/cache-laminas-mvc.
     * Hardcoded here so this package doesn't take a hard dependency on
     * the cache adapter; if cache-laminas-mvc isn't installed, firing
     * the event is a harmless no-op.
     */
    private const PAGECACHE_DISABLE_EVENT = 'pagecache.disable';

    public function __construct(
        private readonly ErrorPageRepositoryInterface $repository,
        private readonly string $viewTemplate = self::DEFAULT_VIEW_TEMPLATE,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(MvcEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }

        $status = $response->getStatusCode();
        if ($status < 400) {
            return;
        }

        $this->disablePageCache($event);
        $this->log($event, $status);

        $page = $this->repository->get($status);
        if ($page === null || $page->isEmpty()) {
            return;
        }

        $result = $event->getResult();
        $viewModel = $result instanceof ViewModel ? $result : new ViewModel();

        $viewModel->setTemplate($this->viewTemplate);
        $viewModel->setVariables([
            'status' => $status,
            'title'  => $page->title,
            'body'   => $page->body,
        ]);
        $viewModel->setTerminal(true);

        $event->setResult($viewModel);
        $event->setViewModel($viewModel);
    }

    /**
     * Tell any listening page-cache that this 4xx/5xx response must not
     * be stored. cache-laminas-mvc's CacheStrategy listens to this event
     * on the same identifier(s) it uses for dispatch/finish; on receipt
     * it flips its disabled flag and onFinish skips storage.
     */
    private function disablePageCache(MvcEvent $event): void
    {
        $event->getApplication()?->getEventManager()->trigger(self::PAGECACHE_DISABLE_EVENT);
    }

    private function log(MvcEvent $event, int $status): void
    {
        if ($this->logger === null) {
            return;
        }

        $uri = self::extractUri($event);

        if ($status >= 500) {
            $exception = $event->getParam('exception');
            $context   = $exception instanceof Throwable ? ['exception' => $exception] : [];
            $this->logger->error(sprintf('HTTP %d at %s', $status, $uri), $context);
            return;
        }

        $this->logger->info(sprintf('HTTP %d at %s', $status, $uri));
    }

    private static function extractUri(MvcEvent $event): string
    {
        $request = $event->getRequest();
        if ($request !== null && method_exists($request, 'getUri')) {
            return (string) $request->getUri();
        }
        return '';
    }
}
