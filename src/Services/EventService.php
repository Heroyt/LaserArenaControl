<?php

namespace App\Services;

use App\Core\DB;
use App\Logging\Logger;
use Dibi\DateTime;
use Dibi\Exception;

class EventService
{

	public const TABLE = 'events';

	private static Logger $logger;

	/**
	 * Trigger a new event to be broadcast
	 *
	 * @param string $message Event content
	 *
	 * @return bool Success
	 */
	public static function trigger(string $message) : bool {
		try {
			DB::insert(self::TABLE, ['message' => $message]);
			return true;
		} catch (Exception $e) {
			self::getLogger()->error($e->getMessage());
			self::getLogger()->debug($e->getTraceAsString());
		}
		return false;
	}

	/**
	 * @return Logger
	 */
	private static function getLogger() : Logger {
		if (!isset(self::$logger)) {
			self::$logger = new Logger(LOG_DIR, 'events');
		}
		return self::$logger;
	}

	/**
	 * Get all events that are not flagged as sent yet
	 *
	 * @return object{id_event:int,message:string,datetime:DateTime,sent:int}[] Array of events ordered by their date from the most recent (descending)
	 */
	public static function getUnsent() : array {
		return DB::select('events', '*')->where('sent = 0')->orderBy('datetime')->desc()->fetchAll();
	}

	/**
	 * Flag given event ids as sent
	 *
	 * @param int[] $ids Event ids to flag
	 *
	 * @return bool Success
	 */
	public static function updateSent(array $ids) : bool {
		try {
			DB::update('events', ['sent' => 1], ['id_event IN %in', $ids]);
			return true;
		} catch (Exception $e) {
			self::getLogger()->error($e->getMessage());
			self::getLogger()->debug($e->getTraceAsString());
		}
		return false;
	}

}