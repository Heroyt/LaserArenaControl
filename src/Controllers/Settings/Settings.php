<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers\Settings;

use App\Core\App;
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
use Lsr\Core\Controllers\Controller;
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
use Psr\Http\Message\ResponseInterface;

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
	public function show(): ResponseInterface {
		return $this->view('pages/settings/index');
	}

	/**
	 * @return void
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 */
	public function vests(): ResponseInterface {
		$vests = Vest::getAll();
		$this->params['vests'] = [];
		foreach (GameFactory::getSupportedSystems() as $system) {
			$this->params['vests'][$system] = [];
		}
		foreach ($vests as $vest) {
			$this->params['vests'][$vest->system][] = $vest;
		}
		return $this->view('pages/settings/vests');
	}

	/**
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function gate(): ResponseInterface {
		return $this->view('pages/settings/gate');
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveGate(Request $request): ResponseInterface {
		try {
			$offset = $request->getPost('timer_offset');
			if (isset($offset)) {
				Info::set('timer-offset', (int)$offset);
			}
			$show = $request->getPost('timer_show');
			if (isset($show)) {
				Info::set('timer_show', (int)$show);
			}
			Info::set('timer_on_inactive_screen', !empty($request->getPost('timer_on_inactive_screen')));
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
			return $this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
			]);
		}
		return App::redirect('settings-gate', $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveVests(Request $request): ResponseInterface {
		try {
			foreach ($request->getPost('vest', []) as $id => $info) {
				DB::update(Vest::TABLE, $info, ['%n = %i', Vest::getPrimaryKey(), $id]);
				$vest = Vest::get($id);
				$vest->clearCache();
			}
		} catch (Exception) {
			$request->passErrors[] = lang('Failed to save settings.', context: 'errors');
		}
		if ($request->isAjax()) {
			return $this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
			], empty($request->passErrors) ? 200 : 400);
		}
		return App::redirect('settings', $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function saveGeneral(Request $request): ResponseInterface {
		try {
			$apiKey = $request->getPost('api_key');
			if (isset($apiKey)) {
				Info::set('liga_api_key', $apiKey);
			}
			$lmx = $request->getPost('lmx_ip');
			if (isset($lmx)) {
				Info::set('lmx_ip', $lmx);
			}
			$gates = $request->getPost('gates_ips');
			if (isset($gates)) {
				Info::set('gates_ips', array_map('trim', explode(',', $gates)));
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
			return $this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
			]);
		}
		return App::redirect('settings', $request);
	}

	/**
	 * @throws DriverException
	 * @throws JsonException
	 */
	public function savePrint(Request $request): ResponseInterface {
		if ($this->validatePrint($request)) {
			try {
				DB::getConnection()->begin();

				// Save default template
				Info::set('default_print_template', $request->getPost('default-template', 'default'));

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
				foreach ($request->getPost('styles', []) as $key => $info) {
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
					$style->default = $style->id === (int)($request->getPost('default-style', 0));
					$style->insert();
				}

				/**
				 * @var array{style:int,dates:string} $info
				 */
				foreach ($request->getPost('dateRange', []) as $info) {
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
			return $this->respond([
				'success' => empty($request->passErrors),
				'errors' => $request->passErrors,
			], empty($request->passErrors) ? 200 : 500);
		}
		return App::redirect('settings-print', $request);
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
	private function processPrintFileUpload(UploadedFile $file, Request $request, string $printDir, PrintStyle $style, bool $landscape = false): ResponseInterface {
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
	public function print(): ResponseInterface {
		$this->params['styles'] = PrintStyle::getAll();
		$this->params['templates'] = PrintTemplate::getAll();
		$this->params['defaultTemplateId'] = Info::get('default_print_template', 'default');
		$this->params['dates'] = PrintStyle::getAllStyleDates();
		return $this->view('pages/settings/print');
	}

	#[Get('settings/cache', 'settings-cache')]
	public function cache(): ResponseInterface {
		return $this->view('pages/settings/cache');
	}

	public function group(): ResponseInterface {
		$this->params['groupsActive'] = GameGroup::getActive();
		$this->params['groupsInactive'] = GameGroup::query()->where('active = 0')->orderBy('id_group')->desc()->get();

		return $this->view('pages/settings/groups');
	}

}