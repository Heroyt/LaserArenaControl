<?php

namespace App\Controllers;

use App\Models\MusicMode;
use App\Services\LigaApi;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Delete;
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
		$allMusic = [];

		if (!empty($_FILES['media']['name'])) {
			foreach ($_FILES['media']['name'] as $key => $name) {
				$music = new MusicMode();
				$name = basename($name);

				// Handle form errors
				if ($_FILES['media']['error'][$key] !== UPLOAD_ERR_OK) {
					$request->passErrors[] = match ($_FILES['media']['error'][$key]) {
						UPLOAD_ERR_INI_SIZE => lang('Uploaded file is too large', context: 'errors').' - '.$name,
						UPLOAD_ERR_FORM_SIZE => lang('Form size is to large', context: 'errors').' - '.$name,
						UPLOAD_ERR_PARTIAL => lang('The uploaded file was only partially uploaded.', context: 'errors').' - '.$name,
						UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
						default => lang('Error while uploading a file.', context: 'errors').' - '.$name,
					};
					continue;
				}

				// Check for duplicates
				if (file_exists(UPLOAD_DIR.$name)) {
					$request->passNotices[] = ['type' => 'info', 'content' => lang('Uploaded file already exists', context: 'errors').' - '.$name];
					continue;
				}

				// Check file type
				$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				if ($fileType !== 'mp3') {
					$request->passErrors[] = lang('File must be an mp3.', context: 'errors');
					continue;
				}

				// Upload file
				if (!move_uploaded_file($_FILES['media']["tmp_name"][$key], UPLOAD_DIR.$name)) {
					$request->passErrors[] = lang('File upload failed.', context: 'errors');
					continue;
				}

				// Save the model
				$music->name = $name;
				$music->fileName = UPLOAD_DIR.$name;
				try {
					if (!$music->save()) {
						$request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
						continue;
					}
					$allMusic[] = [
						'id'       => $music->id,
						'name'     => $music->name,
						'media'    => App::getUrl().$name,
						'fileName' => $music->fileName,
					];
					$request->passNotices[] = [
						'type'    => 'success',
						'content' => lang('Saved successfully', context: 'form'),
					];
				} catch (ValidationException $e) {
					$request->passErrors[] = lang('Failed to validate data before saving', context: 'errors').': '.$e->getMessage();
				}
			}
		}
		else {
			$request->passErrors[] = lang('No file uploaded', context: 'errors');
		}

		/** @var LigaApi $liga */
		$liga = App::getService('ligaApi');
		try {
			$liga->syncMusicModes();
		} catch (ValidationException $e) {
		}

		$this->customRespond($request, $allMusic);
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
				$music->order = (int) $info['order'];
				$music->public = isset($info['public']);
				$music->setPreviewStartFromFormatted($info['previewStart'] ?? '0');
				if (!$music->save()) {
					$request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
				}
			} catch (ModelNotFoundException) {
				$request->passErrors[] = lang('Cannot find music mode', context: 'errors');
			} catch (ValidationException $e) {
				$request->passErrors[] = lang('Failed to validate data before saving', context: 'errors').': '.$e->getMessage();
			} catch (DirectoryCreationException) {
			}
		}

		/** @var LigaApi $liga */
		$liga = App::getService('ligaApi');
		try {
			$liga->syncMusicModes();
		} catch (ValidationException $e) {
		}

		$this->customRespond($request);
	}

	/**
	 * Send a response to the client - sends a JSON or a redirect based on the request type (AJAX / normal)
	 *
	 * @param Request     $request
	 * @param MusicMode[] $music
	 *
	 * @return never
	 * @throws JsonException
	 */
	private function customRespond(Request $request, array $music = []) : never {
		if ($request->isAjax()) {
			if (!empty($request->passErrors)) {
				$this->respond(['errors' => $request->passErrors, 'notices' => $request->passNotices, 'music' => $music], 500);
			}
			$this->respond(['status' => 'ok', 'errors' => [], 'notices' => $request->passNotices, 'music' => $music]);
		}
		App::redirect(['settings', 'music'], $request);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Delete('settings/music/{id}')]
	public function delete(Request $request) : never {
		$id = (int) ($request->params['id'] ?? 0);
		if ($id <= 0) {
			$this->respond(['error' => lang('Invalid ID', context: 'errors')], 400);
		}
		try {
			$music = MusicMode::get($id);
			if (file_exists($music->fileName) && !unlink($music->fileName)) {
				$this->respond(['error' => lang('Failed to delete the music file', context: 'errors')], 500);
			}
			if (!$music->delete()) {
				$this->respond(['error' => lang('Failed to delete the music mode', context: 'errors')], 500);
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => lang('Music mode not found', context: 'errors')], 404);
		}

		$this->respond(['status' => 'ok']);
	}

}