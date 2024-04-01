<?php

namespace App\Gate\Logic;

/**
 * @method static ScreenTriggerType[] cases()
 * @method static ScreenTriggerType from(string $value)
 * @method static ScreenTriggerType|null tryFrom(string $value)
 * @property string $value
 */
enum ScreenTriggerType : string
{

	case DEFAULT = 'default';
	case GAME_LOADED = 'game_loaded';
	case GAME_PLAYING = 'game_playing';
	case GAME_ENDED = 'game_ended';

	case CUSTOM = 'custom';

	public function getReadable() : string {
		return match ($this) {
			self::DEFAULT      => lang('Výchozí', context: 'gate-screen-trigger'),
			self::GAME_LOADED  => lang('Hra načtena', context: 'gate-screen-trigger'),
			self::GAME_PLAYING => lang('Hra hraje', context: 'gate-screen-trigger'),
			self::GAME_ENDED   => lang('Hra skončila', context: 'gate-screen-trigger'),
			self::CUSTOM       => lang('Vlastní událost', context: 'gate-screen-trigger'),
		};
	}

	public function getDescription() : string {
		return match ($this) {
			self::DEFAULT      => lang('Obrazovka se zobrazuje jako výchozí, pokud žádná jiná před ní nesplňuje podmínku.',
				context:                 'gate-screen-trigger'),
			self::GAME_LOADED  => lang('Obrazovka se zobrazí, pokud je poslední hra načtená, ale nespuštěná.',
				context:                 'gate-screen-trigger'),
			self::GAME_PLAYING => lang('Obrazovka se zobrazí, pokud poslední hra právě probíhá.',
				context:                 'gate-screen-trigger'),
			self::GAME_ENDED   => lang('Obrazovka se zobrazí, pokud poseldní hra už skončila.',
				context:                 'gate-screen-trigger'),
			self::CUSTOM       => lang('Obrazovka se zobrazí, pokud se vyvolá nějaká manuální událost.',
				context:                 'gate-screen-trigger'),
		};
	}

}
