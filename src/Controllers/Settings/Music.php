<?php

namespace App\Controllers\Settings;

use App\Core\App;
use App\Models\MusicMode;
use App\Models\Playlist;
use App\Services\FeatureConfig;
use App\Tasks\MusicSyncTask;
use App\Tasks\MusicTrimPreviewTask;
use App\Tasks\Payloads\MusicTrimPreviewPayload;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Roadrunner\Tasks\TaskProducer;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Spiral\RoadRunner\Jobs\Options;

/**
 *
 */
class Music extends Controller
{
    public function __construct(
      private readonly TaskProducer  $taskProducer,
      private readonly FeatureConfig $config,
    ) {
        parent::__construct();
    }

    public function show() : ResponseInterface {
        $this->params['music'] = MusicMode::getAll();
        $this->params['playlists'] = Playlist::getAll();
        return $this->view('pages/settings/music');
    }

    public function upload(Request $request) : ResponseInterface {
        $allMusic = [];

        $files = $request->getUploadedFiles();
        if (isset($files['media']) && is_array($files['media']) && !empty($files['media'])) {
            /** @var UploadedFile $file */
            foreach ($files['media'] as $file) {
                $music = new MusicMode();
                $name = basename($file->getClientFilename());

                // Handle form errors
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    $request->passErrors[] = match ($file->getError()) {
                        UPLOAD_ERR_INI_SIZE   => lang('Nahraný soubor je příliš velký', context: 'errors').' - '.$name,
                        UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors').' - '.$name,
                        UPLOAD_ERR_PARTIAL    => lang(
                                     'The uploaded file was only partially uploaded.',
                            context: 'errors'
                          ).' - '.$name,
                        UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
                        default               => lang('Error while uploading a file.', context: 'errors').' - '.$name,
                    };
                    continue;
                }

                // Check for duplicates
                if (file_exists(UPLOAD_DIR.$name)) {
                    $request->passNotices[] = [
                      'type'    => 'info',
                      'content' => lang('Uploaded file already exists', context: 'errors').' - '.$name,
                    ];
                    $musicCheck = MusicMode::query()->where('file_name = %s', UPLOAD_DIR.$name)->first();
                    if (isset($musicCheck)) {
                        $music = $musicCheck;
                    }
                }

                // Check file type
                $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($fileType !== 'mp3') {
                    $request->passErrors[] = lang('File must be an mp3.', context: 'errors');
                    continue;
                }

                // Upload file
                try {
                    $file->moveTo(UPLOAD_DIR.$name);
                } catch (RuntimeException $e) {
                    $request->passErrors[] = lang('File upload failed.', context: 'errors').$e->getMessage();
                    continue;
                }

                // Save the model
                $music->name = pathinfo($name, PATHINFO_FILENAME);
                $music->fileName = UPLOAD_DIR.$name;
                try {
                    if (!$music->save()) {
                        $request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
                        continue;
                    }
                    $allMusic[] = [
                      'id'       => $music->id,
                      'name'     => $music->name,
                      'media' => App::getInstance()->getBaseUrl().$name,
                      'fileName' => $music->fileName,
                    ];
                    $request->passNotices[] = [
                      'type'    => 'success',
                      'content' => lang('Saved successfully', context: 'form'),
                    ];
                } catch (ValidationException $e) {
                    $request->passErrors[] = lang(
                                 'Failed to validate data before saving',
                        context: 'errors'
                      ).': '.$e->getMessage();
                }
            }
        }
        else {
            $request->passErrors[] = lang('No file uploaded', context: 'errors');
        }

        $this->taskProducer->plan(MusicTrimPreviewTask::class, new MusicTrimPreviewPayload($music->id));
        if ($this->config->isFeatureEnabled('liga')) {
            $this->taskProducer->plan(MusicSyncTask::class, null, new Options(priority: 99));
        }
        $this->taskProducer->dispatch();

