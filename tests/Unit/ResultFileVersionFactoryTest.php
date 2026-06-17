<?php

namespace Tests\Unit;

use App\Services\ResultFileVersionFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResultFileVersionFactoryTest extends TestCase
{
    public function testCreatesStableVersionFromMetadata(): void {
        $factory = new ResultFileVersionFactory();

        $version = $factory->fromMetadata('/tmp/results/0001.game', 123, 456, str_repeat('a', 64));

        $this->assertSame('/tmp/results/0001.game', $version->path);
        $this->assertSame(sha1('/tmp/results/0001.game'), $version->pathHash);
        $this->assertSame(123, $version->mtime);
        $this->assertSame(456, $version->size);
        $this->assertSame(str_repeat('a', 64), $version->contentHash);
        $this->assertSame(
            sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
            $version->version,
        );
    }

    public function testCreatesVersionFromReadableFile(): void {
        $path = tempnam(sys_get_temp_dir(), 'lac-result-version-');
        $this->assertIsString($path);
        file_put_contents($path, 'result-content');

        try {
            $version = (new ResultFileVersionFactory())->fromFile($path);

            $this->assertSame(realpath($path), $version->path);
            $this->assertSame(hash_file('sha256', $path), $version->contentHash);
            $this->assertSame(filesize($path), $version->size);
            $this->assertSame(filemtime($path), $version->mtime);
        } finally {
            unlink($path);
        }
    }

    public function testUnreadableFileThrows(): void {
        $this->expectException(RuntimeException::class);

        (new ResultFileVersionFactory())->fromFile('/path/that/does/not/exist.game');
    }
}
