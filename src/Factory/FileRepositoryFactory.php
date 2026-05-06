<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Factory;

use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Repository\FileRepository;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Builds a FileRepository pointed at config['errors']['file'].
 *
 * The file path must be configured by the consuming Site — there's no sensible
 * default, since the path is the contract between the admin (writer) and the
 * Site (reader).
 */
final class FileRepositoryFactory
{
    public function __invoke(ContainerInterface $container): ErrorPageRepositoryInterface
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $path   = $config['errors']['file'] ?? null;

        if (! is_string($path) || $path === '') {
            throw new RuntimeException(
                'contenir/errors-laminas-mvc: config[errors][file] must be a non-empty string'
                . ' pointing at the shared errors file.'
            );
        }

        return new FileRepository($path);
    }
}
