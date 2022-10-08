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
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Logger;

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
		// Validate each game
		$gamesData = [];
		foreach ($games as $key => $game) {
			if ($game::SYSTEM !== $system) {
				throw new InvalidArgumentException('Game #'.$key.' (code: '.$game->code.') is not an '.$system.' game.');
			}
			// Remove unfinished games
			if ($game->isFinished()) {
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

}