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
 * EVENT_RENDER_ERROR at high priority so it can swap the result ViewModel
 * before the framework's default rendering strategy runs.
 *
 * RENDER fires for both normal dispatches that ended up with a 4xx/5xx status
 * (e.g. controller called setStatusCode(403)) and for DISPATCH_ERROR cases
 * (route mismatch → 404, exception → 500) where the framework's strategy has
 * already prepared an error ViewModel. Hooking RENDER lets us handle every
 * case through one code path.
 */
class Module
{
    public const RENDER_PRIORITY = 100;

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
        $events->attach(MvcEvent::EVENT_RENDER_ERROR, $listener, self::RENDER_PRIORITY);
    }
}
