#!/bin/bash

echo "Content-Type: multipart/x-mixed-replace;boundary=ffmpeg"
echo "Cache-Control: no-cache"
echo ""
ffmpeg -i "rtsp://admin:12345@192.168.25.221/h264/ch1/main/av_stream" -c:v mjpeg -q:v 1 -f mpjpeg -an -

ffmpeg -fflags nobuffer \
 -loglevel debug \
 -rtsp_transport tcp \
 -i rtsp://admin:12345@192.168.25.208/h264/ch1/main/av_stream \
 -vsync 0 \
 -copyts \
 -vcodec copy \
 -movflags frag_keyframe+empty_moov \
 -an \
 -hls_flags delete_segments+append_list \
 -f hls \
 -hls_time 1 \
 -hls_list_size 3 \
 -hls_segment_type mpegts \
 -hls_segment_filename './stream/%d.ts' \
./stream/index.m3u8