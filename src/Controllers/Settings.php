<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Vest;
use DateTime;
use Dibi\DriverException;
use Dibi\Exception;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;

/**
 *
 */
class Settings extends Controller
{

	protected string $title = 'Settings';

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function show() : void {
		$this->view('pages/settings/index');
	}

	/**
	 * @return void
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 */
	public function vests() : void {
		$vests = Vest::getAll();
		$this->params['vests'] = [];
		foreach (GameFactory::getSupportedSystems() as $system) {
			/** @phpstan-ignore-next-line */
			$this->params['vests'][$system] = [];
		}
		foreach ($vests as $vest) {
			/** @phpstan-ignore-next-line */
			$this->params['vests'][$vest->system][] = $vest;
		}
		$this->view('pages/settings/vests');
	}

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function gate() : void {
		$this->view('pages/settings/gate');
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveGate(Request $request) : void {
		try {
			if (isset($request->post['timer_offset'])) {
				Info::set('timer-offset', (int) $request->post['timer_offset']);
			}
			if (isset($request->post['timer_show'])) {
				Info::set('timer_show', (int) $request->post['timer_show']);
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			$this->respond([
											 'success' => empty($request->passErrors),
											 'errors'  => $request->passErrors,
										 ]);
		}
		App::redirect('settings-gate', $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveVests(Request $request) : void {
		try {
			foreach ($request->post['vest'] ?? [] as $id => $info) {
				DB::update(Vest::TABLE, $info, ['%n = %i', Vest::getPrimaryKey(), $id]);
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			$this->respond([
											 'success' => empty($request->passErrors),
											 'errors'  => $request->passErrors,
										 ], empty($request->passErrors) ? 200 : 400);
		}
		App::redirect('settings', $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveGeneral(Request $request) : void {
		try {
			if (isset($request->post['api_key'])) {
				Info::set('liga_api_key', $request->post['api_key']);
			}
			if (isset($request->post['lmx_ip'])) {
				Info::set('lmx_ip', $request->post['lmx_ip']);
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			$this->respond([
											 'success' => empty($request->passErrors),
											 'errors'  => $request->passErrors,
										 ]);
		}
		App::redirect('settings', $request);
	}

	/**
	 * @throws DriverException
	 * @throws JsonException
	 */
	public function savePrint(Request $request) : void {
		if ($this->validatePrint($request)) {
			try {
				DB::getConnection()->begin();

				// Save default template
				Info::set('default_print_template', $_POST['default-template'] ?? 'default');

				// Delete all dates
				DB::delete(PrintStyle::TABLE.'_dates', ['1=1']);

				// Delete all styles
				DB::delete(PrintStyle::TABLE, ['1=1']);
				DB::resetAutoIncrement(PrintStyle::TABLE);

				$printDir = 'assets/images/print/';

				/**
				 * @var array{name:string,primary:string,dark:string,light:string,original-background?:string,original-background-landscape?:string} $info
				 */
				foreach ($_POST['styles'] ?? [] as $key => $info) {
					$style = new PrintStyle();
					$style->id = $key;
					$style->name = $info['name'];
					$style->colorPrimary = $info['primary'];
					$style->colorDark = $info['dark'];
					$style->colorLight = $info['light'];
					$style->bg = $info['original-background'] ?? '';
					$style->bgLandscape = $info['original-background-landscape'] ?? '';
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
										$style->bgLandscape = $name;
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
					$style->default = $style->id === (int) ($_POST['default-style'] ?? 0);
					$style->insert();
				}

				/**
				 * @var array{style:int,dates:string} $info
				 */
				foreach ($_POST['dateRange'] ?? [] as $info) {
					preg_match_all('/(\d{2}\.\d{2}\.\d{4})/', $info['dates'], $matches);
					$dateFrom = new DateTime($matches[0][1] ?? '');
					$dateTo = new DateTime($matches[1][1] ?? '');
					DB::insert(PrintStyle::TABLE.'_dates', [
						PrintStyle::getPrimaryKey() => $info['style'],
						'date_from'                 => $dateFrom,
						'date_to'                   => $dateTo,
					]);
				}

				DB::getConnection()->commit();
			} catch (Exception|DriverException $e) {
				DB::getConnection()->rollback();
				$request->passErrors[] = lang('Database error occurred.', context: 'errors').' '.$e->getMessage();
			} catch (ValidationException $e) {
				DB::getConnection()->rollback();
				$request->passErrors[] = lang('Validation error:', context: 'errors').' '.$e->getMessage();
			}
		}
		if ($request->isAjax()) {
			$this->respond([
											 'success' => empty($request->passErrors),
											 'errors'  => $request->passErrors,
										 ], empty($request->passErrors) ? 200 : 500);
		}
		App::redirect('settings-print', $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return bool
	 */
	private function validatePrint(Request $request) : bool {
		// TODO: Actually validate request..
		return count($request->passErrors) === 0;
	}

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 */
	public function print() : void {
		$this->params['styles'] = PrintStyle::getAll();
		$this->params['templates'] = PrintTemplate::getAll();
		$this->params['defaultTemplateId'] = Info::get('default_print_template', 'default');
		$this->params['dates'] = PrintStyle::getAllStyleDates();
		$this->view('pages/settings/print');
	}

}