<?php

namespace App\Controllers\Cli;

use App\Services\VersionService;
use Lsr\Core\Controllers\CliController;
use Lsr\Helpers\Cli\Colors;
use Lsr\Helpers\Cli\Enums\ForegroundColors;

class Version extends CliController
{

	public function __construct(
		private readonly VersionService $versionService,
	) {
	}

	public function getVersion() : void {
		echo 'Current version: '.Colors::color(ForegroundColors::LIGHT_BLUE).$this->versionService->getCurrentVersion().Colors::reset().PHP_EOL;
	}

	public function isUpdateAvailable() : void {
		$currentVersion = $this->versionService->getCurrentVersion();
		$availableVersion = $this->versionService->getLastAvailableVersion();

		if ($currentVersion === 'dev') {
			echo 'Currently on DEV version. Update is unnecessary.'.PHP_EOL;
		}
		else if (version_compare($currentVersion, $availableVersion) < 0) {
			echo Colors::color(ForegroundColors::GREEN).'Update available!'.Colors::reset().PHP_EOL.$availableVersion.PHP_EOL;
		}
		else {
			echo 'Running the latest version.'.PHP_EOL;
		}
	}

	public function list() : void {
		$versions = $this->versionService->getAvailableVersions();
		$current = $this->versionService->getCurrentVersion();
		foreach ($versions as $key => $version) {
			if (version_compare($version, $current) === 0) {
				echo '* ';
			}
			if ($key === 0) {
				echo Colors::color(ForegroundColors::LIGHT_BLUE);
			}
			echo $version;
			if ($key === 0) {
				echo Colors::reset();
			}
			echo PHP_EOL;
		}
	}

}