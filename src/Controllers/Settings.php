<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Exceptions\ValidationException;
use App\Models\Factory\GameModeFactory;
use App\Models\Game\PrintStyle;
use Dibi\DriverException;
use Dibi\Exception;

class Settings extends Controller
{

	protected string $title = 'Settings';

	public function show() : void {
		$this->view('pages/settings/index');
	}

	public function modes() : void {
		$this->params['modes'] = GameModeFactory::getAll();
		$this->view('pages/settings/modes');
	}

	public function savePrint(Request $request) : void {
		if ($this->validatePrint($request)) {
			try {
				DB::getConnection()->begin();

				// Delete all styles
				DB::delete(PrintStyle::TABLE, ['1=1']);
				DB::resetAutoIncrement(PrintStyle::TABLE);

				$printDir = 'assets/images/print/';

				foreach ($_POST['styles'] ?? [] as $key => $info) {
					$style = new PrintStyle();
					$style->name = $info['name'];
					$style->colorPrimary = $info['primary'];
					$style->colorDark = $info['dark'];
					$style->colorLight = $info['light'];
					$style->bg = $info['original-background'] ?? '';
					bdump($_POST);
					bdump($_FILES);
					if (!empty($_FILES['styles']['name'][$key]['background'])) {
						$name = $printDir.basename($_FILES['styles']['name'][$key]['background']);
						$imageFileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
						$check = getimagesize($_FILES['styles']["tmp_name"][$key]['background']);
						if ($check !== false) {
							if (in_array($imageFileType, ['jpg', 'jpeg', 'png'], true)) {
								if (move_uploaded_file($_FILES['styles']["tmp_name"][$key]['background'], ROOT.$name)) {
									$style->bg = $name;
								}
								else {
									$request->passErrors[] = lang('File upload failed.', context: 'errors');
								}
							}
							else {
								$request->passErrors[] = lang('File must be an image.', context: 'errors');
							}
						}
						else {
							$request->passErrors[] = lang('File upload failed.', context: 'errors');
						}
					}
					$style->save();
				}

				DB::getConnection()->commit();
			} catch (Exception|DriverException $e) {
				DB::getConnection()->rollback();
				$request->passErrors[] = lang('Database error occurred.', context: 'errors');
			} catch (ValidationException $e) {
				DB::getConnection()->rollback();
				$request->passErrors[] = lang('Validation error:', context: 'errors').' '.$e->getMessage();
			}
		}
		App::redirect('settings-print', $request);
	}

	private function validatePrint(Request $request) : bool {
		return count($request->passErrors) === 0;
	}

	public function print() : void {
		$this->params['styles'] = PrintStyle::getAll();
		$this->view('pages/settings/print');
	}

}