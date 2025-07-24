<?php

namespace App\Controllers\Settings;

use App\Core\Info;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\Services\FeatureConfig;
use DateTimeImmutable;
use Dibi\DriverException;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 *
 */
class PrintSettings extends Controller
{
    public string $title = 'Nastavení - Tisk';

    public function __construct(
      private readonly FeatureConfig $featureConfig,
    ) {}

    public function init(RequestInterface $request) : void {
        parent::init($request);
        $this->params['featureConfig'] = $this->featureConfig;
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws TemplateDoesNotExistException
     * @throws ValidationException
     */
    public function show() : ResponseInterface {
        $this->params['styles'] = PrintStyle::getAll();
        $this->params['templates'] = PrintTemplate::getAll();
        $this->params['defaultTemplateId'] = Info::get('default_print_template', 'default');
        $this->params['dates'] = PrintStyle::getAllStyleDates();
        return $this->view('pages/settings/print');
    }

    /**
     * @throws DriverException
     * @throws JsonException
     */
    public function save(Request $request) : ResponseInterface {
        if ($this->validate($request)) {
            try {
                DB::getConnection()->begin();

                // Save default template
                Info::set('default_print_template', $request->getPost('default-template', 'default'));

                // Delete all dates
                DB::delete(PrintStyle::TABLE.'_dates', ['1=1']);

                // Delete all styles
                DB::delete(PrintStyle::TABLE, ['1=1']);
                DB::resetAutoIncrement(PrintStyle::TABLE);

                $printDir = 'upload/print/';
                if (
                  !file_exists(ROOT.'upload/print') && !mkdir($concurrentDirectory = ROOT.'upload/print') && !is_dir(
                    $concurrentDirectory
                  )
                ) {
                    $printDir = 'upload';
                }

                /** @var array<int,array{background?:UploadedFile,background-landscape?:UploadedFile}> $files */
                $files = $request->getUploadedFiles()['styles'] ?? [];
                if (!is_array($files)) {
                    $files = [];
                }
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

                    if (isset($files[$key]['background'])) {
                        $this->processPrintFileUpload($files[$key]['background'], $request, $printDir, $style);
                    }
                    if (isset($files[$key]['background-landscape'])) {
                        $this->processPrintFileUpload(
                          $files[$key]['background-landscape'],
                          $request,
                          $printDir,
                          $style,
                          true
                        );
                    }
                    $style->default = $style->id === (int) ($request->getPost('default-style', 0));
                    $style->insert();
                }

                /**
                 * @var array{style:int,dates:string} $info
                 */
                foreach ($request->getPost('dateRange', []) as $info) {
                    preg_match_all('/(\d{2}\.\d{2}\.\d{4})/', $info['dates'], $matches);
                    $dateFrom = new DateTimeImmutable($matches[0][1] ?? '');
                    $dateTo = new DateTimeImmutable($matches[1][1] ?? '');
                    DB::insert(
                      PrintStyle::TABLE.'_dates',
                      [
                        PrintStyle::getPrimaryKey() => $info['style'],
                        'date_from'                 => $dateFrom,
                        'date_to'                   => $dateTo,
                      ]
                    );
                }

                DB::getConnection()->commit();
            } catch (Exception | DriverException $e) {
                DB::getConnection()->rollback();
                $request->passErrors[] = lang('Database error occurred.', context: 'errors').' '.$e->getMessage();
            } catch (ValidationException $e) {
                DB::getConnection()->rollback();
                $request->passErrors[] = lang('Validation error:', context: 'errors').' '.$e->getMessage();
            }
        }
        if ($request->isAjax()) {
            return $this->respond(
              [
                'success' => empty($request->passErrors),
                'errors'  => $request->passErrors,
              ],
              empty($request->passErrors) ? 200 : 500
            );
        }
        return $this->app->redirect('settings-print', $request);
    }

    /**
     * @param  Request  $request
     *
     * @return bool
     */
    private function validate(Request $request) : bool {
        // TODO: Actually validate request..
        return count($request->passErrors) === 0;
    }

    /**
     * @param  UploadedFile  $file
     * @param  Request  $request
     * @param  string  $printDir
     * @param  PrintStyle  $style
     * @param  bool  $landscape
     * @return void
     */
    private function processPrintFileUpload(
      UploadedFile $file,
      Request      $request,
      string       $printDir,
      PrintStyle   $style,
      bool         $landscape = false
    ) : void {
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
            return;
        }
        $name = $printDir.$name;
        $check = getimagesize($file->getStream()->getMetadata('uri'));
        if ($check === false) {
            $request->passErrors[] = lang('File upload failed.', context: 'errors');
            return;
        }

        $validTypes = ['jpg', 'png', 'jpeg'];
        $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($fileType, $validTypes)) {
            $request->passErrors[] = lang(
                       'Nahraný soubor musí být v jednom z formátů: %s.',
              context: 'errors',
              format : [implode(', ', $validTypes)]
            );
            return;
        }

        try {
            $file->moveTo(ROOT.$name);
            if ($landscape) {
                $style->bgLandscape = $name;
            }
            else {
                $style->bg = $name;
            }
        } catch (RuntimeException $e) {
            $request->passErrors[] = lang('File upload failed.', context: 'errors').$e->getMessage();
        }
    }
}
