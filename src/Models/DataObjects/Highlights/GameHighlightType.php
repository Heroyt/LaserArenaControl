<?php

namespace App\Models\DataObjects\Highlights;

use Exception;
use OpenApi\Attributes as OA;

/**
 * @method static GameHighlightType from(string $type)
 * @method static GameHighlightType|null tryFrom(string $type)
 * @property string $value
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

    public function getIcon() : string {
        return match ($this) {
            self::TROPHY       => 'trophy',
            self::OTHER        => 'star',
            self::ALONE_STATS  => 'run',
            self::HITS         => 'gun',
            self::DEATHS       => 'skull',
            self::USER_AVERAGE => throw new Exception('To be implemented'),
        };
    }

}
