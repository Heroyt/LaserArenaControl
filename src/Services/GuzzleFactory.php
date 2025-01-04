<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Lsr\Logging\Logger;
use SensitiveParameter;

/**
 *
 */
class GuzzleFactory
{
    private Logger $logger;

    public function __construct() {
        $this->logger = new Logger(LOG_DIR, 'guzzle');
    }


    public function makeClient(string $url, #[SensitiveParameter] string $apiKey) : Client {
        // Add logging to handler and set handler to cUrl
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler(['handle_factory' => new CurlFactory(99)]));
        $stack->push(Middleware::log($this->logger, new MessageFormatter(MessageFormatter::DEBUG)));

        // Initialize client
        return new Client(
          [
            'handler'         => $stack,
            'base_uri'        => trailingSlashIt($url).'api/',
            'timeout'         => 60.0, // 1 minute
            'allow_redirects' => true,
            'headers'         => [
              'Accept'        => 'application/json',
              'Authorization' => 'Bearer '.$apiKey,
            ],
          ]
        );
    }
}
