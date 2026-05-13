<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc;

use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;

/**
 * Laminas MVC entry point.
 *
 * On bootstrap, attaches the ErrorListener to MvcEvent::EVENT_RENDER and
 * EVENT_RENDER_ERROR so it can swap the result ViewModel before the
 * framework's default rendering strategy runs.
 *
 * Two different priorities are used because the two events have different
 * preconditions:
 *
 *  - EVENT_RENDER fires once per render. By this point any dispatch-time
 *    status (404 from RouteNotFoundStrategy, controller-set 403, etc.) is
 *    already on the response, so the listener can run high (priority 100)
 *    and intercept ahead of DefaultRenderingStrategy (priority -10000).
 *
 *  - EVENT_RENDER_ERROR fires when a render attempt threw. Laminas's
 *    ExceptionStrategy is attached at priority 1 and is the listener that
 *    actually sets the response status to 500. If we ran at 100 here we
 *    would inspect the status *before* ExceptionStrategy had set it, see
 *    200, and bail. We therefore attach at -100 — below ExceptionStrategy
 *    but above DefaultRenderingStrategy — so the status is 500 by the time
 *    we look.
 */
class Module
{
    public const RENDER_PRIORITY       = 100;
    public const RENDER_ERROR_PRIORITY = -100;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return (new ConfigProvider())();
    }

    public function onBootstrap(MvcEvent $event): void
    {
        $application = $event->getApplication();
        $listener    = $application->getServiceManager()->get(ErrorListener::class);
        $this->attachListener($application->getEventManager(), $listener);
    }

    public function attachListener(EventManagerInterface $events, ErrorListener $listener): void
    {
        $events->attach(MvcEvent::EVENT_RENDER, $listener, self::RENDER_PRIORITY);
        $events->attach(MvcEvent::EVENT_RENDER_ERROR, $listener, self::RENDER_ERROR_PRIORITY);
    }
}
