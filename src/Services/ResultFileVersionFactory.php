<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Import\ResultFileVersion;
use RuntimeException;

readonly class ResultFileVersionFactory
{
    public function fromFile(string $path): ResultFileVersion
    {
        $canonicalPath = realpath($path);
        if ($canonicalPath === false || !is_file($canonicalPath) || !is_readable($canonicalPath)) {
            throw new RuntimeException('Result file is not readable: ' . $path);
        }

        $mtime = filemtime($canonicalPath);
        $size = filesize($canonicalPath);
        $hash = hash_file('sha256', $canonicalPath);
        if ($mtime === false || $size === false || $hash === false) {
            throw new RuntimeException('Failed to read result file metadata: ' . $canonicalPath);
        }

        return $this->fromMetadata($canonicalPath, $mtime, $size, $hash);
    }

    public function fromMetadata(string $path, int $mtime, int $size, string $contentHash): ResultFileVersion
    {
        $canonicalPath = $this->canonicalizePath($path);

        return new ResultFileVersion(
            path: $canonicalPath,
            pathHash: sha1($canonicalPath),
            mtime: $mtime,
            size: $size,
            contentHash: $contentHash,
            version: sha1($canonicalPath . ':' . $mtime . ':' . $size . ':' . $contentHash),
        );
    }

    private function canonicalizePath(string $path): string
    {
        $realPath = realpath($path);
        return $realPath === false ? $path : $realPath;
    }
}
