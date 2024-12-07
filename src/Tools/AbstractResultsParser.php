<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools;

use App\Core\App;
use App\GameModels\Game\Game;
use App\Services\LaserLiga\PlayerProvider;
use App\Tools\Interfaces\ResultsParserInterface;
use LAC\Modules\Core\ResultParserExtensionInterface;
use Lsr\Exceptions\FileException;

/**
 * Abstract base for any result parser class
 *
 * @template G of Game
 * @implements ResultsParserInterface<G>
 */
abstract class AbstractResultsParser implements ResultsParserInterface
{
    /** @var array<string,string[][]> */
    protected array $matches = [];
    protected string $fileName = '';
    protected string $fileContents = '';

    public function __construct(
        protected readonly PlayerProvider $playerProvider,
    ) {
    }

    /**
     * @return iterable<string>
     */
    public function getFileLines(): iterable {
        $separator = "\r\n";
        $line = strtok($this->getFileContents(), $separator);
        while ($line !== false) {
            yield $line;
            $line = strtok($separator);
        }
    }

    /**
     * @return string
     */
    public function getFileContents(): string {
        return $this->fileContents;
    }

    /**
     * @param  string  $pattern
     *
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
     * @param  string  $fileName
     *
     * @return $this
     * @throws FileException
     */
    public function setFile(string $fileName): static {
        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new FileException('File "' . $fileName . '" does not exist or is not readable');
        }

        $this->fileName = $fileName;

        $contents = file_get_contents($this->fileName);
        if ($contents === false) {
            throw new FileException('File "' . $this->fileName . '" read failed');
        }
        $this->fileContents = mb_convert_encoding($contents, 'UTF-8');
        $this->matches = [];
        return $this;
    }

    /**
     * @return $this
     */
    public function setContents(string $contents): static {
        $this->fileContents = $contents;
        $this->matches = [];
        return $this;
    }

    /**
     * @param  G  $game
     * @param  array<string, mixed>  $meta
     *
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
