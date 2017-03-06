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

use Craft;
use craft\helpers\UrlHelper;

/**
 * @author    nystudio107
 * @package   Transcoder
 * @since     1.0.0
 */
class TranscoderVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Returns a URL to the transcoded video or "" if it doesn't exist (at which
     * time it will create it). By default, the video is never resized, and the
     * format is always .mp4
     *
     * @param $filePath
     * @param $videoOptions
     *
     * @return string
     */
    public function getVideoUrl($filePath, $videoOptions): string
    {
        $result = Transcoder::$plugin->transcoder->getVideoUrl($filePath, $videoOptions);

        return $result;
    }

    /**
     * Returns a URL to a video thumbnail
     *
     * @param $filePath
     * @param $thumbnailOptions
     *
     * @return string
     */
    public function getVideoThumbnailUrl($filePath, $thumbnailOptions): string
    {
        $result = Transcoder::$plugin->transcoder->getVideoThumbnailUrl($filePath, $thumbnailOptions);

        return $result;
    }

    /**
     * Extract information from a video/audio file
     *
     * @param $filePath
     *
     * @return array
     */
    public function getFileInfo($filePath): array
    {
        $result = Transcoder::$plugin->transcoder->getFileInfo($filePath);

        return $result;
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
        $result = "";
        $filename = Transcoder::$plugin->transcoder->getVideoFileName($filePath, $videoOptions);
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
        $result = "";
        $filePath = parse_url($url, PHP_URL_PATH);
        $filePath = $_SERVER['DOCUMENT_ROOT'].$filePath;
        if (file_exists($filePath)) {
            $urlParams = [
                'url' => $url,
            ];
            $result = UrlHelper::actionUrl('transcoder/default/download-file', $urlParams);
        }

        return $result;
    }
}
