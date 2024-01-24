<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Tools;

use App\Core\App;
use App\GameModels\Game\Game;
use App\Services\PlayerProvider;
use App\Tools\Interfaces\ResultsParserInterface;
use LAC\Modules\Core\ResultParserExtensionInterface;
use Lsr\Exceptions\FileException;

/**
 * Abstract base for any result parser class
 */
abstract class AbstractResultsParser implements ResultsParserInterface
{

	protected string $fileContents;

	protected array $matches = [];

	/**
	 * @param string $fileName
	 *
	 * @throws FileException
	 */
	public function __construct(
		protected string                  $fileName,
		protected readonly PlayerProvider $playerProvider,
	) {
		if (!file_exists($this->fileName) || !is_readable($this->fileName)) {
			throw new FileException('File "' . $this->fileName . '" does not exist or is not readable');
		}
		$contents = file_get_contents($this->fileName);
		if ($contents === false) {
			throw new FileException('File "' . $this->fileName . '" read failed');
		}
		$this->fileContents = utf8_encode($contents);
	}

	/**
	 * @return string
	 */
	public function getFileContents(): string {
		return $this->fileContents;
	}

	/**
	 * @return iterable<string>
	 */
	public function getFileLines(): iterable {
		$separator = "\r\n";
		/** @var string|false $line */
		$line = strtok($this->getFileContents(), $separator);
		while ($line !== false) {
			yield $line;
			$line = strtok($separator);
		}
	}

	/**
	 * @param string $pattern
	 * @return string[][]
	 */
	public function matchAll(string $pattern): array {
		if (isset($this->matches[$pattern])) {
			return $this->matches[$pattern];
		}
		preg_match_all($pattern, $this->getFileContents(), $matches);
		$this->matches[$pattern] = $matches;
		return $matches;
	}

	/**
	 * @param Game $game
	 * @param array<string, mixed> $meta
	 * @return void
	 */
	protected function processExtensions(Game $game, array $meta): void {
		$extensions = App::getContainer()->findByType(ResultParserExtensionInterface::class);
		foreach ($extensions as $extensionName) {
			/** @var ResultParserExtensionInterface $extensions */
			$extensions = App::getService($extensionName);
			$extensions->parse($game, $meta, $this);
		}
	}

}