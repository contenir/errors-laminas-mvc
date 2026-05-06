<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Factory;

use Contenir\Errors\ErrorPageRepositoryInterface;
use Contenir\Errors\Laminas\Mvc\ConfigProvider;
use Contenir\Errors\Laminas\Mvc\Listener\ErrorListener;
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
            repository: $container->get(ErrorPageRepositoryInterface::class),
            viewTemplate: (string) $errors['view_template'],
            logger: $this->resolveLogger($container, $errors['logger']),
        );
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
