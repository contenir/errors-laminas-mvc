<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Factory;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
use Contenir\Errors\Repository\InMemoryRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ErrorListenerFactory
{
    public function __invoke(ContainerInterface $container): ErrorListener
    {
        $config   = $container->has('config') ? $container->get('config') : [];
        $defaults = (new ConfigProvider())->getErrorsDefaults();
        $errors   = ($config['errors'] ?? []) + $defaults;

        return new ErrorListener(
            repository: $this->resolveRepository($container, $errors),
            viewTemplate: (string) $errors['view_template'],
            logger: $this->resolveLogger($container, $errors['logger']),
        );
    }

    /**
     * Build an in-memory repository from `config[errors][pages]` (the merged
     * Laminas config). The data file written by admin lives in
     * config/autoload/*.local.php and is auto-merged into `$config` at boot,
     * so there's no need to re-read it from disk on every request.
     *
     * If a service is registered for ErrorPageRepositoryInterface (e.g. a
     * consumer wants to swap in their own implementation), that wins.
     *
     * @param array<string, mixed> $errors
     */
    private function resolveRepository(ContainerInterface $container, array $errors): ErrorPageRepositoryInterface
    {
        if ($container->has(ErrorPageRepositoryInterface::class)) {
            return $container->get(ErrorPageRepositoryInterface::class);
        }

        $pages = [];
        foreach (($errors['pages'] ?? []) as $status => $row) {
            if (! is_int($status) || ! is_array($row)) {
                continue;
            }
            $pages[$status] = new ErrorPage(
                $status,
                (string) ($row['title'] ?? ''),
                (string) ($row['body'] ?? ''),
            );
        }

        return new InMemoryRepository($pages);
    }

    private function resolveLogger(ContainerInterface $container, mixed $logger): ?LoggerInterface
    {
        if ($logger === null) {
            return null;
        }

        if ($logger instanceof LoggerInterface) {
            return $logger;
        }

        if (is_string($logger) && $logger !== '') {
            $resolved = $container->get($logger);
            if (! $resolved instanceof LoggerInterface) {
                throw new RuntimeException(sprintf(
                    'contenir/errors-laminas-mvc: logger service "%s" must implement Psr\Log\LoggerInterface.',
                    $logger,
                ));
            }
            return $resolved;
        }

        throw new RuntimeException(
            'contenir/errors-laminas-mvc: config[errors][logger] must be null, a service ID string,'
            . ' or a Psr\Log\LoggerInterface instance.'
        );
    }
}
