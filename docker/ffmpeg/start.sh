#!/bin/ash

# Run ffmpeg in the background
ffmpeg -fflags nobuffer -loglevel error -rtsp_transport tcp -i rtsp://admin:12345@192.168.25.221/h264/ch1/main/av_stream -vsync 0 -copyts -vcodec copy -movflags frag_keyframe+empty_moov -an -hls_flags delete_segments+append_list -f hls -hls_time 1 -hls_list_size 3 -hls_segment_type mpegts -hls_segment_filename '/var/stream/stream1-%d.ts' /var/stream/stream1.m3u8 > /var/log/stream/stream1.log 2>&1 < /dev/null &
ffmpeg -fflags nobuffer -loglevel error -rtsp_transport tcp -i rtsp://admin:12345@192.168.25.222/h264/ch1/main/av_stream -vsync 0 -copyts -vcodec copy -movflags frag_keyframe+empty_moov -an -hls_flags delete_segments+append_list -f hls -hls_time 1 -hls_list_size 3 -hls_segment_type mpegts -hls_segment_filename '/var/stream/stream2-%d.ts' /var/stream/stream2.m3u8 > /var/log/stream/stream2.log 2>&1 < /dev/null &
ffmpeg -fflags nobuffer -loglevel error -rtsp_transport tcp -i rtsp://admin:12345@192.168.25.223/h264/ch1/main/av_stream -vsync 0 -copyts -vcodec copy -movflags frag_keyframe+empty_moov -an -hls_flags delete_segments+append_list -f hls -hls_time 1 -hls_list_size 3 -hls_segment_type mpegts -hls_segment_filename '/var/stream/stream3-%d.ts' /var/stream/stream3.m3u8 > /var/log/stream/stream3.log 2>&1 < /dev/null &
ffmpeg -fflags nobuffer -loglevel error -rtsp_transport tcp -i rtsp://admin:12345@192.168.25.224/h264/ch1/main/av_stream -vsync 0 -copyts -vcodec copy -movflags frag_keyframe+empty_moov -an -hls_flags delete_segments+append_list -f hls -hls_time 1 -hls_list_size 3 -hls_segment_type mpegts -hls_segment_filename '/var/stream/stream4-%d.ts' /var/stream/stream4.m3u8 > /var/log/stream/stream4.log 2>&1 < /dev/null &
ffmpeg -fflags nobuffer -loglevel error -rtsp_transport tcp -i rtsp://admin:12345@192.168.25.225/h264/ch1/main/av_stream -vsync 0 -copyts -vcodec copy -movflags frag_keyframe+empty_moov -an -hls_flags delete_segments+append_list -f hls -hls_time 1 -hls_list_size 3 -hls_segment_type mpegts -hls_segment_filename '/var/stream/stream5-%d.ts' /var/stream/stream5.m3u8 > /var/log/stream/stream5.log 2>&1 < /dev/null &
ffmpeg -fflags nobuffer -loglevel error -rtsp_transport tcp -i rtsp://admin:12345@192.168.25.226/h264/ch1/main/av_stream -vsync 0 -copyts -vcodec copy -movflags frag_keyframe+empty_moov -an -hls_flags delete_segments+append_list -f hls -hls_time 1 -hls_list_size 3 -hls_segment_type mpegts -hls_segment_filename '/var/stream/stream6-%d.ts' /var/stream/stream6.m3u8 > /var/log/stream/stream6.log 2>&1 < /dev/null &

while true; do
  if [ -f /var/stream/config/restart.txt ]; then
    echo "Restarting container..."

    # Remove the restart flag
    rm -f /var/stream/config/restart.txt

    # exit the container - exit code is optional
    exit 0
  fi
  sleep 5
done