<?php

namespace App\Gate\Screens;

use App\Gate\Settings\AnimationType;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\ImageScreenType;
use App\Gate\Settings\YoutubeSettings;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<YoutubeSettings>
 */
class YoutubeScreen extends GateScreen implements WithSettings
{
	private YoutubeSettings $settings;

	/**
	 * @inheritDoc
	 */
	public static function getName() : string {
		return lang('Youtube video', context: 'gate.screens');
	}

	/**
	 * @inheritDoc
	 */
	public static function getDiKey() : string {
		return 'gate.screens.youtube';
	}

	/**
	 * @inheritDoc
	 */
	public static function getSettingsForm() : string {
		return 'gate/settings/youtube.latte';
	}

	/**
	 * @inheritDoc
	 */
	public static function buildSettingsFromForm(array $data) : GateSettings {
		bdump($data);
		bdump(ImageScreenType::tryFrom($data['type'] ?? ''));
		return new YoutubeSettings(
			self::convertToEmbedUrl($data['url'] ?? ''),
			ImageScreenType::tryFrom($data['type'] ?? '') ?? ImageScreenType::CENTER,
			AnimationType::tryFrom($data['animation'] ?? '') ?? AnimationType::FADE,
		);
	}

	private static function convertToEmbedUrl(string $url) : string {
		// Regular expression to match YouTube URL formats
		$pattern = '/^(?:https?:\/\/)?(?:www\.)?(?:youtube(?:-nocookie)?\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})$/';

		// If URL matches pattern, extract video ID and return embed URL
		if (preg_match($pattern, $url, $matches) !== false) {
			$videoID = $matches[1];
			bdump($videoID);
			return 'https://www.youtube-nocookie.com/embed/'.$videoID;
		}

		bdump('Invalid URL: '.$url);
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function run() : ResponseInterface {
		return $this->view(
			'gate/screens/youtube',
			[
				'settings' => $this->getSettings(),
				'url'      => $this->getSettings()->url,
				'addCss'   => ['gate/youtube.css'],
				'addJs'    => ['gate/youtube.js'],
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSettings() : YoutubeSettings {
		if (!isset($this->settings)) {
			$this->settings = new YoutubeSettings('');
		}
		return $this->settings;
	}

	/**
	 * @inheritDoc
	 */
	public function setSettings(GateSettings $settings) : static {
		bdump($settings);
		$this->settings = $settings;
		return $this;
	}
}