<?php

namespace App\Models\DataObjects\Highlights;

use OpenApi\Attributes as OA;

/**
 * @method static GameHighlightType from(string $type)
 * @method static GameHighlightType|null tryFrom(string $type)
 */
#[OA\Schema(type: 'string')]
enum GameHighlightType : string
{

	case TROPHY = 'trophy';
	case OTHER = 'other';
	case ALONE_STATS = 'alone';
	case HITS = 'hits';
	case DEATHS = 'deaths';
	case USER_AVERAGE = 'user_average';

	/**
	 * @return class-string<GameHighlight>
	 */
	public function getHighlightClass() : string {
		return match ($this) {
			self::TROPHY => TrophyHighlight::class,
			default      => GameHighlight::class,
		};
	}

}
