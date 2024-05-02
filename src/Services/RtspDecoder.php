<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class RtspDecoder
{

    private Process $process;

    public function __construct(
      public readonly string $uri,
      public readonly string $name,
    ) {}

    public function start() : void {
        $command = [
          'ffmpeg',
          '-fflags',
          'nobuffer',
          '-loglevel',
          'debug',
          '-rtsp_transport',
          'tcp',
          '-i',
          $this->uri,
          '-vsync',
          '0',
          '-copyts',
          '-vcodec',
          'copy',
          '-movflags',
          'frag_keyframe+empty_moov',
          '-an',
          '-hls_flags',
          'delete_segments+append_list',
          '-f',
          'hls',
          '-hls_time',
          '1',
          '-hls_list_size',
          '3',
          '-hls_segment_type',
          'mpegts',
          '-hls_segment_filename',
          '/var/tmp/hls/'.$this->name.'%d.ts',
          '/var/tmp/hls/'.$this->name.'.m3u8',
        ];

        $this->process = new Process($command);
        $this->process->start();
    }

    public function stop() : void {
        if (isset($this->process)) {
            $this->process->stop();
        }
    }
}