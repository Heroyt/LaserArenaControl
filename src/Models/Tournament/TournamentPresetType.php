<?php

namespace App\Models\Tournament;

/**
 * @property string $value
 * @method static TournamentPresetType from(string $value)
 * @method static TournamentPresetType|null tryFrom(string $value)
 * @method static TournamentPresetType[] cases()
 */
enum TournamentPresetType: string
{

	case ROUND_ROBIN = 'rr';
	case TWO_GROUPS_ROBIN = '2grr';
	case TWO_GROUPS_ROBIN_10 = '2grr10';

	public function getReadableValue(): string {
		return match ($this) {
			self::ROUND_ROBIN => lang('Každý s každým', context: 'tournament.presets'),
			self::TWO_GROUPS_ROBIN => lang('Každý s každým na poloviny', context: 'tournament.presets'),
			self::TWO_GROUPS_ROBIN_10 => lang('Každý s každým na poloviny - 10 týmů', context: 'tournament.presets'),
		};
	}

}
