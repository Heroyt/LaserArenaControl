<?php

namespace App\Controllers;

use App\Models\Table;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Logging\Exceptions\DirectoryCreationException;

class Tables extends Controller
{

	#[Post('tables/{id}/clean')]
	public function cleanTable(Request $request) : never {
		try {
			$table = Table::get((int) ($request->params['id'] ?? 0));
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Model not found', 'exception' => $e->getMessage()], 404);
		}

		if (!$table->clean()) {
			$this->respond(['error' => 'Clean failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	#[Get('tables/{id}')]
	public function get(Request $request) : never {
		try {
			$table = Table::get((int) ($request->params['id'] ?? 0));
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Model not found', 'exception' => $e->getMessage()], 404);
		}
		$this->respond($table);
	}

	#[Get('tables')]
	public function getAll() : never {
		$this->respond(['tables' => array_values(Table::getAll())]);
	}

}