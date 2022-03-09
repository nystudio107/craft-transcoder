<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\controllers;

use Craft;
use craft\errors\AssetDisallowedExtensionException;
use craft\helpers\Json as JsonHelper;
use craft\helpers\Path as PathHelper;
use craft\web\Controller;
use nystudio107\transcoder\Transcoder;
use yii\base\ExitException;
use yii\web\BadRequestHttpException;
use function count;
use function is_array;

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|bool|int $allowAnonymous = [
        'download-file',
        'progress',
    ];

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function beforeAction($action): bool
    {
        if (!Transcoder::$settings->enableDownloadFileEndpoint) {
            $this->allowAnonymous = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Force the download of a given $url.  We do it this way to prevent people
     * from downloading things that are outside of the server root.
     *
     * @param $url
     *
     * @throws AssetDisallowedExtensionException
     * @throws BadRequestHttpException
     * @throws ExitException
     */
    public function actionDownloadFile($url): void
    {
        $filePath = parse_url($url, PHP_URL_PATH);
        // Remove any relative paths
        if (!PathHelper::ensurePathIsContained($filePath)) {
            throw new BadRequestHttpException('Invalid resource path: ' . $filePath);
        }
        // Only work for `allowedFileExtensions` file extensions
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $allowedExtensions = Craft::$app->getConfig()->getGeneral()->allowedFileExtensions;
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new AssetDisallowedExtensionException("File “{$filePath}” cannot be downloaded because “{$extension}” is not allowed.");
        }

        $filePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        Craft::$app->getResponse()->sendFile(
            $filePath,
            null,
            ['inline' => false]
        );
        Craft::$app->end();
    }

    /**
     * Return a JSON-encoded array providing the progress of the transcoding:
     *
     * 'filename' => the name of the file
     * 'duration' => the duration of the video/audio stream
     * 'time' => the time of the current encoding
     * 'progress' => a percentage indicating how much of the encoding is done
     *
     * @param $filename
     *
     * @return string
     */
    public function actionProgress($filename): string
    {
        $result = [];
        $progressFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.progress';
        if (file_exists($progressFile)) {
            $content = @file_get_contents($progressFile);
            if ($content) {
                // get duration of source
                preg_match('/Duration: (.*?), start:/', $content, $matches);
                if (count($matches) > 0) {
                    $rawDuration = $matches[1];

                    // rawDuration is in 00:00:00.00 format. This converts it to seconds.
                    $ar = array_reverse(explode(':', $rawDuration));
                    $duration = (float)$ar[0];
                    if (!empty($ar[1])) {
                        $duration += (int)$ar[1] * 60;
                    }
                    if (!empty($ar[2])) {
                        $duration += (int)$ar[2] * 60 * 60;
                    }
                } else {
                    $duration = 'unknown'; // with GIF as input, duration is unknown
                }

                // Get the time in the file that is already encoded
                preg_match_all('/time=(.*?) bitrate/', $content, $matches);
                $rawTime = array_pop($matches);

                // this is needed if there is more than one match
                if (is_array($rawTime)) {
                    $rawTime = array_pop($rawTime);
                }

                //rawTime is in 00:00:00.00 format. This converts it to seconds.
                $ar = array_reverse(explode(':', $rawTime));
                $time = (float)$ar[0];
                if (!empty($ar[1])) {
                    $time += (int)$ar[1] * 60;
                }
                if (!empty($ar[2])) {
                    $time += (int)$ar[2] * 60 * 60;
                }

                //calculate the progress
                if ($duration !== 'unknown') {
                    $progress = round(($time / $duration) * 100);
                } else {
                    $progress = 'unknown';
                }

                // return results
                if ($progress !== 'unknown' && $progress < 100) {
                    $result = [
                        'filename' => $filename,
                        'duration' => $duration,
                        'time' => $time,
                        'progress' => $progress,
                    ];
                } elseif ($progress === 'unknown') {
                    $result = [
                        'filename' => $filename,
                        'duration' => 'unknown',
                        'time' => $time,
                        'progress' => 'unknown',
                        'message' => 'encoding GIF, can\'t determine duration',
                    ];
                }
            }
        }

        return JsonHelper::encode($result);
    }
}
