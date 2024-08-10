<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core;

use Dibi\Exception;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Throwable;

/**
 * Key-value read-write storage (in database)
 *
 * Allows storing any serializable values.
 */
class Info
{
    public const string TABLE = 'page_info';
    /** @var array<string, mixed> */
    private static array $info = [];

    /**
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (isset(self::$info[$key])) {
            return self::$info[$key];
        }

        /** @var Cache $cache */
        $cache = App::getService('cache');
        try {
            /** @var string|null $value */
            $value = $cache->load(
                'info.' . $key,
                static fn() => DB::select(self::TABLE, '[value]')
                                 ->where('[key] = %s', $key)
                                 ->cacheTags('info', 'info/' . $key)
                                 ->fetchSingle(),
                [
                    $cache::Tags => ['info', 'info/' . $key],
                ]
            );
        } catch (Throwable) {
            $value = null;
        }
        if (!isset($value)) {
            return $default;
        }
        $unserialized = igbinary_unserialize($value);
        if (
            ($unserialized === false && $value !== igbinary_serialize(false)) ||
            ($unserialized === null && $value !== igbinary_serialize(null))
        ) {
            // Fallback to normal PHP serialization
            /** @noinspection UnserializeExploitsInspection */
            $unserialized = unserialize($value);
            // Re-serialize the value
            self::set($key, $unserialized);
        }
        self::$info[$key] = $unserialized; // Cache
        return $unserialized;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     * @throws Exception
     */
    public static function set(string $key, mixed $value): void {
        self::$info[$key] = $value; // Cache
        $serialized = igbinary_serialize($value);
        /** @phpstan-ignore-next-line */
        DB::replace(self::TABLE, [
            [
                'key'   => $key,
                'value' => $serialized,
            ]
        ]);
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean([$cache::Tags => ['info/' . $key]]);
        $cache->save('info.' . $key, $serialized, [$cache::Tags => ['info', 'info/' . $key]]);
    }

    public static function clearStaticCache(): void {
        self::$info = [];
    }
}
