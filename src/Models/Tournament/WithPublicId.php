<?php

namespace App\Models\Tournament;

trait WithPublicId
{

	public ?int $idPublic = null;

	/** @var array<int,static>  */
	protected static array $publicIdMap = [];

	/**
	 * @param int $publicId
	 * @return static|null
	 * @post Entity is cached in static array
	 */
	public static function getByPublicId(int $publicId) : ?static {
		static::$publicIdMap[$publicId] ??=  static::query()->where('[id_public] = %i', $publicId)->first();
		return static::$publicIdMap[$publicId];
	}

	public function save() : bool {
		$success = parent::save();
		if ($this->idPublic !== null) {
			static::$publicIdMap[$this->idPublic] = $this;
		}
		return $success;
	}

}