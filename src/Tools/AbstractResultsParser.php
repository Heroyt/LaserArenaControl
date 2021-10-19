<?php

namespace App\Tools;

use App\Exceptions\FileException;
use App\Tools\Interfaces\ResultsParserInterface;

abstract class AbstractResultsParser implements ResultsParserInterface
{

	protected string $fileContents;

	/**
	 * @param string $fileName
	 *
	 * @throws FileException
	 */
	public function __construct(
		protected string $fileName,
	) {
		if (!file_exists($this->fileName) || !is_readable($this->fileName)) {
			throw new FileException('File "'.$this->fileName.'" does not exist or is not readable');
		}
		$this->fileContents = file_get_contents($this->fileName);
	}

}