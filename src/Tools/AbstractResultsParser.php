<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Tools;

use App\Tools\Interfaces\ResultsParserInterface;
use Lsr\Exceptions\FileException;

/**
 * Abstract base for any result parser class
 */
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