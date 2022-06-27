<?php

namespace unit\Logging;

use Lsr\Logging\Logger;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class LoggerTest extends TestCase
{

	private const LOG_DIR = ROOT.'tests/logs/';

	public function setUp() : void {
		parent::setUp();

		// Remove all files
		$this->removeDirRecursive($this::LOG_DIR);
	}

	public function testLog() : void {
		$id = uniqid('', true);
		$file = $this::LOG_DIR.$id.'-'.date('Y-m-d').'.log';
		$logger = new Logger($this::LOG_DIR, $id);

		self::assertFileExists($this::LOG_DIR);

		$content = '['.date('Y-m-d H:i:s').'] DEBUG: Testing message'.PHP_EOL;
		$logger->debug('Testing message');

		self::assertFileExists($file);

		self::assertEquals($content, file_get_contents($file));

		$content .= '['.date('Y-m-d H:i:s').'] ERROR: Testing message 2'.PHP_EOL;
		$logger->error('Testing message 2');
		self::assertEquals($content, file_get_contents($file));
	}

	public function testLogDirs() : void {
		$id = uniqid('', true);
		$file = $this::LOG_DIR.$id.'-'.date('Y-m-d').'.log';
		$logger = new Logger('../../logs/', $id);

		$content = '['.date('Y-m-d H:i:s').'] DEBUG: Testing message'.PHP_EOL;
		$logger->debug('Testing message');

		self::assertFileExists($file);

		self::assertEquals($content, file_get_contents($file));
	}

	public function testArchive() : void {
		$id = uniqid('', true);

		// Create log directory
		$logger = new Logger($this::LOG_DIR, $id);
		self::assertFileExists($this::LOG_DIR);

		$files = [
			$this::LOG_DIR.$id.'-'.date('Y-m-d').'.log',
			$this::LOG_DIR.$id.'-'.date('Y-m-d', strtotime('-1 days')).'.log',
			$this::LOG_DIR.$id.'-'.date('Y-m-d', strtotime('-2 days')).'.log',
			$this::LOG_DIR.$id.'-'.date('Y-m-d', strtotime('-3 days')).'.log', // Should be archived
			$this::LOG_DIR.$id.'-'.date('Y-m-d', strtotime('-4 days')).'.log', // Should be archived
		];
		// Create the temp files and populate them
		foreach ($files as $file) {
			file_put_contents($file, 'TEST');
		}

		// Setup a new logger - should archive older logs
		$logger = new Logger($this::LOG_DIR, $id);
		self::assertFileExists($this::LOG_DIR.$id.'-'.date('Y-m-W').'.zip');

		// Check if the archive contains all the files
		$archive = new ZipArchive();
		$test = $archive->open($this::LOG_DIR.$id.'-'.date('Y-m-W').'.zip');
		self::assertTrue($test, 'Failed opening the zip file');
		foreach (array_slice($files, 2) as $file) {
			self::assertNotFalse($archive->locateName(str_replace($this::LOG_DIR, '', $file)));
		}

		// Add more files to the existing archive
		$files[] = $this::LOG_DIR.$id.'-'.date('Y-m-d', strtotime('-5 days')).'.log'; // Should be archived
		$files[] = $this::LOG_DIR.$id.'-'.date('Y-m-d', strtotime('-6 days')).'.log'; // Should be archived

		// Create the temp files and populate them
		foreach (array_slice($files, -2) as $file) {
			file_put_contents($file, 'TEST');
		}

		// Setup a new logger - should archive older logs
		$logger = new Logger($this::LOG_DIR, $id);
		self::assertFileExists($this::LOG_DIR.$id.'-'.date('Y-m-W').'.zip');

		// Check if the archive contains all the files
		$archive = new ZipArchive();
		$test = $archive->open($this::LOG_DIR.$id.'-'.date('Y-m-W').'.zip');
		self::assertTrue($test, 'Failed opening the zip file');
		foreach (array_slice($files, 2) as $file) {
			self::assertNotFalse($archive->locateName(str_replace($this::LOG_DIR, '', $file)));
		}

	}

	/**
	 * @param string $dir
	 */
	private function removeDirRecursive(string $dir) : void {
		$files = glob($dir.'*');
		foreach ($files as $file) {
			if (is_dir($file)) {
				$this->removeDirRecursive($file);
				rmdir($file);
			}
			else {
				unlink($file);
			}
		}
	}

}
