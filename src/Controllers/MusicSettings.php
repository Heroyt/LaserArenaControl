<?php

namespace App\Controllers;

use App\Models\MusicMode;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Exceptions\DirectoryCreationException;

/**
 *
 */
class MusicSettings extends Controller
{

	/**
	 * @return void
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 */
	#[Get('settings/music', 'settings-music')]
	public function show() : void {
		$this->params['music'] = MusicMode::getAll();
		$this->view('pages/settings/music');
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('settings/music/upload')]
	public function upload(Request $request) : never {
		bdump($GLOBALS);
		bdump($request);
		bdump($_POST);
		bdump($_FILES);

		$music = new MusicMode();
		if (!empty($_FILES['media']['name'])) {
			if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
				$request->passErrors[] = match ($_FILES['media']['error']) {
					UPLOAD_ERR_INI_SIZE => lang('Uploaded file is too large', context: 'errors'),
					UPLOAD_ERR_FORM_SIZE => lang('Form size is to large', context: 'errors'),
					UPLOAD_ERR_PARTIAL => lang('The uploaded file was only partially uploaded.', context: 'errors'),
					UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors'),
					default => lang('Error while uploading a file.', context: 'errors'),
				};
			}
			else {
				$name = basename($_FILES['media']['name']);
				$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				// TODO: Check duplicate files
				if ($fileType === 'mp3') {
					if (move_uploaded_file($_FILES['media']["tmp_name"], UPLOAD_DIR.$name)) {
						$music->name = $name;
						$music->fileName = UPLOAD_DIR.$name;
						try {
							if (!$music->save()) {
								$request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
							}
						} catch (ValidationException $e) {
							$request->passErrors[] = lang('Failed to validate data before saving', context: 'errors').': '.$e->getMessage();
						}
					}
					else {
						$request->passErrors[] = lang('File upload failed.', context: 'errors');
					}
				}
				else {
					$request->passErrors[] = lang('File must be an mp3.', context: 'errors');
				}
			}
		}
		else {
			$request->passErrors[] = lang('No file uploaded', context: 'errors');
		}

		$this->customRespond($request);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('settings/music')]
	public function save(Request $request) : never {

		foreach ($_POST['music'] ?? [] as $id => $info) {
			try {
				$music = MusicMode::get((int) $id);
				$music->name = $info['name'];
				if (!$music->save()) {
					$request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
				}
			} catch (ModelNotFoundException $e) {
				$request->passErrors[] = lang('Cannot find music mode', context: 'errors');
			} catch (ValidationException $e) {
				$request->passErrors[] = lang('Failed to validate data before saving', context: 'errors').': '.$e->getMessage();
			} catch (DirectoryCreationException $e) {
			}
		}

		$this->customRespond($request);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	private function customRespond(Request $request) : never {
		if ($request->isAjax()) {
			if (!empty($request->passErrors)) {
				$this->respond(['errors' => $request->passErrors], 500);
			}
			$this->respond(['status' => 'ok']);
		}
		if (empty($request->passErrors)) {
			$request->passNotices[] = [
				'type'    => 'success',
				'content' => lang('Saved successfully', context: 'form'),
			];
		}
		App::redirect(['settings', 'music'], $request);
	}

}