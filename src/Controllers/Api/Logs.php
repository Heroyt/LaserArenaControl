<?php

namespace App\Controllers\Api;

use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Logging\Exceptions\ArchiveCreationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use ZipArchive;

class Logs extends ApiController
{
    /**
     * @throws JsonException
     */
    public function show(Request $request) : ResponseInterface {
        try {
            $logger = new Logger(LOG_DIR.'api/', 'logs');
            $logger->info('Showing logs ('.$request->getIp().')');
        } catch (DirectoryCreationException) {
            $logger = null;
        }

        $logFile = $request->getGet('log', '');
        if (empty($logFile)) {
            return $this->respond('Missing required argument "log".', 400);
        }
        if (!file_exists(LOG_DIR.$logFile.'.log')) {
            $date = $request->getGet('date', date('Y-m-d'));
            $logFile .= '-'.$date.'.log';
            if (!file_exists(LOG_DIR.$logFile) || !is_readable(LOG_DIR.$logFile)) {
                return $this->respond('Log file "'.$logFile.'" does not exist or is not readable.', 404);
            }
        }
        else {
            $logFile .= '.log';
        }

        /** @var string $contents */
        $contents = file_get_contents(LOG_DIR.$logFile);

        preg_match_all('/(^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})] ([A-Z]+): ([^\[]+))/m', $contents, $matches);
        $lines = [];
        foreach ($matches[0] as $key => $line) {
            $lines[] = [
              'time'     => $matches[2][$key],
              'severity' => $matches[3][$key],
              'contents' => $matches[4][$key],
            ];
        }

        return $this->respond(['success' => true, 'lines' => $lines]);
    }

    /**
     * @param  Request  $request
     *
     * @return void
     * @throws ArchiveCreationException
     * @throws JsonException
     */
    public function download(Request $request) : ResponseInterface {
        try {
            $logger = new Logger(LOG_DIR.'api/', 'logs');
            $logger->info('Downloading logs ('.$request->getIp().')');
        } catch (DirectoryCreationException) {
            $logger = null;
        }

        $logFile = $request->getGet('log', '');
        $date = $request->getGet('date', date('Y-m-d'));
        if (!empty($logFile)) {
            $logFile .= '-'.$date.'.log';
            if (!file_exists(LOG_DIR.$logFile) || !is_readable(LOG_DIR.$logFile)) {
                return $this->respond('Log file "'.$logFile.'" does not exist or is not readable.', 404);
            }
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="'.$logFile.'"');
            header('Content-Length: '.filesize(LOG_DIR.$logFile));
            http_response_code(200);
            echo file_get_contents(LOG_DIR.$logFile);
            exit;
        }

        $archive = new ZipArchive();
        $test = $archive->open(LOG_DIR.'logs.zip', ZipArchive::CREATE); // Create or open a zip file
        if ($test !== true) {
            throw new ArchiveCreationException($test);
        }

        $Directory = new RecursiveDirectoryIterator(LOG_DIR);
        $Iterator = new RecursiveIteratorIterator($Directory);
        $Regex = new RegexIterator($Iterator, '/^.+\.(?:log|zip)/i', RegexIterator::MATCH);
        foreach ($Regex as $file) {
            if (is_array($file)) {
                $file = $file[0];
            }
            $fileName = str_replace(LOG_DIR, '', $file);
            if ($fileName === 'logs.zip') {
                continue;
            }
            $archive->addFile($file, $fileName);
        }
        $archive->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="logs.zip"');
        header("Content-Length: ".filesize(LOG_DIR.'logs.zip'));
        http_response_code(200);
        readfile(LOG_DIR.'logs.zip');
        exit;
    }
}
