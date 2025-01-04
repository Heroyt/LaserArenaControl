<?php

namespace App\Models\Simple;

use Dibi\Row;
use Lsr\Orm\Interfaces\InsertExtendInterface;

/**
 *
 */
class Grid implements InsertExtendInterface
{
    public function __construct(
      public int $row = 1,
      public int $col = 1,
      public int $width = 1,
      public int $height = 1,
    ) {}

    /**
     * @inheritDoc
     */
    public static function parseRow(Row $row) : ?static {
        /** @phpstan-ignore-next-line */
        return new self(
          (int) ($row->grid_row ?? 1),
          (int) ($row->grid_col ?? 1),
          (int) ($row->grid_width ?? 1),
          (int) ($row->grid_height ?? 1),
        );
    }

    /**
     * @inheritDoc
     */
    public function addQueryData(array &$data) : void {
        $data['grid_row'] = $this->row;
        $data['grid_col'] = $this->col;
        $data['grid_width'] = $this->width;
        $data['grid_height'] = $this->height;
    }
}
