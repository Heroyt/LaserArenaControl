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
use App\Models\GameGroup;
use App\Services\FeatureConfig;
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
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Files\UploadedFile;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;

/**
 *
 */
class Settings extends Controller
{

	protected string $title = 'Settings';

	public function __construct(
		private readonly FeatureConfig $featureConfig,
		Latte                          $latte
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['featureConfig'] = $this->featureConfig;
	}

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function show(): void {
		$this->view('pages/settings/index');
	}

	/**
	 * @return void
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 */
	public function vests(): void {
		$vests = Vest::getAll();
		$this->params['vests'] = [];
		foreach (GameFactory::getSupportedSystems() as $system) {
			$this->params['vests'][$system] = [];
		}
		foreach ($vests as $vest) {
			$this->params['vests'][$vest->system][] = $vest;
		}
		$this->view('pages/settings/vests');
	}

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function gate(): void {
		$this->view('pages/settings/gate');
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveGate(Request $request): void {
		try {
			if (isset($request->post['timer_offset'])) {
				Info::set('timer-offset', (int)$request->post['timer_offset']);
			}
			if (isset($request->post['timer_show'])) {
				Info::set('timer_show', (int)$request->post['timer_show']);
			}
			Info::set('timer_on_inactive_screen', !empty($request->post['timer_on_inactive_screen']));
			if (isset($_FILES['background'])) {
				$file = UploadedFile::parseUploaded('background');
				if (isset($file)) {
					// Remove old uploaded files
					foreach (glob(UPLOAD_DIR . 'gate.*') as $old) {
						unlink($old);
					}
					// Save new file
					$file->save(UPLOAD_DIR . 'gate.' . $file->getExtension());
				}
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			$this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
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
	public function saveVests(Request $request): void {
		try {
			foreach ($request->post['vest'] ?? [] as $id => $info) {
				DB::update(Vest::TABLE, $info, ['%n = %i', Vest::getPrimaryKey(), $id]);
				$vest = Vest::get($id);
				$vest->clearCache();
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			$this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
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
	public function saveGeneral(Request $request): void {
		try {
			if (isset($request->post['api_key'])) {
				Info::set('liga_api_key', $request->post['api_key']);
			}
			if (isset($request->post['lmx_ip'])) {
				Info::set('lmx_ip', $request->post['lmx_ip']);
			}
			if (isset($request->post['gates_ips'])) {
				Info::set('gates_ips', array_map('trim', explode(',', $request->post['gates_ips'])));
			}
			if (isset($_FILES['logo'])) {
				$file = UploadedFile::parseUploaded('logo');
				if (isset($file)) {
					// Remove old uploaded files
					foreach (glob(UPLOAD_DIR . 'logo.*') as $old) {
						unlink($old);
					}
					// Save new file
					$file->save(UPLOAD_DIR . 'logo.' . $file->getExtension());
				}
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			$this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
			]);
		}
		App::redirect('settings', $request);
	}

	/**
	 * @throws DriverException
	 * @throws JsonException
	 */
	public function savePrint(Request $request): void {
		if ($this->validatePrint($request)) {
			try {
				DB::getConnection()->begin();

				// Save default template
				Info::set('default_print_template', $_POST['default-template'] ?? 'default');

				// Delete all dates
				DB::delete(PrintStyle::TABLE . '_dates', ['1=1']);

				// Delete all styles
				DB::delete(PrintStyle::TABLE, ['1=1']);
				DB::resetAutoIncrement(PrintStyle::TABLE);

				$printDir = 'assets/images/print/';

				/** @var array<int,array{background:UploadedFile|null,background-landscape:UploadedFile|null}> $uploadedFiles */
				$uploadedFiles = UploadedFile::parseUploadedMultiple('styles');
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
					if (isset($uploadedFiles[$key]['background'])) {
						$this->processPrintFileUpload($uploadedFiles[$key]['background'], $request, $printDir, $style);
					}
					if (isset($uploadedFiles[$key]['background-landscape'])) {
						$this->processPrintFileUpload($uploadedFiles[$key]['background-landscape'], $request, $printDir, $style, true);
					}
					$style->default = $style->id === (int)($_POST['default-style'] ?? 0);
					$style->insert();
				}

				/**
				 * @var array{style:int,dates:string} $info
				 */
				foreach ($_POST['dateRange'] ?? [] as $info) {
					preg_match_all('/(\d{2}\.\d{2}\.\d{4})/', $info['dates'], $matches);
					$dateFrom = new DateTime($matches[0][1] ?? '');
					$dateTo = new DateTime($matches[1][1] ?? '');
					DB::insert(PrintStyle::TABLE . '_dates', [
						PrintStyle::getPrimaryKey() => $info['style'],
						'date_from' => $dateFrom,
						'date_to' => $dateTo,
					]);
				}

				DB::getConnection()->commit();
			} catch (Exception|DriverException $e) {
				DB::getConnection()->rollback();
				$request->passErrors[] = lang('Database error occurred.', context: 'errors') . ' ' . $e->getMessage();
			} catch (ValidationException $e) {
				DB::getConnection()->rollback();
				$request->passErrors[] = lang('Validation error:', context: 'errors') . ' ' . $e->getMessage();
			}
		}
		if ($request->isAjax()) {
			$this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
			], empty($request->passErrors) ? 200 : 500);
		}
		App::redirect('settings-print', $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return bool
	 */
	private function validatePrint(Request $request): bool {
		// TODO: Actually validate request..
		return count($request->passErrors) === 0;
	}

	/**
	 * @param UploadedFile $file
	 * @param Request $request
	 * @param string $printDir
	 * @param PrintStyle $style
	 */
	private function processPrintFileUpload(UploadedFile $file, Request $request, string $printDir, PrintStyle $style, bool $landscape = false): void {
		if ($file->error !== UPLOAD_ERR_OK) {
			$request->passErrors[] = $file->getErrorMessage();
		}
		else {
			$name = $printDir . $file->getBaseName() . '.' . $file->getExtension();
			$check = getimagesize($file->tmpName);
			if ($check !== false) {
				if ($file->validateExtension('jpg', 'jpeg', 'png')) {
					if ($file->save(ROOT . $name)) {
						if ($landscape) {
							$style->bgLandscape = $name;
						}
						else {
							$style->bg = $name;
						}
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

	/**
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public function print(): void {
		$this->params['styles'] = PrintStyle::getAll();
		$this->params['templates'] = PrintTemplate::getAll();
		$this->params['defaultTemplateId'] = Info::get('default_print_template', 'default');
		$this->params['dates'] = PrintStyle::getAllStyleDates();
		$this->view('pages/settings/print');
	}

	#[Get('settings/cache', 'settings-cache')]
	public function cache(): void {
		$this->view('pages/settings/cache');
	}

	public function group(): void {
		$this->params['groupsActive'] = GameGroup::getActive();
		$this->params['groupsInactive'] = GameGroup::query()->where('active = 0')->orderBy('id_group')->desc()->get();

		$this->view('pages/settings/groups');
	}

}