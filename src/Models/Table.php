<?php

namespace App\Models;

use App\Models\Simple\Grid;
use DateTimeInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\OneToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_table')]
class Table extends Model
{

	public const TABLE = 'tables';

	public string $name;

	#[Instantiate]
	public Grid $grid;

	#[OneToOne]
	public ?GameGroup $group = null;

	/**
	 * Create a group
	 *
	 * @param bool                   $overwrite
	 * @param DateTimeInterface|null $date
	 *
	 * @return GameGroup
	 * @throws ValidationException
	 */
	public function createGroup(bool $overwrite = false, ?DateTimeInterface $date = null) : GameGroup {
		if (!$overwrite && isset($this->group)) {
			// Prevent creating multiple groups when there is already one
			return $this->group;
		}

		if ($overwrite && isset($this->group)) {
			$this->clean(); // Close the existing group
		}

		$this->group = new GameGroup();
		$this->group->name = sprintf(
			lang('Stůl %s', context: 'tables').' - %s',
			$this->name,
			isset($date) ? $date->format('d.m.Y H:i') : date('d.m.Y H:i')
		);
		$this->group->active = true;
		$this->group->save();

		return $this->group;
	}

	/**
	 * "Clean" the table -> Remove its group
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function clean() : bool {
		if (isset($this->group)) {
			$this->group->active = false;
			$this->group->save();
		}
		$this->group = null;
		return $this->save();
	}

}