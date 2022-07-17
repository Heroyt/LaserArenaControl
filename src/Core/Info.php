<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core;

use Dibi\Exception;
use Lsr\Core\DB;

/**
 * Key-value read-write storage (in database)
 *
 * Allows storing any serializable values.
 */
class Info
{

	public const TABLE = 'page_info';
	private static array $info = [];

	/**
	 * @param string     $key
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	public static function get(string $key, mixed $default = null) : mixed {
		if (isset(self::$info[$key])) {
			return self::$info[$key];
		}
		$value = DB::select(self::TABLE, '[value]')->where('[key] = %s', $key)->fetchSingle();
		if (!isset($value)) {
			return $default;
		}
		/** @noinspection UnserializeExploitsInspection */
		$value = unserialize($value);
		self::$info[$key] = $value; // Cache
		return $value;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function set(string $key, mixed $value) : void {
		self::$info[$key] = $value; // Cache
		DB::replace(self::TABLE, [
			[
				'key'   => $key,
				'value' => serialize($value),
			]
		]);
	}

}