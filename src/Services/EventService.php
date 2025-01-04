<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\App;
use JsonException;
use Lsr\Core\Config;
use Redis;

/**
 * Service for broadcasting WS events to front-end users
 */
class EventService
{
    private static string $eventUrl;

    public function __construct(
      private readonly Redis $redis,
    ) {}

    public static function getEventUrl() : string {
        if (!isset(self::$eventUrl)) {
            /** @var Config $config */
            $config = App::getServiceByType(Config::class);

            /** @var string $url */
            $url = $config->getConfig('ENV')['EVENT_URL'] ??
              explode(':', ($_SERVER['HTTP_HOST'] ?? '{host}'))[0].':'.self::getEventPort();

            $host = App::getInstance()->getBaseUrl();
            if (str_contains(strtolower($url), '{host}')) {
                $url = str_replace(['{host}', '{HOST}'], $host, $url);
            }
            else {
                $url = App::getInstance()->getBaseUrlObject()->getScheme().'://'.$url;
            }

            // Remove double slashes
            if (str_ends_with($host, '/')) {
                $host = substr($host, 0, -1);
            }

            self::$eventUrl = str_replace($host.'//', $host.'/', $url);
        }
        return self::$eventUrl;
    }

    public static function getEventPort() : int {
        return EVENT_PORT;
    }

    /**
     * Trigger a new event to be broadcast
     *
     * @param  string|array<string,mixed>  $message  Event content
     *
     * @return bool Success
     * @throws JsonException
     */
    public function trigger(string $type, string | array $message) : bool {
        if (is_array($message)) {
            $message = json_encode($message, JSON_THROW_ON_ERROR);
        }
        $id = $this->redis->xAdd('events:'.$type, '*', ['message' => $message], 10, true);
        return !empty($id);
    }
}
