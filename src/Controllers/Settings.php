<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Exceptions\ValidationException;
use App\Models\Factory\GameModeFactory;
use App\Models\Game\PrintStyle;
use DateTime;
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

				// Delete all dates
				DB::delete(PrintStyle::TABLE.'_dates', ['1=1']);

				// Delete all styles
				DB::delete(PrintStyle::TABLE, ['1=1']);
				DB::resetAutoIncrement(PrintStyle::TABLE);

				$printDir = 'assets/images/print/';

				/**
				 * @var array{name:string,primary:string,dark:string,light:string,original-background:string} $info
				 */
				foreach ($_POST['styles'] ?? [] as $key => $info) {
					$style = new PrintStyle();
					$style->name = $info['name'];
					$style->colorPrimary = $info['primary'];
					$style->colorDark = $info['dark'];
					$style->colorLight = $info['light'];
					$style->bg = $info['original-background'] ?? '';
					$style->bg_landscape = $info['original-background-landscape'] ?? '';
					bdump($_POST);
					bdump($_FILES);
					if (!empty($_FILES['styles']['name'][$key]['background'])) {
						if ($_FILES['styles']['error'][$key]['background'] !== UPLOAD_ERR_OK) {
							$request->passErrors[] = match ($_FILES['styles']['error'][$key]['background']) {
								UPLOAD_ERR_INI_SIZE => lang('Uploaded file is too large', context: 'errors'),
								UPLOAD_ERR_FORM_SIZE => lang('Form size is to large', context: 'errors'),
								UPLOAD_ERR_PARTIAL => lang('The uploaded file was only partially uploaded.', context: 'errors'),
								UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors'),
								default => lang('Error while uploading a file.', context: 'errors'),
							};
						}
						else {
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
					}
					if (!empty($_FILES['styles']['name'][$key]['background-landscape'])) {
						if ($_FILES['styles']['error'][$key]['background-landscape'] !== UPLOAD_ERR_OK) {
							$request->passErrors[] = match ($_FILES['styles']['error'][$key]['background-landscape']) {
								UPLOAD_ERR_INI_SIZE => lang('Uploaded file is too large', context: 'errors'),
								UPLOAD_ERR_FORM_SIZE => lang('Form size is to large', context: 'errors'),
								UPLOAD_ERR_PARTIAL => lang('The uploaded file was only partially uploaded.', context: 'errors'),
								UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors'),
								default => lang('Error while uploading a file.', context: 'errors'),
							};
						}
						else {
							$name = $printDir.basename($_FILES['styles']['name'][$key]['background-landscape']);
							$imageFileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
							$check = getimagesize($_FILES['styles']["tmp_name"][$key]['background-landscape']);
							if ($check !== false) {
								if (in_array($imageFileType, ['jpg', 'jpeg', 'png'], true)) {
									if (move_uploaded_file($_FILES['styles']["tmp_name"][$key]['background-landscape'], ROOT.$name)) {
										$style->bg_landscape = $name;
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
					}
					$style->save();
				}

				/**
				 * @var int                           $key
				 * @var array{style:int,dates:string} $info
				 */
				foreach ($_POST['dateRange'] ?? [] as $key => $info) {
					preg_match_all('/(\d{2}\.\d{2}\.\d{4})/', $info['dates'], $matches);
					$dateFrom = new DateTime($matches[0][1] ?? '');
					$dateTo = new DateTime($matches[1][1] ?? '');
					DB::insert(PrintStyle::TABLE.'_dates', [
						PrintStyle::PRIMARY_KEY => $info['style'],
						'date_from'             => $dateFrom,
						'date_to'               => $dateTo,
					]);
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
		if ($request->isAjax()) {
			$this->ajaxJson([
												'success' => empty($request->passErrors),
												'errors'  => $request->passErrors,
											]);
		}
		App::redirect('settings-print', $request);
	}

	private function validatePrint(Request $request) : bool {
		// TODO: Actually validate request..
		return count($request->passErrors) === 0;
	}

	public function print() : void {
		$this->params['styles'] = PrintStyle::getAll();
		$this->params['dates'] = PrintStyle::getAllStyleDates();
		$this->view('pages/settings/print');
	}

}