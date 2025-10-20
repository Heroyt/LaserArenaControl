<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core;

use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\DB;
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
     * @param  string  $key
     * @param  mixed|null  $default
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null, bool $useCache = true) : mixed {
        if (!$useCache) {
            $value = self::getValue($key);
            if ($value === null) {
                return $default;
            }
            $value = self::getUnserialized($value, $key);
            // Update cache
            self::set($key, $value);
            return $value;
        }

        if (isset(self::$info[$key])) {
            return self::$info[$key];
        }

        /** @var Cache $cache */
        $cache = App::getService('cache');
        try {
            /** @var string|null $value */
            $value = $cache->load(
              'info.'.$key,
              static fn() => self::getValue($key),
              [
                'tags' => ['info', 'info/'.$key],
              ]
            );
        } catch (Throwable) {
            $value = null;
        }
        if (!isset($value)) {
            return $default;
        }
        return self::getUnserialized($value, $key);
    }

    private static function getValue(string $key) : ?string {
        try {
            return DB::select(self::TABLE, '[value]')
                     ->where('[key] = %s', $key)
                     ->cacheTags('info', 'info/'.$key)
                     ->fetchSingle(false);
        } catch (Exception) {
            return null;
        }
    }

    private static function getUnserialized(?string $value, string $key) : mixed {
        if ($value === null) {
            return null;
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
        self::$info[$key] = $unserialized;
        return $unserialized;
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     *
     * @return void
     * @throws Exception
     */
    public static function set(string $key, mixed $value) : void {
        self::$info[$key] = $value; // Cache
        $serialized = igbinary_serialize($value);
        DB::replace(
          self::TABLE,
          [
            [
              'key'   => $key,
              'value' => $serialized,
            ],
          ]
        );
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->save('info.'.$key, $serialized, [$cache::Tags => ['info', 'info/'.$key]]);
    }

    public static function clearStaticCache() : void {
        self::$info = [];
    }
}
