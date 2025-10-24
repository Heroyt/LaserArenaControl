<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools;

use App\Core\App;
use App\Exceptions\ConnectionException;
use App\Exceptions\ConnectionTimeoutException;
use Lsr\Caching\Cache;

/**
 * Controller for sending TCP requests to control the LaserMaxx console
 */
class LMXController
{
    public const int PORT = 8081;
    public const string LOAD_COMMAND = 'load';
    public const string LOAD_START_COMMAND = 'loadStart';
    public const string START_COMMAND = 'start';
    public const string END_COMMAND = 'end';
    public const string GET_STATUS_COMMAND = 'status';
    public const string RETRY_DOWNLOAD_COMMAND = 'retryDownload';
    public const string CANCEL_DOWNLOAD_COMMAND = 'cancelDownload';

    public const string OK_MESSAGE = 'ok';
    public const string INVALID_MESSAGE = 'invalid command';

    public const int DEFAULT_TIMEOUT = 30;
    public const array COMMAND_TIMEOUTS = [
      self::GET_STATUS_COMMAND => 3,
    ];

    /**
     * Retry scores download
     *
     * @param  non-empty-string  $ip
     *
     * @return string 'ok'
     * @throws ConnectionException
     */
    public static function retryDownload(string $ip) : string {
        return self::sendCommand($ip, self::RETRY_DOWNLOAD_COMMAND);
    }

    /**
     * @param  non-empty-string  $ip
     * @param  non-empty-string  $command
     * @param  string  $parameters
     *
     * @return string Response
     * @throws ConnectionException
     */
    public static function sendCommand(string $ip, string $command, string $parameters = '') : string {
        $timeout = self::COMMAND_TIMEOUTS[$command] ?? self::DEFAULT_TIMEOUT;
        $fp = @fsockopen($ip, self::PORT, $errno, $errstr, $timeout);
        if (!$fp) {
            throw new ConnectionTimeoutException(
              sprintf(
                lang('Nepodařilo se připojit k TCP serveru (%s:%d).'),
                $ip,
                self::PORT
              ).' '.$errstr.' ('.$errno.')',
              $errno,
              $timeout
            );
        }
        if (fwrite($fp, $command.':'.$parameters) === false) {
            fclose($fp);
            throw new ConnectionException(
              sprintf(
                lang('Nepodařilo se odeslat příkaz TCP serveru (%s:%d).'),
                $ip,
                self::PORT
              ),
              0,
            );
        }
        $response = '';
        while (!feof($fp)) {
            $response .= fgets($fp, 128);
        }
        fclose($fp);
        return $response;
    }

    /**
     * Cancel scores download
     *
     * @param  non-empty-string  $ip
     *
     * @return string 'ok'
     * @throws ConnectionException
     */
    public static function cancelDownload(string $ip) : string {
        return self::sendCommand($ip, self::CANCEL_DOWNLOAD_COMMAND);
    }

    /**
     * Get a current game status
     *
     * @param  non-empty-string  $ip
     *
     * @return string 'ARMED'|'STANDBY'|'PLAYING'
     * @throws ConnectionException
     */
    public static function getStatus(string $ip) : string {
        $cache = App::getService('cache');
        assert($cache instanceof Cache);
        return $cache->load(
          'lmx.status.'.$ip,
          static fn() => self::sendCommand($ip, self::GET_STATUS_COMMAND),
          /** @phpstan-ignore argument.type */
          [
            $cache::Expire => 5,
          ]
        );
    }

    /**
     * Load a new game
     *
     * @param  non-empty-string  $ip
     * @param  string  $gameMode  Game mode to load
     *
     * @return string Response
     * @throws ConnectionException
     */
    public static function load(string $ip, string $gameMode) : string {
        return self::sendCommand($ip, self::LOAD_COMMAND, $gameMode);
    }

    /**
     * Start a new game
     *
     * @param  non-empty-string  $ip
     *
     * @return string
     * @throws ConnectionException
     */
    public static function start(string $ip) : string {
        return self::sendCommand($ip, self::START_COMMAND);
    }

    /**
     * Load a new game and start it
     *
     * @param  non-empty-string  $ip
     * @param  string  $gameMode  Game mode to load
     *
     * @return string Response
     * @throws ConnectionException
     */
    public static function loadStart(string $ip, string $gameMode) : string {
        return self::sendCommand($ip, self::LOAD_START_COMMAND, $gameMode);
    }

    /**
     * Stop a currently running game
     *
     * @param  non-empty-string  $ip
     *
     * @return string response
     * @throws ConnectionException
     */
    public static function end(string $ip) : string {
        return self::sendCommand($ip, self::END_COMMAND);
    }
}