        return $this->customRespond($request, ['music' => $allMusic]);
    }

    public function save(Request $request) : ResponseInterface {
        /** @var UploadedFile[][][] $files */
        $files = $request->getUploadedFiles();
        /** @var array{name:string,group:string,order:numeric-string,public?:string,previewStart?:numeric-string}[] $musicInfo */
        $musicInfo = $request->getPost('music', []);
        foreach ($musicInfo as $id => $info) {
            try {
                $music = MusicMode::get((int) $id);
                $music->name = $info['name'];
                $music->group = empty($info['group']) ? null : $info['group'];
                $music->order = (int) $info['order'];
                $music->public = isset($info['public']);
                $previewStart = $music->previewStart;
                $music->setPreviewStartFromFormatted($info['previewStart'] ?? '0');
                if ($previewStart !== $music->previewStart) {
                    $this->taskProducer->plan(MusicTrimPreviewTask::class, new MusicTrimPreviewPayload($music->id));
                }

                if (isset($files['music'][$id]['background'])) {
                    $file = $files['music'][$id]['background'];
                    $name = basename($file->getClientFilename());
                    if ($file->getError() === UPLOAD_ERR_OK) {
                        $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($fileType, ['jpg', 'jpeg', 'png'], true)) {
                            $uploadPath = UPLOAD_DIR.'music-'.$id.'-background.'.$fileType;
                            $file->moveTo($uploadPath);
                            $music->backgroundImage = $uploadPath;
                            $request->passNotices[] = 'music - '.$id.' - backgroundImage - '.$music->backgroundImage;

                            // Delete all optimized images
                            $images = glob(UPLOAD_DIR.'optimized/music-'.$id.'-background*');
                            foreach ($images as $file) {
                                unlink($file);
                            }
                        }
                        else {
                            $request->passErrors[] = lang('Nepodporovaný typ obrázku.', context: 'errors');
                        }
                    }
                    else {
                        $request->passErrors[] = match ($file->getError()) {
                            UPLOAD_ERR_INI_SIZE   => lang(
                                         'Nahraný soubor je příliš velký',
                                context: 'errors'
                              ).' - '.$name,
                            UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors').' - '.$name,
                            UPLOAD_ERR_PARTIAL    => lang(
                                         'The uploaded file was only partially uploaded.',
                                context: 'errors'
                              ).' - '.$name,
                            UPLOAD_ERR_CANT_WRITE => lang(
                                         'Failed to write file to disk.',
                                context: 'errors'
                              ).' - '.$name,
                            default               => lang(
                                         'Error while uploading a file.',
                                context: 'errors'
                              ).' - '.$name,
                        };
                    }
                }

                if (isset($files['music'][$id]['icon'])) {
                    $file = $files['music'][$id]['icon'];
                    $name = basename($file->getClientFilename());
                    if ($file->getError() === UPLOAD_ERR_OK) {
                        $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'svg'], true)) {
                            $uploadPath = UPLOAD_DIR.'music-'.$id.'-icon.'.$fileType;
                            $file->moveTo($uploadPath);
                            $music->icon = $uploadPath;
                            $request->passNotices[] = 'music - '.$id.' - icon - '.$music->icon;

                            // Delete all optimized images
                            $images = glob(UPLOAD_DIR.'optimized/music-'.$id.'-icon*');
                            foreach ($images as $file) {
                                unlink($file);
                            }
                        }
                        else {
                            $request->passErrors[] = lang('Nepodporovaný typ obrázku.', context: 'errors');
                        }
                    }
                    else {
                        $request->passErrors[] = match ($file->getError()) {
                            UPLOAD_ERR_INI_SIZE   => lang(
                                         'Nahraný soubor je příliš velký',
                                context: 'errors'
                              ).' - '.$name,
                            UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors').' - '.$name,
                            UPLOAD_ERR_PARTIAL    => lang(
                                         'The uploaded file was only partially uploaded.',
                                context: 'errors'
                              ).' - '.$name,
                            UPLOAD_ERR_CANT_WRITE => lang(
                                         'Failed to write file to disk.',
                                context: 'errors'
                              ).' - '.$name,
                            default               => lang(
                                         'Error while uploading a file.',
                                context: 'errors'
                              ).' - '.$name,
                        };
                    }
                }

                if (!$music->save()) {
                    $request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
                }
            } catch (ModelNotFoundException) {
                $request->passErrors[] = lang('Cannot find music mode', context: 'errors');
            } catch (ValidationException $e) {
                $request->passErrors[] = lang(
                             'Failed to validate data before saving',
                    context: 'errors'
                  ).': '.$e->getMessage();
            } catch (DirectoryCreationException) {
            }
        }

        $playlistIds = [];
        foreach ($request->getPost('playlist', []) as $id => $data) {
            if (empty($data['name'])) {
                continue;
            }
            $new = false;
            if (str_starts_with($id, 'new')) {
                $playlist = new Playlist();
                $new = true;
            }
            else {
                try {
                    $playlist = Playlist::get((int) $id);
                } catch (ModelNotFoundException | ValidationException $e) {
                    $playlist = new Playlist();
                    $new = true;
                }
            }

            $playlist->name = $data['name'];
            $music = [];
            foreach ($data['music'] ?? [] as $musicId) {
                $music[] = MusicMode::get((int) $musicId);
            }
            $playlist->setMusic($music);

            $playlist->save();
            if ($new) {
                $playlistIds[$id] = $playlist->id;
            }
        }

        if ($this->config->isFeatureEnabled('liga')) {
            $this->taskProducer->plan(MusicSyncTask::class, null, new Options(priority: 99));
        }

        $this->taskProducer->dispatch();

        return $this->customRespond($request, ['playlistIds' => $playlistIds]);
    }

    /**
     * Send a response to the client - sends a JSON or a redirect based on the request type (AJAX / normal)
     *
     * @param  Request  $request
     * @param  array<string,mixed>  $data
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    private function customRespond(Request $request, array $data = []) : ResponseInterface {
        if ($request->isAjax()) {
            if (!empty($request->passErrors)) {
                return $this->respond(
                  array_merge(['errors' => $request->passErrors, 'notices' => $request->passNotices], $data),
                  500
                );
            }
            return $this->respond(
              array_merge([['status' => 'ok', 'errors' => [], 'notices' => $request->passNotices]], $data)
            );
        }
        return $this->app->redirect(['settings', 'music'], $request);
    }

    public function uploadIntro(MusicMode $music, Request $request) : ResponseInterface {
        $files = $request->getUploadedFiles();
        if (isset($files['intro']) && $files['intro'] instanceof UploadedFile) {
            $file = $files['intro'];
            $savePath = UPLOAD_DIR.$music->id.'_intro.mp3';
            $response = $this->processMediaUpload($file, $savePath);
            if ($response !== null) {
                return $response;
            }

            $music->introFile = $savePath;
            if (!$music->save()) {
                return $this->respond(
                  new ErrorResponse(lang('Nepodařilo se uložit hudební mód.', context: 'errors'), ErrorType::DATABASE),
                  500
                );
            }

            return $this->respond(
              new SuccessResponse(values: ['url' => $music->getIntroMediaUrl(), 'name' => $music->getIntroFileName()])
            );
        }
        return $this->respond(new ErrorResponse('No file was uploaded', ErrorType::VALIDATION), 400);
    }

    private function processMediaUpload(UploadedFile $file, string $savePath) : ?ResponseInterface {
        $name = basename($file->getClientFilename());

        // Handle form errors
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $error = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE   => lang('Nahraný soubor je příliš velký', context: 'errors').' - '.$name,
                UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors').' - '.$name,
                UPLOAD_ERR_PARTIAL    => lang(
                             'The uploaded file was only partially uploaded.',
                    context: 'errors'
                  ).' - '.$name,
                UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
                default               => lang('Error while uploading a file.', context: 'errors').' - '.$name,
            };
            return $this->respond(new ErrorResponse($error, ErrorType::VALIDATION), 400);
        }

        // Check file type
        $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($fileType !== 'mp3') {
            return $this->respond(
              new ErrorResponse(lang('File must be an mp3.', context: 'errors'), ErrorType::VALIDATION),
              400
            );
        }

        // Upload file
        try {
            $file->moveTo($savePath);
        } catch (RuntimeException $e) {
            return $this->respond(
              new ErrorResponse(lang('File upload failed.', context: 'errors'), exception: $e),
              500
            );
        }
        return null;
    }

    public function uploadEnding(MusicMode $music, Request $request) : ResponseInterface {
        $files = $request->getUploadedFiles();
        if (isset($files['ending']) && $files['ending'] instanceof UploadedFile) {
            $file = $files['ending'];
            $savePath = UPLOAD_DIR.$music->id.'_ending.mp3';
            $response = $this->processMediaUpload($file, $savePath);
            if ($response !== null) {
                return $response;
            }

            $music->endingFile = $savePath;
            if (!$music->save()) {
                return $this->respond(
                  new ErrorResponse(lang('Nepodařilo se uložit hudební mód.', context: 'errors'), ErrorType::DATABASE),
                  500
                );
            }

            return $this->respond(
              new SuccessResponse(values: ['url' => $music->getEndingMediaUrl(), 'name' => $music->getEndingFileName()])
            );
        }
        return $this->respond(new ErrorResponse('No file was uploaded', ErrorType::VALIDATION), 400);
    }

    public function uploadArmed(MusicMode $music, Request $request) : ResponseInterface {
        $files = $request->getUploadedFiles();
        if (isset($files['armed']) && $files['armed'] instanceof UploadedFile) {
            $file = $files['armed'];
            $savePath = UPLOAD_DIR.$music->id.'_armed.mp3';
            $response = $this->processMediaUpload($file, $savePath);
            if ($response !== null) {
                return $response;
            }

            $music->armedFile = $savePath;
            if (!$music->save()) {
                return $this->respond(
                  new ErrorResponse(lang('Nepodařilo se uložit hudební mód.', context: 'errors'), ErrorType::DATABASE),
                  500
                );
            }

            return $this->respond(
              new SuccessResponse(values: ['url' => $music->getArmedMediaUrl(), 'name' => $music->getArmedFileName()])
            );
        }
        return $this->respond(new ErrorResponse('No file was uploaded', ErrorType::VALIDATION), 400);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function delete(Request $request) : ResponseInterface {
        $id = (int) ($request->params['id'] ?? 0);
        if ($id <= 0) {
            return $this->respond(['error' => lang('Invalid ID', context: 'errors')], 400);
        }
        try {
            $music = MusicMode::get($id);
            if (file_exists($music->fileName) && !unlink($music->fileName)) {
                return $this->respond(['error' => lang('Failed to delete the music file', context: 'errors')], 500);
            }
            if (!$music->delete()) {
                return $this->respond(['error' => lang('Failed to delete the music mode', context: 'errors')], 500);
            }
        } catch (ModelNotFoundException | ValidationException | DirectoryCreationException $e) {
            return $this->respond(['error' => lang('Music mode not found', context: 'errors')], 404);
        }

        return $this->respond(['status' => 'ok']);
    }
}
