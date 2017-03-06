<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

/**
 * Transcoder config.php
 *
 * Completely optional configuration settings for Transcoder if you want to
 * customize some of its more esoteric behavior, or just want specific control
 * over things.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'transcoder.php'
 * and make your changes there.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [

    // The path to the ffmpeg binary
    "ffmpegPath" => "/usr/bin/ffmpeg",

    // The path to the ffprobe binary
    "ffprobePath" => "/usr/bin/ffprobe",

    // The options to use for ffprobe
    "ffprobeOptions" => "-v quiet -print_format json -show_format -show_streams",

    // The path where the transcoded videos are stored
    "transcoderPath" => $_SERVER['DOCUMENT_ROOT'] . "/transcoder/",

    // The URL where the transcoded videos are stored
    "transcoderUrl" => "/transcoder/",

    // Default options for encoded videos
    "defaultVideoOptions" => [
        "fileSuffix" => ".mp4",
        "bitRate" => "800k",
        "frameRate" => 15,
        "width" => "",
        "height" => "",
        "sharpen" => true,
        // Can be "none", "crop", or "letterbox"
        "aspectRatio" => "letterbox",
        "letterboxColor" => "",
    ],

    // Default options for video thumbnails
    "defaultThumbnailOptions" => [
        "fileSuffix" => ".jpg",
        "timeInSecs" => 10,
        "width" => "",
        "height" => "",
        "sharpen" => true,
        // Can be "none", "crop", or "letterbox"
        "aspectRatio" => "letterbox",
        "letterboxColor" => "",
    ],

];
