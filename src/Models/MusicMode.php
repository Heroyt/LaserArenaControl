<?php

namespace App\Models;

use Lsr\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_music')]
class MusicMode extends Model
{

	public const TABLE = 'music';

	#[Required]
	#[StringLength(1, 20)]
	public string $name;
	#[Required]
	public string $fileName = '';
	public int    $order    = 0;
	/** @var int Preview start time in seconds */
	public int $previewStart = 0;
	/** @var bool If the music mode should be synchronized and shown publically */
	public bool $public = true;

	/**
	 * @return MusicMode[]
	 * @throws ValidationException
	 */
	public static function getAll() : array {
		return self::query()->orderBy('order')->get();
	}

	public function getMediaUrl() : string {
		return str_replace(ROOT, App::getUrl(), $this->fileName);
	}

	public function getFormattedPreviewStart() : string {
		return floor($this->previewStart / 60).':'.str_pad((string) ($this->previewStart % 60), 2, '0', STR_PAD_LEFT);
	}

	public function setPreviewStartFromFormatted(string $formatted) : MusicMode {
		$this->previewStart = 0;
		/** @var int[] $exploded */
		$exploded = array_reverse(array_map(static fn(string $part) => (int) trim($part), explode(':', $formatted)));
		$multiplier = 1;
		foreach ($exploded as $part) {
			$this->previewStart += $part * $multiplier;
			$multiplier *= 60;
		}
		return $this;
	}

}