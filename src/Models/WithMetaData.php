<?php

namespace App\Models;

use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\Transform;

/**
 * @template T of array<string,mixed>
 */
trait WithMetaData
{
    #[JsonExclude, Transform(save: 'transformMetaForSave', load: 'transformMetaForLoad')]
    public ?string $meta = null;
    /** @var T|array<string,mixed>|null */
    protected ?array $metaData = null;

    /**
     * @param  string|key-of<T>  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setMetaValue(string $key, mixed $value) : static {
        $meta = $this->getMeta();
        $meta[$key] = $value;
        $this->setMeta($meta);
        return $this;
    }

    /**
     * @return T|array<string,mixed>
     */
    public function getMeta() : array {
        if (!isset($this->metaData)) {
            $this->metaData = !empty($this->meta) ? $this->unserializeMeta($this->meta) : [];
        }
        assert($this->metaData !== null);
        return $this->metaData;
    }

    /**
     * @param  T|array<string,mixed>  $meta
     * @return $this
     */
    public function setMeta(array $meta) : static {
        $this->metaData = $meta;
        $this->meta = igbinary_serialize($meta);
        return $this;
    }

    public function transformMetaForSave(?string $meta): ?string
    {
        if ($meta === null) {
            return null;
        }
        return base64_encode($meta);
    }

    public function transformMetaForLoad(?string $meta): ?string
    {
        if ($meta === null) {
            return null;
        }
        $decoded = base64_decode($meta, true);
        if ($decoded === false) {
            return $meta;
        }
        if ($this->canUnserializeMeta($decoded)) {
            return $decoded;
        }
        return $meta;
    }

    /**
     * @return T|array<string,mixed>
     */
    protected function unserializeMeta(string $meta): array
    {
        $decoded = base64_decode($meta, true);
        if ($decoded !== false && $this->canUnserializeMeta($decoded)) {
            /** `@var` T|array<string,mixed> $data */
            $data = igbinary_unserialize($decoded);
            return $data;
        }
        if ($this->canUnserializeMeta($meta)) {
            /** `@var` T|array<string,mixed> $data */
            $data = igbinary_unserialize($meta);
            return $data;
        }
        return [];
    }

    private function canUnserializeMeta(string $value): bool
    {
        $unserialized = @igbinary_unserialize($value);
        return !(
            ($unserialized === false && $value !== igbinary_serialize(false)) ||
            ($unserialized === null && $value !== igbinary_serialize(null))
        );
    }
}
