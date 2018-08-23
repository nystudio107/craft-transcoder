<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\variables;

use nystudio107\transcoder\Transcoder;

use craft\helpers\UrlHelper;

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class TranscoderVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Returns a URL to the transcoded video or "" if it doesn't exist (at which
     * time it will create it).
     *
     * @param $filePath
     * @param $videoOptions
     *
     * @return string
     */
    public function getVideoUrl($filePath, $videoOptions): string
    {
        return Transcoder::$plugin->transcode->getVideoUrl($filePath, $videoOptions);
    }

    /**
     * Returns a URL to a video thumbnail
     *
     * @param $filePath
     * @param $thumbnailOptions
     *
     * @return string|false|null URL or path of the video thumbnail
     */
    public function getVideoThumbnailUrl($filePath, $thumbnailOptions)
    {
        return Transcoder::$plugin->transcode->getVideoThumbnailUrl($filePath, $thumbnailOptions);
    }

    /**
     * Returns a URL to the transcoded audio file or "" if it doesn't exist
     * (at which time it will create it).
     *
     * @param $filePath
     * @param $audioOptions
     *
     * @return string
     */
    public function getAudioUrl($filePath, $audioOptions): string
    {
        return Transcoder::$plugin->transcode->getAudioUrl($filePath, $audioOptions);
    }

    /**
     * Extract information from a video/audio file
     *
     * @param      $filePath
     * @param bool $summary
     *
     * @return array
     */
    public function getFileInfo($filePath, $summary = false): array
    {
        return Transcoder::$plugin->transcode->getFileInfo($filePath, $summary);
    }

    /**
     * Get a video progress URL
     *
     * @param $filePath
     * @param $videoOptions
     *
     * @return string
     */
    public function getVideoProgressUrl($filePath, $videoOptions): string
    {
        $result = '';
        $filename = Transcoder::$plugin->transcode->getVideoFilename($filePath, $videoOptions);
        if (!empty($filename)) {
            $urlParams = [
                'filename' => $filename,
            ];
            $result = UrlHelper::actionUrl('transcoder/default/progress', $urlParams);
        }

        return $result;
    }

    /**
     * Get an audio progress URL
     *
     * @param $filePath
     * @param $audioOptions
     *
     * @return string
     */
    public function getAudioProgressUrl($filePath, $audioOptions): string
    {
        $result = '';
        $filename = Transcoder::$plugin->transcode->getAudioFilename($filePath, $audioOptions);
        if (!empty($filename)) {
            $urlParams = [
                'filename' => $filename,
            ];
            $result = UrlHelper::actionUrl('transcoder/default/progress', $urlParams);
        }

        return $result;
    }

    /**
     * Get a GIF progress URL
     *
     * @param $filePath
     * @param $gifOptions
     *
     * @return string
     */
    public function getGifProgressUrl($filePath, $gifOptions): string
    {
        $result = '';
        $filename = Transcoder::$plugin->transcode->getGifFilename($filePath, $gifOptions);
        if (!empty($filename)) {
            $urlParams = [
                'filename' => $filename,
            ];
            $result = UrlHelper::actionUrl('transcoder/default/progress', $urlParams);
        }

        return $result;
    }
    
    /**
     * Get a download URL
     *
     * @param $url
     *
     * @return string
     */
    public function getDownloadUrl($url): string
    {
        $result = '';
        $filePath = parse_url($url, PHP_URL_PATH);
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        if (file_exists($filePath)) {
            $urlParams = [
                'url' => $url,
            ];
            $result = UrlHelper::actionUrl('transcoder/default/download-file', $urlParams);
        }

        return $result;
    }
    
    /**
     * Returns a URL to a GIF file
     *
     * @param $filePath
     * @param $gifOptions
     *
     * @return string
     */
    public function getGifUrl($filePath, $gifOptions): string
    {
        return Transcoder::$plugin->transcode->getGifUrl($filePath, $gifOptions);
    }
}
