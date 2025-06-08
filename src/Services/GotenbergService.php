<?php

namespace App\Services;

use App\Services\Gotenberg\Chromium;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use JsonException;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class GotenbergService
{
    public readonly Chromium $chromium;
    private Logger $logger;
    private Client $client;

    public function __construct(
      public readonly string $host,
      public readonly int    $port,
    ) {
        $this->chromium = new Chromium($this);
        $this->logger = new Logger(LOG_DIR, 'gotenberg');
        $this->makeClient();
    }

    private function makeClient() : void {
        // Add logging to handler and set handler to cUrl
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler(['handle_factory' => new CurlFactory(99)]));
        $stack->push(Middleware::log($this->logger, new MessageFormatter(MessageFormatter::DEBUG)));

        // Initialize client
        $this->client = new Client(
          [
            'handler'         => $stack,
            'base_uri' => trailingUnSlashIt($this->host).':'.$this->port,
            'timeout'         => 30.0, // 10 seconds
            'allow_redirects' => true,
            'headers'         => [],
          ]
        );
    }

    /**
     * @param  string  $path
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @return ResponseInterface|null
     */
    public function get(string $path, array $query = [], array $headers = []) : ?ResponseInterface {
        try {
            $response = $this->client->post(
              $path.'?'.http_build_query($query),
              [
                'headers' => $headers,
              ]
            );
            if ($response->getStatusCode() !== 200) {
                try {
                    $this->logger->error(
                      'Request failed: '.json_encode($response->getBody()->getContents(), JSON_THROW_ON_ERROR)
                    );
                } catch (JsonException $e) {
                    $this->logger->exception($e);
                }
            }
        } catch (GuzzleException $e) {
            $this->logger->exception($e);
            return null;
        }

        return $response;
    }

    /**
     * @param  string  $path
     * @param  array<string, mixed>  $formData
     * @param  array<string, string>  $headers
     *
     * @return ResponseInterface|null
     */
    public function post(string $path, array $formData = [], array $headers = []) : ?ResponseInterface {
        if (!isset($headers['Accept'])) {
            $headers['Accept'] = 'application/pdf';
        }
        try {
            $response = $this->client->post(
              $path,
              [
                'multipart' => $formData,
                'headers'   => $headers,
              ]
            );
            if ($response->getStatusCode() !== 200) {
                try {
                    $this->logger->error(
                      'Request failed: '.json_encode($response->getBody()->getContents(), JSON_THROW_ON_ERROR)
                    );
                } catch (JsonException $e) {
                    $this->logger->exception($e);
                }
            }
        } catch (GuzzleException $e) {
            $this->logger->exception($e);
            return null;
        }

        $this->logger->debug(
          'Status code: '.$response->getStatusCode().', Content-Type: '.$response->getHeaderLine('Content-Type')
        );

        return $response;
    }

    /**
     * @return Logger
     */
    public function getLogger() : Logger {
        return $this->logger;
    }
}
