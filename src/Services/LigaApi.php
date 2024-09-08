<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Api\DataObjects\LigaPlayer\LigaPlayerData;
use App\Api\DataObjects\Vests\LigaVest;
use App\Core\App;
use App\Core\Info;
use App\GameModels\Game\Game;
use App\GameModels\Vest;
use App\Models\MusicMode;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use JsonException;
use LAC\Modules\Core\LigaApiExtensionInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SensitiveParameter;
use Spiral\RoadRunner\Metrics\Metrics;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Serializer;

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
        #[SensitiveParameter]
        public string $apiKey,
        private readonly Metrics $metrics,
        private readonly Serializer $serializer,
        private readonly GuzzleFactory $guzzleFactory,
    ) {
        $this->logger = new Logger(LOG_DIR, 'ligaApi');
        $this->makeClient();
    }

    private function makeClient(): void {
        $this->client = $this->guzzleFactory->makeClient($this->url, $this->apiKey);
    }

    /**
     * @param  Metrics  $metrics
     * @param  Serializer  $serializer
     * @param  GuzzleFactory  $guzzleFactory
     * @return LigaApi
     */
    public static function getInstance(
        Metrics       $metrics,
        Serializer    $serializer,
        GuzzleFactory $guzzleFactory
    ): LigaApi {
        if (!isset(self::$instance)) {
            /** @var string $url */
            $url = Info::get('liga_api_url', '');
            /** @var string $key */
            $key = Info::get('liga_api_key', '');
            self::$instance = new self($url, $key, $metrics, $serializer, $guzzleFactory);
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
     * @post All finished games will be sent to public
     */
    public function syncGames(
        string $system,
        array  $games,
        ?float $timeout = null,
        bool   $recreateClient = false
    ): bool {
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
                throw new InvalidArgumentException(
                    'Game #' . $key . ' (code: ' . $game->code . ') is not an ' . $system . ' game.'
                );
            }
            // Remove unfinished games
            if ($game->isFinished()) {
                // Check assigned users
                try {
                    // Get user data from game
                    $response = $this->client->get('games/' . $game->code . '/users');
                    if ($response->getStatusCode() === 200) {
                        $response->getBody()->rewind();

                        /** @var LigaPlayerData[] $users */
                        $users = $this->serializer->deserialize(
                            $response->getBody()->getContents(),
                            LigaPlayerData::class . '[]',
                            'json'
                        );
                        if (!empty($users)) {
                            // Assign user objects for each user got
                            foreach ($users as $vest => $userData) {
                                $player = $game->getVestPlayer($vest);
                                if (isset($player)) {
                                    $player->user = $playerProvider->getPlayerObjectFromData($userData);
                                    // Sync new user
                                    if (!isset($player->user->id)) {
                                        $player->user->save();
                                    }
                                    $player->save();
                                }
                            }
                        }
                    }
                } catch (GuzzleException | ValidationException) {
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
                'body' => $this->serializer->serialize(['system' => $system, 'games' => $gamesData], 'json'),
            ];
            $config['headers']['Content-Type'] = 'application/json';
            $config['headers']['Content-Length'] = strlen($config['body']);
            if (isset($timeout)) {
                $config['timeout'] = $timeout;
            }

            $response = $this->client->post('games', $config);
            $status = $response->getStatusCode();
            if ($status > 299) {
                $this->logger->error(
                    'Request failed: ' .
                    $this->serializer->serialize($response->getBody()->getContents(), 'json')
                );
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
     * @param array<string,mixed>|object|null $data
     * @param array<string,mixed> $config
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post(string $path, array|object|null $data = null, array $config = []): ResponseInterface {
        $this->makeClient();
        if (isset($data)) {
            $config['body'] = $this->serializer->serialize($data, 'json');
            $config['headers']['Content-Type'] = 'application/json';
            $config['headers']['Content-Length'] = strlen($config['body']);
        }
        return $this->client->post($path, $config);
    }

    /**
     * @param  MusicMode  $mode
     * @return bool
     */
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
                'body' => $this->serializer->serialize(
                    [
                    'music' => [
                        [
                            'id' => $mode->id,
                            'name' => $mode->name,
                            'order' => $mode->order,
                            'previewStart' => $mode->previewStart,
                        ],
                    ],
                    ],
                    'json'
                ),
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
                'body' => $this->serializer->serialize([
                    'music' => $data,
                ], 'json'),
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
                $post_data = $this->buildDataFiles(
                    $boundary,
                    [],
                    [['name' => 'media', 'fileName' => $fileName, 'contents' => $media, 'type' => 'audio/mpeg']]
                );

                $ch = curl_init(trailingSlashIt($this->url) . 'api/music/' . $mode->id . '/upload');
                if ($ch === false) {
                    throw new RuntimeException('CURL init failed');
                }
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
                    $this->logger->error(
                        'Music upload failed: ' . $body . ' ' .
                        $this->serializer->serialize($info, 'json')
                    );
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

    /**
     * @param  string  $boundary
     * @param  array<string,mixed>  $fields
     * @param  array{name:string,fileName:string,contents:string,type:string}[]  $files
     * @return string
     */
    private function buildDataFiles(string $boundary, array $fields, array $files): string {
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
            $extensions = App::getServiceByType(LigaApiExtensionInterface::class);
            if ($extensions === null) {
                $extensions = [];
            } else if (!is_array($extensions)) {
                $extensions = [$extensions];
            }
            $this->extensions = $extensions;
        }
        return $this->extensions;
    }

    /**
     * Synchronize all vests to laser liga
     *
     * @param  bool  $recreateClient
     *
     * @return bool
     * @throws GuzzleException
     * @throws JsonException
     * @throws ValidationException
     * @throws Exception
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
        if ($response->getStatusCode() === 200) {
            $response->getBody()->rewind();
            $contents = $response->getBody()->getContents();
            try {
                /** @var LigaVest[] $data */
                $data = $this->serializer->deserialize(
                    $contents,
                    LigaVest::class . '[]',
                    'json'
                );

                foreach ($data as $vestData) {
                    if (!isset($vests[$vestData->system][$vestData->vestNum])) {
                        continue;
                    }
                    $vest = $vests[$vestData->system][$vestData->vestNum];
                    if ($vest->updatedAt < $vestData->updatedAt) {
                        $vest->status = $vestData->status;
                        $vest->info = $vestData->info;
                        $vest->save();
                    }
                }
            } catch (UnexpectedValueException $e) {
                $response->getBody()->rewind();
                $this->logger->error('Failed to parse GET /api/vests response', context: ['response' => $response->getBody()->getContents()]);
                $this->logger->exception($e);
            }
        } else {
            $response->getBody()->rewind();
            $this->logger->error('Failed to call GET /api/vests', context: ['response' => $response->getBody()->getContents()]);
        }

        // Send all updates to laser liga
        $response = $this->post('/api/vests', ['vest' => $vestsAll]);
        if ($response->getStatusCode() >= 300) {
            $response->getBody()->rewind();
            $this->logger->error('Failed to call POST /api/vests', context: ['response' => $response->getBody()->getContents(), 'request' => $vestsAll]);
        }
        return $response->getStatusCode() < 300;
    }
}
