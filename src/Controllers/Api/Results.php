<?php

namespace App\Controllers\Api;

use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Player;
use App\Services\ImportService;
use App\Services\PlayerProvider;
use App\Tools\Evo5\ResultsParser;
use Exception;
use JsonException;
use Lsr\Core\Constants;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Exceptions\ArchiveCreationException;
use Lsr\Logging\Logger;
use Throwable;
use ZipArchive;

class Results extends ApiController
{

	public function __construct(
		Latte                           $latte,
		private readonly PlayerProvider $playerProvider,
		private readonly ImportService $importService,
	) {
		parent::__construct($latte);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public function import(Request $request) : void {
		$resultsDir = $request->post['dir'] ?? '';
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}

		$this->importService->import($resultsDir, $this);
	}

	/**
	 * Import one game (again)
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function importGame(Request $request) : void {
		$logger = new Logger(LOG_DIR.'results/', 'import');
		$resultsDir = trailingSlashIt($request->post['dir'] ?? DEFAULT_RESULTS_DIR);

		try {
			$game = GameFactory::getByCode($request->params['game'] ?? '');
		} catch (Throwable $e) {
			$this->respond(['error' => 'Error while getting the game by code.', 'exception' => $e->getMessage()], 500);
		}
		if (!isset($game)) {
			$this->respond(['error' => 'Unknown game.'], 404);
		}

		$game->clearCache();

		if (empty($game->fileNumber)) {
			$this->respond(['error' => 'Cannot get game file number.', 'game' => $game], 404);
		}
		$files = glob($resultsDir.str_pad($game->fileNumber, 4, '0', STR_PAD_LEFT).'*.game');
		if (empty($files)) {
			$this->respond(['error' => 'Cannot find game file.', 'path' => $resultsDir.$game->fileNumber.'*.game'], 404);
		}
		if (count($files) > 1) {
			$this->respond(['error' => 'Found more than one suitable game file.'], 500);
		}

		try {
			$logger->info('Importing file: '.$files[0]);
			$parser = new ResultsParser($files[0], $this->playerProvider);
			$game = $parser->parse();

			$now = time();

			if (!isset($game->importTime)) {
				$logger->debug('Game is not finished');

				// The game is not finished and does not contain any results
				// It is either:
				// - an old, un-played game
				// - freshly loaded game
				// - started and not finished game
				// An old game should be ignored, the other 2 cases should be logged and an event should be sent.
				// But only the latest game should be considered

				// The game is started
				if ($game->started && isset($game->fileTime) && ($now - $game->fileTime->getTimestamp()) <= Constants::GAME_STARTED_TIME) {
					$logger->debug('Game is started');
				}
				// The game is loaded
				if (!$game->started && isset($game->fileTime) && ($now - $game->fileTime->getTimestamp()) <= Constants::GAME_LOADED_TIME) {
					$logger->debug('Game is loaded');
				}
				$this->respond(['error' => 'Game is not finished'], 400);
			}

			// Check players
			$null = true;
			/** @var Player $player */
			foreach ($game->getPlayers() as $player) {
				if ($player->score !== 0 || $player->shots !== 0) {
					$null = false;
					break;
				}
			}
			if ($null) {
				$logger->warning('Game is empty');
				// Empty game - no shots, no hits, etc..
				$this->respond(['error' => 'Game is empty'], 400);
			}

			if (!$game->save()) {
				throw new ResultsParseException('Failed saving game into DB.');
			}
		} catch (Exception $e) {
			$this->respond(['error' => 'Error while parsing the game file.', 'exception' => $e->getMessage()], 500);
		}
		$this->respond(['success' => true]);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function getLastGameFile(Request $request) : never {
		$resultsDir = urldecode($request->get['dir'] ?? '');
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}
		$resultsDir = trailingSlashIt($resultsDir);
		/** @var string[] $resultFiles */
		$resultFiles = glob(ROOT.$resultsDir.'*.game');
		// Sort by time
		usort($resultFiles, static function(string $a, string $b) {
			return filemtime($b) - filemtime($a);
		});
		/** @var string $resultsContent1 */
		$resultsContent1 = file_get_contents($resultFiles[0]);
		/** @var string $resultsContent2 */
		$resultsContent2 = file_get_contents($resultFiles[1]);
		$this->respond(['files' => $resultFiles, 'contents1' => utf8_encode($resultsContent1), 'contents2' => utf8_encode($resultsContent2)]);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws ArchiveCreationException
	 * @throws JsonException
	 */
	public function downloadLastGameFiles(Request $request) : never {
		/** TODO: Secure.. this is really bad.. and would allow for downloading of any files */
		$resultsDir = urldecode($request->get['dir'] ?? '');
		if (empty($resultsDir)) {
			$this->respond(['error' => 'Missing required argument "dir". Valid results directory is expected.'], 400);
		}
		$resultsDir = trailingSlashIt($resultsDir);
		/** @var string[] $resultFiles */
		$resultFiles = glob(ROOT.$resultsDir.'*.game');

		$archive = new ZipArchive();
		$test = $archive->open(TMP_DIR.'games.zip', ZipArchive::CREATE); // Create or open a zip file
		if ($test !== true) {
			throw new ArchiveCreationException($test);
		}

		foreach ($resultFiles as $file) {
			$fileName = str_replace(ROOT.$resultsDir, '', $file);
			$archive->addFile($file, $fileName);
		}
		$archive->close();

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="games.zip"');
		header("Content-Length: ".filesize(TMP_DIR.'games.zip'));
		http_response_code(200);
		readfile(TMP_DIR.'games.zip');
		exit;
	}

}