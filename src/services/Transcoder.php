<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\services;

use Craft;
use craft\base\Component;
use craft\console\Request;
use craft\elements\Asset;
use craft\volumes\Local;

use yii\base\Exception;

/**
 * @author    nystudio107
 * @package   Transcoder
 * @since     1.0.0
 */
class Transcoder extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns a URL to the transcoded video or "" if it doesn't exist (at which
     * time it will create it). By default, the video is never resized, and the
     * format is always .mp4
     *
     * @param $filePath     path to the original video -OR- an Asset
     * @param $videoOptions array of options for the video
     *
     * @return string       URL of the transcoded video or ""
     */
    public function getVideoUrl($filePath, $videoOptions): string
    {

        $result = "";
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            $path_parts = pathinfo($filePath);
            $destVideoFile = $path_parts['filename'];
            $destVideoPath = Craft::$app->config->get("transcoderPath", "transcoder");

            // Default options for transcoded videos
            $defaultOptions = Craft::$app->config->get("defaultVideoOptions", "transcoder");

            // Coalesce the passed in $videoOptions with the $defaultOptions
            $videoOptions = array_merge($defaultOptions, $videoOptions);

            // Build the basic command for ffmpeg
            $ffmpegCmd = Craft::$app->config->get("ffmpegPath", "transcoder")
                .' -i '.escapeshellarg($filePath)
                .' -vcodec libx264'
                .' -vprofile high'
                .' -preset slow'
                .' -crf 22'
                .' -c:a copy'
                .' -bufsize 1000k'
                .' -threads 0';

            // Set the framerate if desired
            if (!empty($videoOptions['frameRate'])) {
                $ffmpegCmd .= ' -r '.$videoOptions['frameRate'];
                $destVideoFile .= '_'.$videoOptions['frameRate'].'fps';
            }

            // Set the bitrate if desired
            if (!empty($videoOptions['bitRate'])) {
                $ffmpegCmd .= ' -b:v '.$videoOptions['bitRate'].' -maxrate '.$videoOptions['bitRate'];
                $destVideoFile .= '_'.$videoOptions['bitRate'].'bps';
            }

            // Adjust the scaling if desired
            list($destVideoFile, $ffmpegCmd) = $this->addScalingParams(
                $videoOptions,
                $destVideoFile,
                $ffmpegCmd
            );

            // Create the directory if it isn't there already
            if (!file_exists($destVideoPath)) {
                mkdir($destVideoPath);
            }

            // File to store the video encoding progress in
            $progressFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$destVideoFile.".progress";

            // Assemble the destination path and final ffmpeg command
            $destVideoFile .= '.mp4';
            $destVideoPath = $destVideoPath.$destVideoFile;
            $ffmpegCmd .= ' -f mp4 -y '.escapeshellarg($destVideoPath).' 1> '.$progressFile.' 2>&1 & echo $!';

            // Make sure there isn't a lockfile for this video already
            $lockFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$destVideoFile.".lock";
            $oldPid = @file_get_contents($lockFile);
            if ($oldPid !== false) {
                exec("ps $oldPid", $ProcessState);
                if (count($ProcessState) >= 2) {
                    return $result;
                }
                unlink($lockFile);
            }

            // If the video file already exists and hasn't been modified, return it.  Otherwise, start it transcoding
            if (file_exists($destVideoPath) && (filemtime($destVideoPath) >= filemtime($filePath))) {
                $result = Craft::$app->config->get("transcoderUrl", "transcoder").$destVideoFile;
            } else {
                // Kick off the transcoding
                $pid = shell_exec($ffmpegCmd);
                Craft::info($ffmpegCmd, __METHOD__);

                // Create a lockfile in tmp
                file_put_contents($lockFile, $pid);
            }
        }

        return $result;
    }

    /**
     * Returns a URL to a video thumbnail
     *
     * @param $filePath         path to the original video or an Asset
     * @param $thumbnailOptions array of options for the thumbnail
     *
     * @return string           URL of the video thumbnail
     */
    public function getVideoThumbnailUrl($filePath, $thumbnailOptions): string
    {

        $result = "";
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            $path_parts = pathinfo($filePath);
            $destThumbnailFile = $path_parts['filename'];
            $destThumbnailPath = Craft::$app->config->get("transcoderPath", "transcoder");

            // Default options for video thumbnails
            $defaultOptions = Craft::$app->config->get("defaultThumbnailOptions", "transcoder");

            // Coalesce the passed in $thumbnailOptions with the $defaultOptions
            $thumbnailOptions = array_merge($defaultOptions, $thumbnailOptions);

            // Build the basic command for ffmpeg
            $ffmpegCmd = Craft::$app->config->get("ffmpegPath", "transcoder")
                .' -i '.escapeshellarg($filePath)
                .' -vcodec mjpeg'
                .' -vframes 1';

            // Adjust the scaling if desired
            list($destThumbnailFile, $ffmpegCmd) = $this->addScalingParams(
                $thumbnailOptions,
                $destThumbnailFile,
                $ffmpegCmd
            );

            // Set the timecode to get the thumbnail from if desired
            if (!empty($thumbnailOptions['timeInSecs'])) {
                $timeCode = gmdate("H:i:s", $thumbnailOptions['timeInSecs']);
                $ffmpegCmd .= ' -ss '.$timeCode.'.00';
                $destThumbnailFile .= '_'.$thumbnailOptions['timeInSecs'].'s';
            }

            // Create the directory if it isn't there already
            if (!file_exists($destThumbnailPath)) {
                mkdir($destThumbnailPath);
            }

            // Assemble the destination path and final ffmpeg command
            $destThumbnailFile .= '.jpg';
            $destThumbnailPath = $destThumbnailPath.$destThumbnailFile;
            $ffmpegCmd .= ' -f image2 -y '.escapeshellarg($destThumbnailPath).' >/dev/null 2>/dev/null';

            // If the thumbnail file already exists, return it.  Otherwise, generate it and return it
            if (file_exists($destThumbnailPath)) {
                $result = Craft::$app->config->get("transcoderUrl", "transcoder").$destThumbnailFile;
            } else {
                $shellOutput = shell_exec($ffmpegCmd);
                Craft::info($ffmpegCmd, __METHOD__);
                $result = Craft::$app->config->get("transcoderUrl", "transcoder").$destThumbnailFile;
            }
        }

        return $result;
    }

    /**
     * Extract information from a video
     *
     * @param $filePath
     *
     * @return array
     */
    public function getFileInfo($filePath): array
    {

        $result = null;
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            // Build the basic command for ffprobe
            $ffprobeOptions = Craft::$app->config->get("ffprobeOptions", "transcoder");
            $ffprobeCmd = Craft::$app->config->get("ffprobePath", "transcoder")
                .' '.$ffprobeOptions
                .' '.escapeshellarg($filePath);

            $shellOutput = shell_exec($ffprobeCmd);
            Craft::info($ffprobeCmd, __METHOD__);
            $result = json_decode($shellOutput, true);
            Craft::info(print_r($result, true), __METHOD__);
            Craft::dd($result);
        }

        return $result;
    }

    /**
     * Extract a file system path if $filePath is an Asset object
     *
     * @param $filePath
     *
     * @return string
     * @throws Exception
     */
    protected function getAssetPath($filePath): string
    {
        // If we're passed an Asset, extract the path from it
        if (is_object($filePath) && ($filePath instanceof Asset)) {
            $asset = $filePath;
            $assetVolume = $asset->getVolume();

            if (!(($assetVolume instanceof Local) || is_subclass_of($assetVolume, Local::class))) {
                throw new Exception(
                    Craft::t('transcoder', 'Paths not available for non-local asset sources')
                );
            }

            $sourcePath = rtrim($assetVolume->path, DIRECTORY_SEPARATOR);
            $sourcePath .= strlen($sourcePath) ? DIRECTORY_SEPARATOR : '';
            $folderPath = rtrim($asset->getFolder()->path, DIRECTORY_SEPARATOR);
            $folderPath .= strlen($folderPath) ? DIRECTORY_SEPARATOR : '';

            $filePath = $sourcePath.$folderPath.$asset->filename;
        }

        return $filePath;
    }

    /**
     * Set the width & height if desired
     *
     * @param $options
     * @param $destFile
     * @param $ffmpegCmd
     *
     * @return array
     */
    protected function addScalingParams($options, $destFile, $ffmpegCmd): array
    {
        if (!empty($options['width']) && !empty($options['height'])) {
            // Handle "none", "crop", and "letterbox" aspectRatios
            if (!empty($options['aspectRatio'])) {
                switch ($options['aspectRatio']) {
                    // Scale to the appropriate aspect ratio, padding
                    case "letterbox":
                        $letterboxColor = "";
                        if (!empty($options['letterboxColor'])) {
                            $letterboxColor = ":color=".$options['letterboxColor'];
                        }
                        $aspectRatio = ':force_original_aspect_ratio=decrease'
                            .',pad='.$options['width'].':'.$options['height'].':(ow-iw)/2:(oh-ih)/2'
                            .$letterboxColor;
                        break;
                    // Scale to the appropriate aspect ratio, cropping
                    case "crop":
                        $aspectRatio = ':force_original_aspect_ratio=increase'
                            .',crop='.$options['width'].':'.$options['height'];
                        break;
                    // No aspect ratio scaling at all
                    default:
                        $aspectRatio = ':force_original_aspect_ratio=disable';
                        $options['aspectRatio'] = "none";
                        break;
                }
                $destFile .= '_'.$options['aspectRatio'];
            }
            $sharpen = "";
            if (!empty($options['sharpen']) && ($options['sharpen'] !== false)) {
                $sharpen = ',unsharp=5:5:1.0:5:5:0.0';
            }
            $ffmpegCmd .= ' -vf "scale='
                .$options['width'].':'.$options['height']
                .$aspectRatio
                .$sharpen
                .'"';
            $destFile .= '_'.$options['width'].'x'.$options['height'];

            return [$destFile, $ffmpegCmd];
        }

        return [$destFile, $ffmpegCmd];
    }
}
