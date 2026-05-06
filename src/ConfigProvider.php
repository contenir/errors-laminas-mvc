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
