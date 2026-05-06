<?php

declare(strict_types=1);

namespace Contenir\Errors\Laminas\Mvc\Tests\Unit\Factory;

use Contenir\Errors\Laminas\Mvc\Factory\FileRepositoryFactory;
use Contenir\Errors\Laminas\Mvc\Tests\Unit\Factory\Stub\ArrayContainer;
use Contenir\Errors\Repository\FileRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit')]
#[Group('factory')]
final class FileRepositoryFactoryTest extends TestCase
{
    public function testBuildsFileRepositoryFromConfiguredPath(): void
    {
        $container = new ArrayContainer([
            'config' => ['errors' => ['file' => '/tmp/errors.local.php']],
        ]);

        $repository = (new FileRepositoryFactory())($container);

        self::assertInstanceOf(FileRepository::class, $repository);
    }

    public function testThrowsWhenFilePathIsMissing(): void
    {
        $container = new ArrayContainer(['config' => ['errors' => []]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('config[errors][file]');

        (new FileRepositoryFactory())($container);
    }

    public function testThrowsWhenFilePathIsEmpty(): void
    {
        $container = new ArrayContainer(['config' => ['errors' => ['file' => '']]]);

        $this->expectException(RuntimeException::class);

        (new FileRepositoryFactory())($container);
    }

    public function testThrowsWhenFilePathIsNonString(): void
    {
        $container = new ArrayContainer(['config' => ['errors' => ['file' => 42]]]);

        $this->expectException(RuntimeException::class);

        (new FileRepositoryFactory())($container);
    }

    public function testThrowsWhenContainerHasNoConfig(): void
    {
        $container = new ArrayContainer([]);

        $this->expectException(RuntimeException::class);

        (new FileRepositoryFactory())($container);
    }
}
