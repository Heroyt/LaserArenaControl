<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\App;
use App\Core\Info;
use App\GameModels\Game\Enums\VestStatus;
use App\GameModels\Game\Game;
use App\GameModels\Vest;
use App\Models\MusicMode;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use JsonException;
use LAC\Modules\Core\LigaApiExtensionInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 * Singleton service for handling public API calls
 */
class LigaApi
{
    public static LigaApi $instance;

    public Client $client;
    private Logger $logger;

    /** @var LigaApiExtensionInterface[] */
    private array $extensions;

    public function __construct(
        public string $url,
        public string $apiKey,
        private readonly Metrics $metrics,
    ) {
        $this->logger = new Logger(LOG_DIR, 'ligaApi');
        $this->makeClient();
    }

    private function makeClient(): void {
        // Add logging to handler and set handler to cUrl
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler(['handle_factory' => new CurlFactory(99)]));
        $stack->push(Middleware::log($this->logger, new MessageFormatter(MessageFormatter::DEBUG)));

        // Initialize client
        $this->client = new Client([
            'handler' => $stack,
            'base_uri' => trailingSlashIt($this->url) . 'api/',
            'timeout' => 60.0, // 1 minute
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
        ]);
    }

    /**
     * @return LigaApi
     */
    public static function getInstance(Metrics $metrics): LigaApi {
        if (!isset(self::$instance)) {
            /** @var string $url */
            $url = Info::get('liga_api_url', '');
            /** @var string $key */
            $key = Info::get('liga_api_key', '');
            self::$instance = new self($url, $key, $metrics);
        }
        return self::$instance;
    }

    /**
     * Send a GET request to the liga API with all necessary settings, headers, etc.
     *
     * @param string $path
     * @param array<string,mixed>|null $params
     * @param array<string,mixed> $config
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function get(string $path, ?array $params = null, array $config = []): ResponseInterface {

        $this->makeClient();
        if (isset($params)) {
            $config['query'] = $params;
        }
        return $this->client->get($path, $config);
    }

    /**
     * Synchronize games to public API
     *
     * @param string $system
     * @param Game[] $games
     * @param float|null $timeout
     * @param bool $recreateClient
     *
     * @return bool
     * @throws JsonException
     * @post All finished games will be sent to public
     */
    public function syncGames(string $system, array $games, ?float $timeout = null, bool $recreateClient = false): bool {
        if ($recreateClient) {
            $this->makeClient();
        }

        foreach ($this->getExtensions() as $extension) {
            $extension->beforeGameSync($system, $games);
        }

      /** @var PlayerProvider $playerProvider */
        $playerProvider = App::getService('playersProvider');

        // Validate each game
        $gamesData = [];
        foreach ($games as $key => $game) {
            if ($game::SYSTEM !== $system) {
                throw new InvalidArgumentException('Game #' . $key . ' (code: ' . $game->code . ') is not an ' . $system . ' game.');
            }
            // Remove unfinished games
            if ($game->isFinished()) {
                // Check assigned users
                try {
                    // Get user data from game
                    $response = $this->client->get('games/' . $game->code . '/users');
                    if ($response->getStatusCode() === 200) {
                        $response->getBody()->rewind();
                        /** @var array<int,array{id:int,nickname:string,code:string,arena:int,email:string,stats:array{rank:int,gamesPlayed:int,arenasPlayed:int},connections:array{type:string,identifier:string}[]}> $users */
                        $users = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                        if (!empty($users)) {
                            // Assign user objects for each user got
                            foreach ($users as $vest => $userData) {
                                $player = $game->getVestPlayer($vest);
                                if (isset($player)) {
                                    $player->user = $playerProvider->getPlayerObjectFromData($userData);
                                    // Sync new user
                                    if (isset($player->user) && !isset($player->user->id)) {
                                        $player->user->save();
                                    }
                                    $player->save();
                                }
                            }
                        }
                    }
                } catch (GuzzleException | ValidationException | JsonException) {
                }

                foreach ($this->getExtensions() as $extension) {
                    $extension->processGameBeforeSync($game);
                }

                $gamesData[] = $game;
            }
        }

        // Build a request
        try {
            $config = [
                'body' => \GuzzleHttp\Utils::jsonEncode(['system' => $system, 'games' => $gamesData]),
            ];
            $config['headers']['Content-Type'] = 'application/json';
            $config['headers']['Content-Length'] = strlen($config['body']);
            if (isset($timeout)) {
                $config['timeout'] = $timeout;
            }

            $response = $this->client->post('games', $config);
            $status = $response->getStatusCode();
            if ($status > 299) {
                $this->logger->error('Request failed: ' . json_encode($response->getBody()->getContents(), JSON_THROW_ON_ERROR));
                return false;
            }
        } catch (GuzzleException $e) {
            $this->logger->exception($e);
            return false;
        }

        $this->metrics->add('games_synced', count($gamesData));

        foreach ($this->getExtensions() as $extension) {
            $extension->afterGameSync($system, $games);
        }

        return true;
    }

    /**
     * Send a POST request to the liga API with all necessary settings, headers, etc.
     *
     * @param string $path
     * @param array|object|null $data
     * @param array<string,mixed> $config
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post(string $path, array|object|null $data = null, array $config = []): ResponseInterface {
        $this->makeClient();
        if (isset($data)) {
            $config['body'] = \GuzzleHttp\Utils::jsonEncode($data);
            $config['headers']['Content-Type'] = 'application/json';
            $config['headers']['Content-Length'] = strlen($config['body']);
        }
        return $this->client->post($path, $config);
    }

    public function syncMusicMode(MusicMode $mode): bool {
        try {
            if (!$mode->public) {
                $response = $this->client->delete('music/' . $mode->id);
                $response->getBody()->rewind();
                $body = $response->getBody()->getContents();
                if ($response->getStatusCode() !== 200) {
                    $this->logger->error('Music delete failed: ' . $body);
                    return false;
                }
                return true;
            }

            $config = [
                'body' => \GuzzleHttp\Utils::jsonEncode([
                    'music' => [
                        [
                            'id' => $mode->id,
                            'name' => $mode->name,
                            'order' => $mode->order,
                            'previewStart' => $mode->previewStart,
                        ],
                    ],
                ]),
            ];
            $config['headers']['Content-Type'] = 'application/json';
            $config['headers']['Content-Length'] = strlen($config['body']);

            $response = $this->client->post('music', $config);
            $response->getBody()->rewind();
            $body = $response->getBody()->getContents();
            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Music sync failed: ' . $body);
                return false;
            }

            $response = $this->client->post('music/' . $mode->id . '/upload', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'multipart' => [
                    [
                        'name' => 'media',
                        'contents' => Utils::tryFopen($mode->fileName, 'r'),
                    ],
                ],
            ]);
            $response->getBody()->rewind();
            $body = $response->getBody()->getContents();
            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Music upload failed: ' . $body);
                return false;
            }
        } catch (GuzzleException $e) {
            $this->logger->error('Api request failed');
            $this->logger->error($e->getMessage());
            $this->logger->debug($e->getTraceAsString());
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws ValidationException
     */
    public function syncMusicModes(): bool {
        $musicModes = MusicMode::getAll();

        // Sync data
        $private = [];
        $data = [];
        foreach ($musicModes as $mode) {
            if (!$mode->public) {
                $private[] = $mode->id;
                continue;
            }
            $data[] = [
                'id' => $mode->id,
                'name' => $mode->name,
                'order' => $mode->order,
                'previewStart' => $mode->previewStart,
            ];
        }

        try {
            $config = [
                'body' => \GuzzleHttp\Utils::jsonEncode([
                    'music' => $data,
                ]),
            ];
            $config['headers']['Content-Type'] = 'application/json';
            $config['headers']['Content-Length'] = strlen($config['body']);
            $response = $this->client->post('music', $config);
            $response->getBody()->rewind();
            $body = $response->getBody()->getContents();
            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Music sync failed: ' . $body);
                return false;
            }

            foreach ($private as $id) {
                $this->client->deleteAsync('music/' . $id);
            }

            // Upload files
            foreach ($musicModes as $mode) {
                if (!$mode->public) {
                    continue;
                }
                $previewFile = $mode->getPreviewFileName();
                if (!file_exists($previewFile)) {
                    $mode->trimMediaToPreview();
                }
                $media = Utils::tryGetContents(Utils::tryFopen($previewFile, 'r'));

                $boundary = uniqid('', true);
                $delimiter = '-------------' . $boundary;

                $fileName = basename($previewFile);
                $post_data = $this->build_data_files($boundary, [], [['name' => 'media', 'fileName' => $fileName, 'contents' => $media, 'type' => 'audio/mpeg']]);

                $ch = curl_init(trailingSlashIt($this->url) . 'api/music/' . $mode->id . '/upload');
                curl_setopt_array($ch, [
                    CURLOPT_POST => 1,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POSTFIELDS => $post_data,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $this->apiKey,
                        'Content-Type: multipart/form-data; boundary=' . $delimiter,
                        "Content-Length: " . strlen($post_data),
                        "Accept: application/json",
                    ],
                ]);
                $body = curl_exec($ch);
                $info = curl_getinfo($ch);
                if ($info['http_code'] !== 200) {
                    $this->logger->error('Music upload failed: ' . $body . ' ' . json_encode($info));
                    return false;
                }
            }
        } catch (GuzzleException $e) {
            $this->logger->error('Api request failed');
            $this->logger->error($e->getMessage());
            $this->logger->debug($e->getTraceAsString());
            return false;
        }
        return true;
    }

    private function build_data_files(string $boundary, array $fields, array $files): string {
        $data = '';
        $eol = "\r\n";

        $delimiter = '-------------' . $boundary;

        foreach ($fields as $name => $content) {
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . "\"" . $eol . $eol
                . $content . $eol;
        }

        foreach ($files as $file) {
            $name = $file['name'];
            $fileName = $file['fileName'];
            $content = $file['contents'];
            $type = $file['type'];
            $data .= "--" . $delimiter . $eol
                . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $fileName . '"' . $eol
                . 'Content-Type: ' . $type . $eol
                . 'Content-Transfer-Encoding: binary' . $eol;

            $data .= $eol;
            $data .= $content . $eol;
        }
        $data .= "--" . $delimiter . "--" . $eol;

        return $data;
    }

    /**
     * @param bool $remake
     *
     * @return Client
     */
    public function getClient(bool $remake = false): Client {
        if (!isset($this->client) || $remake) {
            $this->makeClient();
        }
        return $this->client;
    }

    /**
     * @return LigaApiExtensionInterface[]
     */
    public function getExtensions(): array {
        if (!isset($this->extensions)) {
            $this->extensions = [];
            foreach (App::getServiceByType(LigaApiExtensionInterface::class) as $name) {
                // @phpstan-ignore-next-line
                $this->extensions[] = App::getService($name);
            }
        }
      // @phpstan-ignore-next-line
        return $this->extensions;
    }

    /**
     * Synchronize all vests to laser liga
     *
     * @param bool $recreateClient
     *
     * @return bool
     * @throws GuzzleException
     * @throws JsonException
     * @throws ValidationException
     */
    public function syncVests(bool $recreateClient = false): bool {
        if ($recreateClient) {
            $this->makeClient();
        }

        $vestsAll = Vest::getAll();
        $vests = [];
        foreach ($vestsAll as $vest) {
            $vests[$vest->system] ??= [];
            $vests[$vest->system][$vest->vestNum] = $vest;
        }

        // Get updates from laser liga
        $response = $this->get('/api/vests');
        /** @var array{vestNum:string,system:string,status:string,info:string|null,updatedAt:array{date:string,timezone_type:int,timezone:string}}[] $data */
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($data as $vestData) {
            if (!isset($vests[$vestData['system']][$vestData['vestNum']])) {
                continue;
            }
            $vest = $vests[$vestData['system']][$vestData['vestNum']];
            $updated = new DateTimeImmutable($vestData['date'], new DateTimeZone($vestData['date']['timezone']));
            if ($vest->updatedAt < $updated) {
                $vest->status = VestStatus::tryFrom($vestData['status']) ?? $vest->status;
                $vest->info = $vestData['info'] ?? null;
                $vest->save();
            }
        }

        // Send all updates to laser liga
        $response = $this->post('/api/vests', $vestsAll);
        return $response->getStatusCode() < 300;
    }
}
