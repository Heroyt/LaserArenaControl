<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\Info;
use App\GameModels\Game\Game;
use App\Models\MusicMode;
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
use Lsr\Core\App;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

/**
 * Singleton service for handling public API calls
 */
class LigaApi
{

	public static LigaApi $instance;

	public Client  $client;
	private Logger $logger;

	public function __construct(
		public string $url,
		public string $apiKey,
	) {
		$this->logger = new Logger(LOG_DIR, 'ligaApi');
		$this->makeClient();
	}

	private function makeClient() : void {
		// Add logging to handler and set handler to cUrl
		$stack = new HandlerStack();
		$stack->setHandler(new CurlHandler(['handle_factory' => new CurlFactory(99)]));
		$stack->push(Middleware::log($this->logger, new MessageFormatter(App::isProduction() ? MessageFormatter::CLF : MessageFormatter::DEBUG)));

		// Initialize client
		$this->client = new Client([
																 'handler'         => $stack,
																 'base_uri'        => trailingSlashIt($this->url).'api/',
																 'timeout'         => 60.0, // 1 minute
																 'allow_redirects' => true,
																 'headers'         => [
																	 'Accept'        => 'application/json',
																	 'Authorization' => 'Bearer '.$this->apiKey,
																 ]
															 ]);
	}

	/**
	 * @return LigaApi
	 */
	public static function getInstance() : LigaApi {
		if (!isset(self::$instance)) {
			/** @var string $url */
			$url = Info::get('liga_api_url', '');
			/** @var string $key */
			$key = Info::get('liga_api_key', '');
			self::$instance = new self($url, $key);
		}
		return self::$instance;
	}

	/**
	 * Send a GET request to the liga API with all necessary settings, headers, etc.
	 *
	 * @param string                   $path
	 * @param array<string,mixed>|null $params
	 * @param array<string,mixed>      $config
	 *
	 * @return ResponseInterface
	 * @throws GuzzleException
	 */
	public function get(string $path, ?array $params = null, array $config = []) : ResponseInterface {
		$this->makeClient();
		if (isset($params)) {
			$config['query'] = $params;
		}
		return $this->client->get($path, $config);
	}

	/**
	 * Synchronize games to public API
	 *
	 * @param string     $system
	 * @param Game[]     $games
	 * @param float|null $timeout
	 * @param bool       $recreateClient
	 *
	 * @return bool
	 * @throws JsonException
	 * @post All finished games will be sent to public
	 */
	public function syncGames(string $system, array $games, ?float $timeout = null, bool $recreateClient = false) : bool {
		if ($recreateClient) {
			$this->makeClient();
		}

		$playerProvider = App::getContainer()->getByType(PlayerProvider::class);
		if (!isset($playerProvider)) {
			$playerProvider = new PlayerProvider($this);
		}

		// Validate each game
		$gamesData = [];
		foreach ($games as $key => $game) {
			if ($game::SYSTEM !== $system) {
				throw new InvalidArgumentException('Game #'.$key.' (code: '.$game->code.') is not an '.$system.' game.');
			}
			// Remove unfinished games
			if ($game->isFinished()) {
				bdump($game);

				// Check assigned users
				try {
					// Get user data from game
					$response = $this->client->get('games/'.$game->code.'/users');
					if ($response->getStatusCode() === 200) {
						$response->getBody()->rewind();
						/** @var array<int,array{id:int,nickname:string,code:string,arena:int,email:string,stats:array{rank:int,gamesPlayed:int,arenasPlayed:int},connections:array{type:string,identifier:string}[]}> $users */
						$users = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
						bdump($users);
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
									bdump($player);
								}
							}
						}
					}
				} catch (GuzzleException|ModelNotFoundException|ValidationException|JsonException) {
				}

				bdump($game);
				$gamesData[] = $game;
			}
		}

		// Build a request
		try {
			$config = [
				'json' => ['system' => $system, 'games' => $gamesData],
			];
			if (isset($timeout)) {
				$config['timeout'] = $timeout;
			}

			$response = $this->client->post('games', $config);
			if ($response->getStatusCode() !== 200) {
				$this->logger->error('Request failed: '.json_encode($response->getBody()->getContents(), JSON_THROW_ON_ERROR));
				return false;
			}
		} catch (GuzzleException $e) {
			/* @phpstan-ignore-next-line */
			$this->logger->exception($e);
			return false;
		}
		return true;
	}

	/**
	 * Send a POST request to the liga API with all necessary settings, headers, etc.
	 *
	 * @param string              $path
	 * @param array|object|null   $data
	 * @param array<string,mixed> $config
	 *
	 * @return ResponseInterface
	 * @throws GuzzleException
	 */
	public function post(string $path, array|object|null $data = null, array $config = []) : ResponseInterface {
		$this->makeClient();
		if (isset($data)) {
			$config['json'] = $data;
		}
		return $this->client->post($path, $config);
	}

	public function syncMusicMode(MusicMode $mode) : bool {
		try {
			if (!$mode->public) {
				$response = $this->client->delete('music/'.$mode->id);
				$response->getBody()->rewind();
				$body = $response->getBody()->getContents();
				if ($response->getStatusCode() !== 200) {
					$this->logger->error('Music delete failed: '.$body);
					return false;
				}
				return true;
			}

			$response = $this->client->post('music', [
				'json' => [
					'music' => [
						[
							'id'           => $mode->id,
							'name'         => $mode->name,
							'order'        => $mode->order,
							'previewStart' => $mode->previewStart,
						],
					],
				]
			]);
			$response->getBody()->rewind();
			$body = $response->getBody()->getContents();
			if ($response->getStatusCode() !== 200) {
				$this->logger->error('Music sync failed: '.$body);
				return false;
			}

			$response = $this->client->post('music/'.$mode->id.'/upload', [
				'multipart' => [
					[
						'name'     => 'media',
						'contents' => Utils::tryFopen($mode->fileName, 'r'),
					]
				]
			]);
			$response->getBody()->rewind();
			$body = $response->getBody()->getContents();
			if ($response->getStatusCode() !== 200) {
				$this->logger->error('Music upload failed: '.$body);
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
	public function syncMusicModes() : bool {
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
				'id'           => $mode->id,
				'name'         => $mode->name,
				'order'        => $mode->order,
				'previewStart' => $mode->previewStart,
			];
		}

		try {
			$response = $this->client->post('music', [
				'json' => [
					'music' => $data,
				]
			]);
			$response->getBody()->rewind();
			$body = $response->getBody()->getContents();
			if ($response->getStatusCode() !== 200) {
				$this->logger->error('Music sync failed: '.$body);
				return false;
			}

			foreach ($private as $id) {
				$this->client->deleteAsync('music/'.$id);
			}

			// Upload files
			foreach ($musicModes as $mode) {
				if (!$mode->public) {
					continue;
				}
				$response = $this->client->post('music/'.$mode->id.'/upload', [
					'multipart' => [
						[
							'name'     => 'media',
							'contents' => Utils::tryFopen($mode->fileName, 'r'),
						]
					]
				]);
				$response->getBody()->rewind();
				$body = $response->getBody()->getContents();
				if ($response->getStatusCode() !== 200) {
					$this->logger->error('Music upload failed: '.$body);
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
	 * @param bool $remake
	 *
	 * @return Client
	 */
	public function getClient(bool $remake = false) : Client {
		if (!isset($this->client) || $remake) {
			$this->makeClient();
		}
		return $this->client;
	}

}