<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CQRS\CommandHandlers\ImportResultFileCommandHandler;
use App\CQRS\Commands\ImportResultFileCommand;
use App\DataObjects\Import\QueuedResultFileImport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ImportResultFileCommandTest extends TestCase
{
    public function test_command_uses_import_handler(): void {
        $command = $this->createCommand();

        $this->assertSame(ImportResultFileCommandHandler::class, $command->getHandler());
    }

    private function createCommand(): ImportResultFileCommand {
        return new ImportResultFileCommand(
            '/tmp/results/0001.game',
            sha1('/tmp/results/0001.game'),
            'evo6',
            123,
            456,
            str_repeat('a', 64),
            sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
        );
    }

    public function test_creates_command_from_queued_file(): void {
        $queuedFile = new QueuedResultFileImport(
            '/tmp/results/0001.game',
            sha1('/tmp/results/0001.game'),
            'evo6',
            123,
            456,
            str_repeat('a', 64),
            sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
            'content',
        );

        $command = ImportResultFileCommand::fromQueuedFile($queuedFile);

        $this->assertSame($queuedFile->path, $command->path);
        $this->assertSame($queuedFile->pathHash, $command->pathHash);
        $this->assertSame($queuedFile->system, $command->system);
        $this->assertSame($queuedFile->version, $command->version);
        $this->assertSame('content', $command->content);
    }

    public function test_creates_forced_command_from_queued_file(): void {
        $queuedFile = new QueuedResultFileImport(
            '/tmp/results/0001.game',
            sha1('/tmp/results/0001.game'),
            'evo6',
            123,
            456,
            str_repeat('a', 64),
            sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
        );

        $command = ImportResultFileCommand::fromQueuedFile($queuedFile, force: true);

        $this->assertTrue($command->force);
    }

    public function test_converts_to_version(): void {
        $command = $this->createCommand();

        $version = $command->toVersion();

        $this->assertSame($command->path, $version->path);
        $this->assertSame($command->pathHash, $version->pathHash);
        $this->assertSame($command->mtime, $version->mtime);
        $this->assertSame($command->size, $version->size);
        $this->assertSame($command->contentHash, $version->contentHash);
        $this->assertSame($command->version, $version->version);
    }

    public function test_import_lock_ttl_uses_minimum_for_short_timeout(): void {
        $this->assertSame(60, $this->getImportLockTtl(30));
    }

    public function test_import_lock_ttl_uses_timeout_with_margin_for_long_timeout(): void {
        $this->assertSame(150, $this->getImportLockTtl(120));
    }

    public function test_import_lock_ttl_uses_minimum_for_disabled_timeout(): void {
        $this->assertSame(60, $this->getImportLockTtl(0));
    }

    private function getImportLockTtl(int $timeoutSeconds): int {
        $handler = (new ReflectionClass(ImportResultFileCommandHandler::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ImportResultFileCommandHandler::class, 'getImportLockTtl');

        return $method->invoke($handler, $timeoutSeconds);
    }
}
