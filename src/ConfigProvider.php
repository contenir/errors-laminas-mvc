<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc;

/**
 * Returns the merged config consumed by Module::getConfig().
 *
 * Kept separate so a Mezzio sibling adapter can later require the same array
 * without reaching into a Laminas MVC Module class.
 */
final class ConfigProvider
{
    public const DEFAULT_VIEW_TEMPLATE = 'contenir/errors/fault';

    /**
     * Built-in default pages used when no admin-authored content is
     * configured for a given status. The factory pre-seeds these into the
     * fallback InMemoryRepository so the listener intercepts unconfigured
     * 4xx/5xx responses with a presentable page out of the box; entries in
     * config[errors][pages] override per-status, and registering an
     * ErrorPageRepositoryInterface service bypasses this layer entirely.
     */
    public const DEFAULT_PAGES = [
        403 => [
            'title' => 'Access denied',
            'body'  => "<p>You don't have permission to view this page.</p>",
        ],
        404 => [
            'title' => 'Page not found',
            'body'  => "<p>We couldn't find the page you were looking for.</p>",
        ],
        500 => [
            'title' => 'Something went wrong',
            'body'  => '<p>An unexpected error occurred. Please try again in a moment.</p>',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'service_manager' => $this->getDependencies(),
            'errors'          => $this->getErrorsDefaults(),
            'view_manager'    => $this->getViewManagerConfig(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                Listener\ErrorListener::class => Factory\ErrorListenerFactory::class,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorsDefaults(): array
    {
        return [
            'view_template' => self::DEFAULT_VIEW_TEMPLATE,
            'logger'        => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewManagerConfig(): array
    {
        return [
            'template_path_stack' => [
                __DIR__ . '/../view',
            ],
        ];
    }
}
