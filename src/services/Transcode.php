<?php
/**
 * Transcoder plugin for Craft CMS
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\fs\Local;
use craft\helpers\App;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json as JsonHelper;
use mikehaertl\shellcommand\Command as ShellCommand;
use nystudio107\transcoder\jobs\EncodeVideo;
use nystudio107\transcoder\jobs\RefreshVideoAsset;
use nystudio107\transcoder\Transcoder;
use RuntimeException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\validators\UrlValidator;
use function function_exists;
use function in_array;
use function is_bool;

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class Transcode extends Component
{
    // Constants
    // =========================================================================

    // Suffixes to add to the generated filename params
    protected const SUFFIX_MAP = [
        'videoFrameRate' => 'fps',
        'videoBitRate' => 'bps',
        'audioBitRate' => 'bps',
        'audioChannels' => 'c',
        'height' => 'h',
        'width' => 'w',
        'timeInSecs' => 's',
    ];

    // Params that should be excluded from being part of the generated filename
    protected const EXCLUDE_PARAMS = [
        'videoEncoder',
        'audioEncoder',
        'fileSuffix',
        'sharpen',
        'synchronous',
        'stripMetadata',
        'videoCodecOptions',
    ];

    // Mappings for getFileInfo() summary values
    protected const INFO_SUMMARY = [
        'format' => [
            'filename' => 'filename',
            'duration' => 'duration',
            'size' => 'size',
        ],
        'audio' => [
            'codec_name' => 'audioEncoder',
            'bit_rate' => 'audioBitRate',
            'sample_rate' => 'audioSampleRate',
            'channels' => 'audioChannels',
        ],
        'video' => [
            'codec_name' => 'videoEncoder',
            'bit_rate' => 'videoBitRate',
            'avg_frame_rate' => 'videoFrameRate',
            'height' => 'height',
            'width' => 'width',
        ],
    ];

    // Public Methods
    // =========================================================================

    /**
     * Returns a URL to the transcoded video or "" if it doesn't exist (at
     * which
     * time it will create it).
     *
     * @param string|Asset $filePath string  path to the original video -OR- an
     *                           Asset
     * @param array $videoOptions array   of options for the video
     * @param bool $generate whether the video should be encoded
     *
     * @return string       URL of the transcoded video or ""
     * @throws InvalidConfigException
     */
    public function getVideoUrl(
        string|Asset $filePath,
        array $videoOptions,
        bool $generate = true,
        bool $synchronous = false,
    ): string {
        $result = '';
        $settings = Transcoder::$plugin->getSettings();
        $outputInfo = $this->getVideoOutputInfo($filePath, $videoOptions);

        if ($outputInfo !== null) {
            $filePath = $outputInfo['sourcePath'];
            $destVideoPath = $outputInfo['directory'];
            $videoOptions = $outputInfo['videoOptions'];
            $thisEncoder = $outputInfo['encoder'];
            $watermarkPath = $outputInfo['watermarkPath'];

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -i ' . escapeshellarg($filePath);

            if ($watermarkPath !== null) {
                $ffmpegCmd .= ' -loop 1 -i ' . escapeshellarg($watermarkPath);
            }

            $ffmpegCmd .= ' -vcodec ' . $thisEncoder['videoCodec']
                . ' ' . $thisEncoder['videoCodecOptions']
                . ' -threads ' . $thisEncoder['threads'];

            // Set the framerate if desired
            if (!empty($videoOptions['videoFrameRate'])) {
                $ffmpegCmd .= ' -r ' . $videoOptions['videoFrameRate'];
            }

            // Set the bitrate if desired
            if (!empty($videoOptions['videoBitRate'])) {
                $ffmpegCmd .= ' -b:v ' . $videoOptions['videoBitRate'] . ' -maxrate ' . $videoOptions['videoBitRate'];
            }

            if ($watermarkPath !== null) {
                $ffmpegCmd .= ' -filter_complex ' . escapeshellarg($this->getVideoWatermarkFilter($videoOptions))
                    . ' -map ' . escapeshellarg('[transcoded]')
                    . ' -map ' . escapeshellarg('0:a?');
            } else {
                // Adjust the scaling if desired
                $ffmpegCmd = $this->addScalingFfmpegArgs(
                    $videoOptions,
                    $ffmpegCmd
                );
            }

            // Handle any audio transcoding
            if (empty($videoOptions['audioBitRate'])
                && empty($videoOptions['audioSampleRate'])
                && empty($videoOptions['audioChannels'])
            ) {
                // Just copy the audio if no options are provided
                $ffmpegCmd .= ' -c:a copy';
            } else {
                // Do audio transcoding based on the settings
                $ffmpegCmd .= ' -acodec ' . $thisEncoder['audioCodec'];
                if (!empty($videoOptions['audioBitRate'])) {
                    $ffmpegCmd .= ' -b:a ' . $videoOptions['audioBitRate'];
                }
                if (!empty($videoOptions['audioSampleRate'])) {
                    $ffmpegCmd .= ' -ar ' . $videoOptions['audioSampleRate'];
                }
                if (!empty($videoOptions['audioChannels'])) {
                    $ffmpegCmd .= ' -ac ' . $videoOptions['audioChannels'];
                }
                $ffmpegCmd .= ' ' . $thisEncoder['audioCodecOptions'];
            }

            // Create the directory if it isn't there already
            if (!is_dir($destVideoPath)) {
                try {
                    FileHelper::createDirectory($destVideoPath);
                } catch (Exception $e) {
                    Craft::error($e->getMessage(), __METHOD__);
                }
            }

            // File to store the video encoding progress in
            $progressFile = $outputInfo['progressFile'];

            // Assemble the destination path and final ffmpeg command
            $destVideoPath = $outputInfo['path'];
            $ffmpegCmd .= ' -f '
                . $thisEncoder['fileFormat']
                . ' -y ' . escapeshellarg($destVideoPath);

            if (!$synchronous) {
                $ffmpegCmd .= ' 1> ' . $progressFile . ' 2>&1 & echo $!';
            }

            // Make sure there isn't a lockfile for this video already
            $lockFile = $outputInfo['lockFile'];
            $oldPid = @file_get_contents($lockFile);
            if ($oldPid !== false) {
                // See if the process is running, and empty result means the process is still running
                // ref: https://stackoverflow.com/questions/3043978/how-to-check-if-a-process-id-pid-exists
                $oldPid = trim($oldPid);
                if ($oldPid !== '' && ctype_digit($oldPid)) {
                    $processState = [];
                    exec('kill -0 ' . (int)$oldPid . ' 2>&1', $processState);
                    if ($processState === []) {
                        return $result;
                    }
                }
                // It's finished transcoding, so delete the lockfile and progress file
                @unlink($lockFile);
                @unlink($progressFile);
            }

            // If the video file already exists and hasn't been modified, return it.  Otherwise, start it transcoding
            if (file_exists($destVideoPath)
                && filesize($destVideoPath) > 0
                && (@filemtime($destVideoPath) >= @filemtime($filePath))
            ) {
                $result = $this->getVersionedMediaUrl(
                    $outputInfo['url'],
                    $destVideoPath
                );
            // skip encoding
            } elseif (!$generate) {
                $result = '';
            } else {
                // Kick off the transcoding
                if ($synchronous) {
                    file_put_contents($lockFile, (string)getmypid());
                    $execution = $this->executeShellCommandWithStatus($ffmpegCmd);
                    $output = $execution['output'];
                    @unlink($lockFile);
                    @unlink($progressFile);

                    if ($execution['success'] && file_exists($destVideoPath) && filesize($destVideoPath) > 0) {
                        $result = $this->getVersionedMediaUrl(
                            $outputInfo['url'],
                            $destVideoPath
                        );
                    } else {
                        @unlink($destVideoPath);
                        Craft::error("Video encoding failed: $output", __METHOD__);
                    }

                    return $result;
                }

                $pid = $this->executeShellCommand($ffmpegCmd);
                Craft::info($ffmpegCmd . "\nffmpeg PID: " . $pid, __METHOD__);

                // Create a lockfile in tmp
                file_put_contents($lockFile, $pid);
            }
        }

        return $result;
    }

    /**
     * Returns a URL to a video thumbnail
     *
     * @param Asset|string $filePath path to the original video or an Asset
     * @param array $thumbnailOptions of options for the thumbnail
     * @param bool $generate whether the thumbnail should be
     *                                 generated if it doesn't exists
     * @param bool $asPath Whether we should return a path or not
     * @param bool $synchronous Whether FFmpeg should finish before returning
     *
     * @return string|false|null URL or path of the video thumbnail
     * @throws InvalidConfigException
     */
    public function getVideoThumbnailUrl(
        Asset|string $filePath,
        array $thumbnailOptions,
        bool $generate = true,
        bool $asPath = false,
        bool $synchronous = false,
    ): string|false|null {
        $result = null;
        $settings = Transcoder::$plugin->getSettings();
        $outputInfo = $this->getThumbnailOutputInfo($filePath);

        $filePath = $this->getAssetPath($filePath);

        if (!empty($filePath)) {
            $destThumbnailPath = $outputInfo['directory'];

            $thumbnailOptions = $this->coalesceOptions('defaultThumbnailOptions', $thumbnailOptions);

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -i ' . escapeshellarg($filePath)
                . ' -vcodec mjpeg'
                . ' -vframes 1';

            if (!empty($thumbnailOptions['preventBlackBars'])
                && !empty($thumbnailOptions['width'])
                && !empty($thumbnailOptions['height'])
            ) {
                $ffmpegCmd .= ' -filter_complex ' . escapeshellarg($this->getPosterBlackBarFilter($thumbnailOptions))
                    . ' -map ' . escapeshellarg('[poster]');
            } else {
                // Adjust the scaling if desired
                $ffmpegCmd = $this->addScalingFfmpegArgs(
                    $thumbnailOptions,
                    $ffmpegCmd
                );
            }

            // Set the timecode to get the thumbnail from if desired
            $thumbnailTime = $thumbnailOptions['posterSeekTimeInSecs'] ?? $thumbnailOptions['timeInSecs'] ?? null;
            if (!empty($thumbnailTime)) {
                $timeCode = gmdate('H:i:s', (int)$thumbnailTime);
                $ffmpegCmd .= ' -ss ' . $timeCode . '.00';
            }

            // Create the directory if it isn't there already
            if (!is_dir($destThumbnailPath)) {
                try {
                    FileHelper::createDirectory($destThumbnailPath);
                } catch (Exception $e) {
                    Craft::error($e->getMessage(), __METHOD__);
                }
            }

            $destThumbnailFile = $this->getFilename(
                $filePath,
                $thumbnailOptions,
                $this->getThumbnailFilenameExcludeParams()
            );

            // Assemble the destination path and final ffmpeg command
            $destThumbnailPath .= $destThumbnailFile;
            $ffmpegCmd .= ' -f image2 -y ' . escapeshellarg($destThumbnailPath);
            if (!$synchronous) {
                $ffmpegCmd .= ' >/dev/null 2>/dev/null &';
            }

            // If a non-empty thumbnail already exists, return it. Otherwise, generate it and return it.
            if (!file_exists($destThumbnailPath) || (int)@filesize($destThumbnailPath) <= 0) {
                @unlink($destThumbnailPath);
                if ($generate) {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $shellOutput = $this->executeShellCommand($ffmpegCmd);
                    Craft::info($ffmpegCmd, __METHOD__);

                    if ($synchronous && file_exists($destThumbnailPath) && filesize($destThumbnailPath) > 0) {
                        if ($asPath) {
                            return $destThumbnailPath;
                        }

                        return $this->getVersionedMediaUrl(
                            $outputInfo['url'] . $destThumbnailFile,
                            $destThumbnailPath
                        );
                    }

                    if ($synchronous) {
                        @unlink($destThumbnailPath);
                        Craft::error("Video poster generation failed: $shellOutput", __METHOD__);
                    }

                    // if ffmpeg fails which we can't check because the process is ran in the background
                    // don't return the future path of the image or else we can't check this in the front end
                } else {
                    Craft::info('Thumbnail does not exist, but not asked to generate it: ' . $filePath, __METHOD__);

                    // The file doesn't exist, and we weren't asked to generate it
                }
                return false;
            }
            // Return either a path or a URL
            if ($asPath) {
                $result = $destThumbnailPath;
            } else {
                $result = $this->getVersionedMediaUrl(
                    $outputInfo['url'] . $destThumbnailFile,
                    $destThumbnailPath
                );
            }
        }

        return $result;
    }

    /**
     * Return a configured poster URL, or an empty string if it is unavailable.
     */
    public function getVideoPosterUrl(
        Asset|string $filePath,
        string $formatHandle,
        bool $generate = false,
        bool $synchronous = false,
    ): string {
        $options = $this->getVideoPosterOptions($filePath, $formatHandle);
        if ($options === null) {
            return '';
        }

        $url = $this->getVideoThumbnailUrl($filePath, $options, $generate, false, $synchronous);
        return is_string($url) ? $url : '';
    }

    /**
     * Return configured poster URLs keyed by format handle.
     */
    public function getVideoPosterUrls(Asset|string $filePath, bool $generate = false, bool $synchronous = false): array
    {
        $urls = [];
        foreach (array_keys($this->getVideoPosterFormats()) as $formatHandle) {
            $urls[$formatHandle] = $this->getVideoPosterUrl($filePath, $formatHandle, $generate, $synchronous);
        }

        return $urls;
    }

    /**
     * Generate every configured poster inside the current process.
     */
    public function generateVideoPosters(Asset|string $filePath): array
    {
        return $this->getVideoPosterUrls($filePath, true, true);
    }

    /**
     * Return whether poster upload queueing needs its own job.
     *
     * @internal Used by the Asset upload event handler.
     */
    public function shouldQueueStandaloneVideoPostersOnUpload(): bool
    {
        $settings = Transcoder::$plugin->getSettings();

        return $settings->enableVideoPosters
            && $settings->queueVideoPostersOnAssetUpload
            && !$settings->queueVideosOnAssetUpload;
    }

    /**
     * Queue invalidation and regeneration after an integration replaces a video asset.
     *
     * @return array{queued: bool, jobId: mixed}
     */
    public function refreshVideoAsset(Asset $asset): array
    {
        $this->validateRefreshVideoAsset($asset);

        $jobId = Craft::$app->getQueue()->push(new RefreshVideoAsset([
            'assetId' => (int)$asset->id,
        ]));
        if ($jobId === null) {
            throw new RuntimeException("Unable to queue video refresh for asset #{$asset->id}.");
        }

        Craft::info("Queued video refresh for asset #{$asset->id}; refresh job ID: $jobId", __METHOD__);

        return [
            'queued' => true,
            'jobId' => $jobId,
        ];
    }

    /**
     * Perform queued cleanup and queue the existing video encoder.
     *
     * @internal Used by RefreshVideoAsset.
     * @return array{removedFiles: int, queued: bool, jobId: mixed, active?: bool}
     */
    public function performVideoAssetRefresh(Asset $asset): array
    {
        $this->validateRefreshVideoAsset($asset);
        $removedFiles = 0;
        $encodingActive = false;

        $locked = $this->runVideoAssetWork($asset, function() use ($asset, &$removedFiles, &$encodingActive): void {
            $targets = $this->getVideoAssetRefreshTargets($asset);
            if ($this->isVideoEncodingActive($targets['temporary'])) {
                $encodingActive = true;
                return;
            }

            $removedFiles = $this->removeVideoAssetRefreshTargets($targets);
        });

        if (!$locked || $encodingActive) {
            return [
                'removedFiles' => 0,
                'queued' => false,
                'jobId' => null,
                'active' => true,
            ];
        }

        $settings = Transcoder::$plugin->getSettings();
        $queue = Craft::$app->getQueue();
        $queueDelay = max(0, (int)App::parseEnv((string)$settings->videoQueueDelaySeconds));
        if ($queueDelay > 0) {
            $queue = $queue->delay($queueDelay);
        }
        $jobId = $queue->push(new EncodeVideo([
            'assetId' => (int)$asset->id,
            'videoOptions' => $settings->queuedVideoOptions,
        ]));
        if ($jobId === null) {
            Craft::error(
                "Video refresh handoff failed for asset #{$asset->id}; removed files: $removedFiles; follow-up encode job ID: none",
                __METHOD__
            );
            throw new RuntimeException("Unable to queue replacement video encoding for asset #{$asset->id}.");
        }

        Craft::info(
            "Refreshed video asset #{$asset->id}; removed files: $removedFiles; follow-up encode job ID: $jobId",
            __METHOD__
        );

        return [
            'removedFiles' => $removedFiles,
            'queued' => true,
            'jobId' => $jobId,
        ];
    }

    /**
     * Run asset-specific work under a non-blocking process lock.
     *
     * @internal Used by Transcoder queue jobs.
     */
    public function runVideoAssetWork(Asset $asset, callable $callback): bool
    {
        return $this->runMediaAssetWork($asset, 'video', $callback);
    }

    /**
     * Run GIF asset work under a non-blocking process lock.
     *
     * @internal Used by Transcoder queue jobs.
     */
    public function runGifAssetWork(Asset $asset, callable $callback): bool
    {
        return $this->runMediaAssetWork($asset, 'gif', $callback);
    }

    /**
     * Run audio asset work under a non-blocking process lock.
     *
     * @internal Used by Transcoder queue jobs.
     */
    public function runAudioAssetWork(Asset $asset, callable $callback): bool
    {
        return $this->runMediaAssetWork($asset, 'audio', $callback);
    }

    /**
     * Run media asset work under a non-blocking process lock.
     */
    protected function runMediaAssetWork(Asset $asset, string $mediaType, callable $callback): bool
    {
        if (!$asset->id) {
            return false;
        }

        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "transcoder-$mediaType-asset-" . (int)$asset->id . '.lock';
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Unable to create the Transcoder $mediaType asset lock.");
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        try {
            ftruncate($handle, 0);
            fwrite($handle, (string)getmypid());
            fflush($handle);
            $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return true;
    }

    /**
     * Returns a URL to the transcoded audio file or "" if it doesn't exist
     * (at which time it will create it).
     *
     * @param Asset|string $filePath path to the original audio file -OR- an Asset
     * @param array $audioOptions array of options for the audio file
     *
     * @return string       URL of the transcoded audio file or ""
     * @throws InvalidConfigException
     */
    public function getAudioUrl(Asset|string $filePath, array $audioOptions): string
    {
        $result = '';
        $settings = Transcoder::$plugin->getSettings();
        $subfolder = '';

        // sub folder check
        if (($filePath instanceof Asset) && $settings['createSubfolders']) {
            $subfolder = $filePath->folderPath;
        }

        $filePath = $this->getAssetPath($filePath);

        if (!empty($filePath)) {
            $destAudioPath = $settings['transcoderPaths']['audio'] ?? $settings['transcoderPaths']['default'];
            $destAudioPath .= $subfolder;
            $destAudioPath = App::parseEnv($destAudioPath);

            $audioOptions = $this->coalesceOptions('defaultAudioOptions', $audioOptions);

            // Get the audio encoder presets to use
            $audioEncoders = $settings['audioEncoders'];
            $thisEncoder = $audioEncoders[$audioOptions['audioEncoder']];

            $audioOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -i ' . escapeshellarg($filePath)
                . ' -acodec ' . $thisEncoder['audioCodec']
                . ' ' . $thisEncoder['audioCodecOptions']
                . ' -vn'
                . ' -threads ' . $thisEncoder['threads'];

            // Set the bitrate if desired
            if (!empty($audioOptions['audioBitRate'])) {
                $ffmpegCmd .= ' -b:a ' . $audioOptions['audioBitRate'];
            }
            // Set the sample rate if desired
            if (!empty($audioOptions['audioSampleRate'])) {
                $ffmpegCmd .= ' -ar ' . $audioOptions['audioSampleRate'];
            }
            // Set the audio channels if desired
            if (!empty($audioOptions['audioChannels'])) {
                $ffmpegCmd .= ' -ac ' . $audioOptions['audioChannels'];
            }
            $ffmpegCmd .= ' ' . $thisEncoder['audioCodecOptions'];

            if (!empty($audioOptions['seekInSecs'])) {
                $ffmpegCmd .= ' -ss ' . $audioOptions['seekInSecs'];
            }

            if (!empty($audioOptions['timeInSecs'])) {
                $ffmpegCmd .= ' -t ' . $audioOptions['timeInSecs'];
            }

            // Create the directory if it isn't there already
            if (!is_dir($destAudioPath)) {
                try {
                    FileHelper::createDirectory($destAudioPath);
                } catch (Exception $e) {
                    Craft::error($e->getMessage(), __METHOD__);
                }
            }

            $destAudioFile = $this->getFilename($filePath, $audioOptions);

            // File to store the audio encoding progress in
            $progressFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $destAudioFile . '.progress';

            // Assemble the destination path and final ffmpeg command
            $destAudioPath .= $destAudioFile;
            // Handle the `stripMetadata` setting
            $stripMetadata = false;
            if (!empty($audioOptions['stripMetadata'])) {
                $stripMetadata = $audioOptions['stripMetadata'];
            }
            if ($stripMetadata) {
                $ffmpegCmd .= ' -map_metadata -1 ';
            }
            // Add the file format
            $ffmpegCmd .= ' -f '
                . $thisEncoder['fileFormat']
                . ' -y ' . escapeshellarg($destAudioPath);
            // Handle the `synchronous` setting
            $synchronous = false;
            if (!empty($audioOptions['synchronous'])) {
                $synchronous = $audioOptions['synchronous'];
            }
            $lockFile = $this->getMediaLockFile('audio', $destAudioPath);
            $oldPid = @file_get_contents($lockFile);
            if ($oldPid !== false) {
                // See if the process is running, and empty result means the process is still running
                // ref: https://stackoverflow.com/questions/3043978/how-to-check-if-a-process-id-pid-exists
                $oldPid = trim($oldPid);
                if ($oldPid !== '' && ctype_digit($oldPid)) {
                    $processState = [];
                    exec('kill -0 ' . (int)$oldPid . ' 2>&1', $processState);
                    if ($processState === []) {
                        return $result;
                    }
                }
                // It's finished transcoding, so delete the lockfile and progress file
                @unlink($lockFile);
                @unlink($progressFile);
            }
            if (!$synchronous) {
                $ffmpegCmd .= ' 1> ' . $progressFile . ' 2>&1 & echo $!';
            }

            // If the audio file already exists and hasn't been modified, return it.  Otherwise, start it transcoding
            if (file_exists($destAudioPath)
                && filesize($destAudioPath) > 0
                && (@filemtime($destAudioPath) >= @filemtime($filePath))
            ) {
                $url = $settings['transcoderUrls']['audio'] ?? $settings['transcoderUrls']['default'];
                $url .= $subfolder;
                $result = App::parseEnv($url) . $destAudioFile;
            } else {
                // Kick off the transcoding
                $execution = $synchronous
                    ? $this->executeShellCommandWithStatus($ffmpegCmd)
                    : ['success' => true, 'output' => $this->executeShellCommand($ffmpegCmd)];
                $output = $execution['output'];

                if ($synchronous) {
                    Craft::info($ffmpegCmd, __METHOD__);
                    if ($execution['success'] && file_exists($destAudioPath) && filesize($destAudioPath) > 0) {
                        $url = $settings['transcoderUrls']['audio'] ?? $settings['transcoderUrls']['default'];
                        $url .= $subfolder;
                        $result = App::parseEnv($url) . $destAudioFile;
                    } else {
                        @unlink($destAudioPath);
                        Craft::error("Audio encoding failed: $output", __METHOD__);
                    }
                } else {
                    Craft::info($ffmpegCmd . "\nffmpeg PID: " . $output, __METHOD__);
                    // Create a lockfile in tmp
                    file_put_contents($lockFile, $output);
                }
            }
        }

        return $result;
    }

    /**
     * Extract information from a video/audio file
     *
     * @param Asset|string $filePath
     * @param bool $summary
     *
     * @return null|array
     * @throws InvalidConfigException
     */
    public function getFileInfo(Asset|string $filePath, bool $summary = false): ?array
    {
        $result = null;
        $settings = Transcoder::$plugin->getSettings();
        $filePath = $this->getAssetPath($filePath);

        if (!empty($filePath)) {
            // Build the basic command for ffprobe
            $ffprobeOptions = $settings['ffprobeOptions'];
            $ffprobeCmd = $settings['ffprobePath']
                . ' ' . $ffprobeOptions
                . ' ' . escapeshellarg($filePath);

            $shellOutput = $this->executeShellCommand($ffprobeCmd);
            Craft::info($ffprobeCmd, __METHOD__);
            $result = JsonHelper::decodeIfJson($shellOutput, true);
            Craft::info(print_r($result, true), __METHOD__);
            // Handle the case it not being JSON
            if (!is_array($result)) {
                $result = [];
            }
            // Trim down the arrays to just a summary
            if ($summary && !empty($result)) {
                $summaryResult = [];
                foreach ($result as $topLevelKey => $topLevelValue) {
                    switch ($topLevelKey) {
                        // Format info
                        case 'format':
                            foreach (self::INFO_SUMMARY['format'] as $settingKey => $settingValue) {
                                if (!empty($topLevelValue[$settingKey])) {
                                    $summaryResult[$settingValue] = $topLevelValue[$settingKey];
                                }
                            }
                            break;
                        // Stream info
                        case 'streams':
                            foreach ($topLevelValue as $stream) {
                                $infoSummaryType = $stream['codec_type'];
                                if (in_array($infoSummaryType, self::INFO_SUMMARY, false)) {
                                    foreach (self::INFO_SUMMARY[$infoSummaryType] as $settingKey => $settingValue) {
                                        if (!empty($stream[$settingKey])) {
                                            $summaryResult[$settingValue] = $stream[$settingKey];
                                        }
                                    }
                                }
                            }
                            break;
                        // Unknown info
                        default:
                            break;
                    }
                }
                // Handle cases where the framerate is returned as XX/YY
                if (!empty($summaryResult['videoFrameRate'])
                    && (str_contains($summaryResult['videoFrameRate'], '/'))
                ) {
                    $parts = explode('/', $summaryResult['videoFrameRate']);
                    $summaryResult['videoFrameRate'] = (float)$parts[0] / (float)$parts[1];
                }
                $result = $summaryResult;
            }
        }

        return $result;
    }

    /**
     * Get the name of a video file from a path and options
     *
     * @param Asset|string $filePath
     * @param array $videoOptions
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function getVideoFilename(Asset|string $filePath, array $videoOptions): string
    {
        return $this->getVideoOutputInfo($filePath, $videoOptions)['filename'] ?? '';
    }

    /**
     * Get the name of an audio file from a path and options
     *
     * @param Asset|string $filePath
     * @param array $audioOptions
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function getAudioFilename(Asset|string $filePath, array $audioOptions): string
    {
        $settings = Transcoder::$plugin->getSettings();
        $audioOptions = $this->coalesceOptions('defaultAudioOptions', $audioOptions);

        // Get the video encoder presets to use
        $audioEncoders = $settings['audioEncoders'];
        $thisEncoder = $audioEncoders[$audioOptions['audioEncoder']];

        $audioOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

        return $this->getFilename($filePath, $audioOptions);
    }

    /**
     * Get the name of a gif video file from a path and options
     *
     * @param Asset|string $filePath
     * @param array $gifOptions
     *
     * @return string
     * @throws InvalidConfigException
     */
    public function getGifFilename(Asset|string $filePath, array $gifOptions): string
    {
        $settings = Transcoder::$plugin->getSettings();
        $gifOptions = $this->coalesceOptions('defaultGifOptions', $gifOptions);

        // Get the video encoder presets to use
        $videoEncoders = $settings['videoEncoders'];
        $thisEncoder = $videoEncoders[$gifOptions['videoEncoder']];

        $gifOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

        return $this->getFilename($filePath, $gifOptions);
    }

    /**
     * Handle generated a thumbnail for the Control Panel
     *
     * @param DefineAssetThumbUrlEvent $event
     *
     * @return null|false|string
     * @throws InvalidConfigException
     */
    public function handleGetAssetThumbPath(DefineAssetThumbUrlEvent $event): null|false|string
    {
        $asset = $this->resolveControlPanelThumbnailAsset($event->asset);
        if ($this->isTemporaryUploadAsset($asset)) {
            Craft::info(
                "Skipped Control Panel video thumbnail generation for temporary asset #{$asset->id}.",
                __METHOD__
            );
            return null;
        }

        $options = [
            'width' => $event->width,
            'height' => $event->height,
        ];
        return $this->getVideoThumbnailUrl($asset, $options);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns a URL to an encoded GIF file (mp4)
     *
     * @param Asset|string $filePath path to the original video or an Asset
     * @param array $gifOptions of options for the GIF file
     * @param bool $synchronous whether ffmpeg should finish before returning
     *
     * @return string|false|null URL or path of the GIF file
     * @throws InvalidConfigException
     */

    public function getGifUrl(Asset|string $filePath, array $gifOptions, bool $synchronous = false): string|false|null
    {
        $result = '';
        $settings = Transcoder::$plugin->getSettings();
        $subfolder = '';

        // sub folder check
        if (($filePath instanceof Asset) && $settings['createSubfolders']) {
            $subfolder = $filePath->folderPath;
        }

        $filePath = $this->getAssetPath($filePath);

        if (!empty($filePath)) {
            // Dest path
            $destVideoPath = $settings['transcoderPaths']['gif'] ?? $settings['transcoderPaths']['default'];
            $destVideoPath .= $subfolder;
            $destVideoPath = App::parseEnv($destVideoPath);

            // Options
            $gifOptions = $this->coalesceOptions('defaultGifOptions', $gifOptions);

            // Get the video encoder presets to use
            $videoEncoders = $settings['videoEncoders'];
            $thisEncoder = $videoEncoders[$gifOptions['videoEncoder']];
            $gifOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -f gif'
                . ' -i ' . escapeshellarg($filePath)
                . ' -vcodec ' . $thisEncoder['videoCodec']
                . ' ' . $thisEncoder['videoCodecOptions'];


            // Create the directory if it isn't there already
            if (!is_dir($destVideoPath)) {
                try {
                    FileHelper::createDirectory($destVideoPath);
                } catch (Exception $e) {
                    Craft::error($e->getMessage(), __METHOD__);
                }
            }

            $destVideoFile = $this->getFilename($filePath, $gifOptions);

            // File to store the video encoding progress in
            $progressFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $destVideoFile . '.progress';

            // Assemble the destination path and final ffmpeg command
            $destVideoPath .= $destVideoFile;
            $ffmpegCmd .= ' -y ' . escapeshellarg($destVideoPath);
            if (!$synchronous) {
                $ffmpegCmd .= ' 1> ' . $progressFile . ' 2>&1 & echo $!';
            }

            // Make sure there isn't a lockfile for this GIF already
            $lockFile = $this->getMediaLockFile('gif', $destVideoPath);
            $oldPid = @file_get_contents($lockFile);
            if ($oldPid !== false) {
                // See if the process is running, and empty result means the process is still running
                // ref: https://stackoverflow.com/questions/3043978/how-to-check-if-a-process-id-pid-exists
                $oldPid = trim($oldPid);
                if ($oldPid !== '' && ctype_digit($oldPid)) {
                    $processState = [];
                    exec('kill -0 ' . (int)$oldPid . ' 2>&1', $processState);
                    if ($processState === []) {
                        return $result;
                    }
                }
                // It's finished transcoding, so delete the lockfile and progress file
                @unlink($lockFile);
                @unlink($progressFile);
            }

            // If the GIF output already exists and hasn't been modified, return it. Otherwise, start transcoding.
            if (file_exists($destVideoPath)
                && filesize($destVideoPath) > 0
                && (@filemtime($destVideoPath) >= @filemtime($filePath))
            ) {
                $url = $settings['transcoderUrls']['gif'] ?? $settings['transcoderUrls']['default'];
                $url .= $subfolder;
                $result = App::parseEnv($url) . $destVideoFile;
            } else {
                // Kick off the transcoding
                $execution = $synchronous
                    ? $this->executeShellCommandWithStatus($ffmpegCmd)
                    : ['success' => true, 'output' => $this->executeShellCommand($ffmpegCmd)];
                $output = $execution['output'];
                if ($synchronous) {
                    if ($execution['success'] && file_exists($destVideoPath) && filesize($destVideoPath) > 0) {
                        $url = $settings['transcoderUrls']['gif'] ?? $settings['transcoderUrls']['default'];
                        $url .= $subfolder;
                        return App::parseEnv($url) . $destVideoFile;
                    }

                    @unlink($destVideoPath);
                    Craft::error("GIF encoding failed: $output", __METHOD__);
                    return '';
                }

                Craft::info($ffmpegCmd . "\nffmpeg PID: " . $output, __METHOD__);

                // Create a lockfile in tmp
                file_put_contents($lockFile, $output);
            }
        }

        return $result;
    }

    /**
     * Get the name of a file from a path and options
     *
     * @param Asset|string $filePath
     * @param array $options
     *
     * @return string
     * @throws InvalidConfigException
     */
    protected function getFilename(Asset|string $filePath, array $options, ?array $excludeParams = null): string
    {
        $settings = Transcoder::$plugin->getSettings();
        $excludeParams ??= self::EXCLUDE_PARAMS;
        $filePath = $this->getAssetPath($filePath);

        $validator = new UrlValidator();
        $error = '';
        if ($validator->validate($filePath, $error)) {
            $urlParts = parse_url($filePath);
            $pathParts = pathinfo($urlParts['path']);
        } else {
            $pathParts = pathinfo($filePath);
        }
        $fileName = $pathParts['filename'];

        // Add our options to the file name
        foreach ($options as $key => $value) {
            if (isset($value)) {
                $suffix = '';
                if (!empty(self::SUFFIX_MAP[$key])) {
                    $suffix = self::SUFFIX_MAP[$key];
                }
                if (is_bool($value)) {
                    $value = $value ? $key : 'no' . $key;
                }
                if (!in_array($key, $excludeParams, true)) {
                    $fileName .= '_' . $value . $suffix;
                }
            }
        }
        // See if we should use a hash instead
        if ($settings['useHashedNames']) {
            $fileName = $pathParts['filename'] . md5($fileName);
        }
        $fileName .= $options['fileSuffix'];

        return $fileName;
    }

    /**
     * Return filename options excluded by the selected video filename strategy.
     */
    protected function getVideoFilenameExcludeParams(array $videoOptions): array
    {
        if (Transcoder::$plugin->getSettings()->videoFilenameStrategy === 'source') {
            return array_values(array_unique(array_merge(self::EXCLUDE_PARAMS, array_keys($videoOptions))));
        }

        return self::EXCLUDE_PARAMS;
    }

    /**
     * Add a cache version from the bytes that are actually on disk.
     */
    protected function getVersionedMediaUrl(string $url, string $path): string
    {
        clearstatcache(true, $path);
        $modifiedAt = @filemtime($path);
        if ($modifiedAt === false) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $modifiedAt;
    }

    /**
     * Validate the public replacement-refresh contract.
     */
    protected function validateRefreshVideoAsset(Asset $asset): void
    {
        if (!$asset->id || AssetsHelper::getFileKindByExtension($asset->filename) !== Asset::KIND_VIDEO) {
            throw new RuntimeException('Transcoder can only refresh a persisted video Asset.');
        }
    }

    /**
     * Resolve the configured output subfolder from an Asset or URL/path segment.
     */
    protected function getSubfolderFromPath(Asset|string $filePath): string
    {
        $settings = Transcoder::$plugin->getSettings();
        if ($filePath instanceof Asset && $settings->createSubfolders) {
            $folderPath = $this->getAssetFolderPath($filePath);
            return $folderPath === '' ? '' : $folderPath . DIRECTORY_SEPARATOR;
        }

        $segment = (int)App::parseEnv((string)$settings->subfolderUrlSegment);
        if ($segment < 1 || !is_string($filePath)) {
            return '';
        }

        $urlPath = parse_url($filePath, PHP_URL_PATH);
        if (!is_string($urlPath)) {
            return '';
        }

        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', $urlPath)),
            static fn(string $value): bool => $value !== ''
        ));

        if (!isset($segments[$segment - 1])) {
            return '';
        }

        $subfolder = rawurldecode($segments[$segment - 1]);
        if ($subfolder === '.'
            || $subfolder === '..'
            || str_contains($subfolder, "\0")
            || str_contains($subfolder, '/')
            || str_contains($subfolder, '\\')
        ) {
            Craft::warning('Ignored an unsafe generated media subfolder from a string input.', __METHOD__);
            return '';
        }

        return $subfolder . DIRECTORY_SEPARATOR;
    }

    /**
     * Resolve an Asset folder from Craft's folder model, with hydrated Asset
     * properties as fallbacks.
     */
    protected function getAssetFolderPath(Asset $asset): string
    {
        $candidates = [];
        try {
            $candidates[] = (string)$asset->getFolder()->path;
        } catch (InvalidConfigException) {
        }

        $candidates[] = (string)($asset->folderPath ?? '');
        try {
            $assetPath = str_replace('\\', '/', $asset->getPath());
            $candidates[] = dirname($assetPath);
        } catch (InvalidConfigException) {
        }

        foreach ($candidates as $candidate) {
            $candidate = trim(str_replace('\\', '/', $candidate), '/');
            if ($candidate === '' || $candidate === '.') {
                continue;
            }

            $segments = explode('/', $candidate);
            if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
                Craft::warning("Ignored an unsafe output folder for asset #{$asset->id}.", __METHOD__);
                continue;
            }

            return implode(DIRECTORY_SEPARATOR, $segments);
        }

        return '';
    }

    /**
     * Return normalized filesystem and public URL directories for thumbnails.
     *
     * @return array{subfolder: string, directory: string, url: string}
     */
    protected function getThumbnailOutputInfo(Asset|string $filePath): array
    {
        $settings = Transcoder::$plugin->getSettings();
        $subfolder = $this->getSubfolderFromPath($filePath);
        $directory = (string)App::parseEnv(
            $settings->transcoderPaths['thumbnail'] ?? $settings->transcoderPaths['default']
        );
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $url = (string)App::parseEnv(
            $settings->transcoderUrls['thumbnail'] ?? $settings->transcoderUrls['default']
        );
        $url = rtrim($url, '/') . '/';

        if ($subfolder !== '') {
            $directory .= trim($subfolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $url .= trim(str_replace('\\', '/', $subfolder), '/') . '/';
        }

        return [
            'subfolder' => $subfolder,
            'directory' => $directory,
            'url' => $url,
        ];
    }

    /**
     * Reload an incomplete Control Panel event Asset after Craft has persisted it.
     */
    protected function resolveControlPanelThumbnailAsset(Asset $asset): Asset
    {
        $folderPath = trim((string)($asset->folderPath ?? ''));
        if ($asset->id && ($folderPath === '' || $this->isTemporaryUploadAsset($asset))) {
            $persistedAsset = Asset::find()->id($asset->id)->one();
            if ($persistedAsset instanceof Asset) {
                return $persistedAsset;
            }
        }

        return $asset;
    }

    /**
     * Return whether Craft has not moved an uploaded Asset into its final folder yet.
     *
     * @internal Used by Control Panel and queue jobs.
     */
    public function isTemporaryUploadAsset(Asset $asset): bool
    {
        if ($asset->getVolumeId() === null) {
            return true;
        }

        $references = [$this->getAssetFolderPath($asset)];
        try {
            $references[] = $asset->getPath();
        } catch (InvalidConfigException) {
        }

        foreach ($references as $reference) {
            $reference = str_replace('\\', '/', trim((string)$reference));
            if (preg_match('~(?:^|/)user_[^/]+(?:/|$)~i', $reference) === 1
                || preg_match('~(?:^|/)(?:storage/)?runtime/assets/tempuploads(?:/|$)~i', $reference) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve every output value shared by video encoding and refresh cleanup.
     *
     * @return array{
     *     sourcePath: string,
     *     subfolder: string,
     *     directory: string,
     *     filename: string,
     *     path: string,
     *     url: string,
     *     lockFile: string,
     *     progressFile: string,
     *     videoOptions: array,
     *     encoder: array,
     *     watermarkPath: ?string
     * }|null
     */
    protected function getVideoOutputInfo(Asset|string $filePath, array $videoOptions): ?array
    {
        $settings = Transcoder::$plugin->getSettings();
        $subfolder = $this->getSubfolderFromPath($filePath);
        $sourcePath = $this->getAssetPath($filePath);
        if ($sourcePath === '') {
            return null;
        }

        $videoOptions = $this->coalesceOptions('defaultVideoOptions', $videoOptions);
        $videoEncoders = $settings->videoEncoders;
        $encoder = $videoEncoders[$videoOptions['videoEncoder']];
        $videoOptions['fileSuffix'] = $encoder['fileSuffix'];
        $watermarkPath = $this->getVideoWatermarkPath();
        if ($watermarkPath !== null) {
            $videoOptions['watermark'] = $this->getVideoWatermarkFingerprint($watermarkPath);
        }

        $directory = (string)App::parseEnv(
            $settings->transcoderPaths['video'] ?? $settings->transcoderPaths['default']
        );
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $urlDirectory = (string)App::parseEnv(
            $settings->transcoderUrls['video'] ?? $settings->transcoderUrls['default']
        );
        $urlDirectory = rtrim($urlDirectory, '/') . '/';
        if ($subfolder !== '') {
            $directory .= trim($subfolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $urlDirectory .= trim(str_replace('\\', '/', $subfolder), '/') . '/';
        }
        $filename = $this->getFilename(
            $sourcePath,
            $videoOptions,
            $this->getVideoFilenameExcludeParams($videoOptions)
        );

        return [
            'sourcePath' => $sourcePath,
            'subfolder' => $subfolder,
            'directory' => $directory,
            'filename' => $filename,
            'path' => $directory . $filename,
            'url' => $urlDirectory . $filename,
            'lockFile' => $this->getMediaLockFile('video', $directory . $filename),
            'progressFile' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.progress',
            'videoOptions' => $videoOptions,
            'encoder' => $encoder,
            'watermarkPath' => $watermarkPath,
        ];
    }

    /**
     * Return exact files managed by the configured automatic video workflow.
     *
     * @return array{output: string[], temporary: string[]}
     */
    protected function getVideoAssetRefreshTargets(Asset $asset): array
    {
        $settings = Transcoder::$plugin->getSettings();
        $videoOutput = $this->getVideoOutputInfo($asset, $settings->queuedVideoOptions);
        if ($videoOutput === null) {
            throw new RuntimeException('Unable to resolve the source or output path for the video Asset.');
        }

        $outputs = [$videoOutput['path']];
        $temporary = [
            $videoOutput['lockFile'],
            $videoOutput['progressFile'],
        ];

        if ($settings->enableVideoPosters) {
            $posterDirectory = $this->getThumbnailOutputInfo($asset)['directory'];
            foreach (array_keys($this->getVideoPosterFormats()) as $formatHandle) {
                $options = $this->getVideoPosterOptions($asset, $formatHandle);
                if ($options !== null) {
                    $options = $this->coalesceOptions('defaultThumbnailOptions', $options);
                    $outputs[] = $posterDirectory . $this->getFilename(
                        $asset,
                        $options,
                        $this->getThumbnailFilenameExcludeParams()
                    );
                }
            }
        }

        return [
            'output' => array_values(array_unique($outputs)),
            'temporary' => array_values(array_unique($temporary)),
        ];
    }

    /**
     * Return whether the configured video encoder still owns its process lock.
     *
     * @param string[] $temporaryPaths
     */
    protected function isVideoEncodingActive(array $temporaryPaths): bool
    {
        foreach ($temporaryPaths as $path) {
            if (!str_ends_with($path, '.lock') || !is_file($path)) {
                continue;
            }

            $pid = trim((string)@file_get_contents($path));
            if ($pid === '' || !ctype_digit($pid)) {
                continue;
            }

            $processState = [];
            exec('kill -0 ' . (int)$pid . ' 2>&1', $processState);
            if ($processState === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete exact refresh targets after validating their managed roots.
     *
     * @param array{output: string[], temporary: string[]} $targets
     */
    protected function removeVideoAssetRefreshTargets(array $targets): int
    {
        $removed = 0;
        foreach ($targets as $kind => $paths) {
            foreach ($paths as $path) {
                if (!is_file($path) && !is_link($path)) {
                    continue;
                }
                if (!$this->isSafeVideoAssetRefreshPath($path, $kind)) {
                    throw new RuntimeException('Refusing to remove a Transcoder file outside its managed roots.');
                }
                if (!@unlink($path) && (is_file($path) || is_link($path))) {
                    throw new RuntimeException('Unable to remove a managed Transcoder file.');
                }
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Verify a deletion target without following a file symlink outside its root.
     */
    protected function isSafeVideoAssetRefreshPath(string $path, string $kind): bool
    {
        $settings = Transcoder::$plugin->getSettings();
        if ($kind === 'temporary') {
            $roots = [sys_get_temp_dir()];
        } elseif ($kind === 'output') {
            $roots = [];
            foreach (['default', 'video', 'thumbnail'] as $key) {
                if (!empty($settings->transcoderPaths[$key])) {
                    $roots[] = (string)App::parseEnv($settings->transcoderPaths[$key]);
                }
            }
        } else {
            return false;
        }

        $parent = realpath(dirname(FileHelper::normalizePath($path)));
        if ($parent === false) {
            return false;
        }

        foreach ($roots as $root) {
            $root = realpath(rtrim(FileHelper::normalizePath($root), DIRECTORY_SEPARATOR));
            if ($root !== false && ($parent === $root || str_starts_with($parent, $root . DIRECTORY_SEPARATOR))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a subfolder-aware internal lock path without changing public progress URLs.
     */
    protected function getMediaLockFile(string $mediaType, string $outputPath): string
    {
        $normalizedPath = FileHelper::normalizePath($outputPath);

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'transcoder-' . $mediaType . '-' . sha1($normalizedPath) . '.lock';
    }

    /**
     * Resolve the configured watermark input.
     */
    protected function getVideoWatermarkPath(): ?string
    {
        $settings = Transcoder::$plugin->getSettings();
        if (!$settings->enableVideoWatermark || $settings->videoWatermarkPath === '') {
            return null;
        }

        $path = (string)App::parseEnv($settings->videoWatermarkPath);
        if (file_exists($path)) {
            return $path;
        }

        $validator = new UrlValidator();
        $error = '';
        if ($validator->validate($path, $error)) {
            return $path;
        }

        Craft::warning("Video watermark could not be found: $path", __METHOD__);
        return null;
    }

    /**
     * Return a stable fingerprint for output-affecting watermark settings.
     */
    protected function getVideoWatermarkFingerprint(string $path): string
    {
        $settings = Transcoder::$plugin->getSettings();

        return substr(sha1(JsonHelper::encode([
            $path,
            App::parseEnv((string)$settings->videoWatermarkWidth),
            $settings->videoWatermarkPosition,
            App::parseEnv((string)$settings->videoWatermarkPadding),
            App::parseEnv((string)$settings->videoWatermarkOpacity),
        ])), 0, 10);
    }

    /**
     * Build the ffmpeg graph that composes scaling and watermarking.
     */
    protected function getVideoWatermarkFilter(array $videoOptions): string
    {
        $settings = Transcoder::$plugin->getSettings();
        $baseFilter = $this->getScalingFilter($videoOptions) ?? 'null';
        $watermarkFilters = ['format=rgba'];

        $width = max(0, (int)App::parseEnv((string)$settings->videoWatermarkWidth));
        if ($width > 0) {
            $watermarkFilters[] = "scale=$width:-1";
        }

        $opacityPercentage = max(0, min(100, (int)App::parseEnv((string)$settings->videoWatermarkOpacity)));
        if ($opacityPercentage < 100) {
            $opacity = $opacityPercentage / 100;
            $watermarkFilters[] = 'colorchannelmixer=aa=' . rtrim(rtrim(number_format($opacity, 2, '.', ''), '0'), '.');
        }

        [$x, $y] = $this->getVideoWatermarkPosition();

        return '[0:v]' . $baseFilter . '[base];'
            . '[1:v]' . implode(',', $watermarkFilters) . '[watermark];'
            . "[base][watermark]overlay=$x:$y:shortest=1[transcoded]";
    }

    /**
     * Return ffmpeg overlay coordinates for the configured watermark position.
     */
    protected function getVideoWatermarkPosition(): array
    {
        $settings = Transcoder::$plugin->getSettings();
        $padding = max(0, (int)App::parseEnv((string)$settings->videoWatermarkPadding));

        return match ($settings->videoWatermarkPosition) {
            'top-left' => [(string)$padding, (string)$padding],
            'top-right' => ["W-w-$padding", (string)$padding],
            'bottom-left' => [(string)$padding, "H-h-$padding"],
            default => ["W-w-$padding", "H-h-$padding"],
        };
    }

    /**
     * Normalize configured poster formats by handle.
     */
    protected function getVideoPosterFormats(): array
    {
        $formats = [];
        foreach (Transcoder::$plugin->getSettings()->videoPosterFormats as $handle => $format) {
            if (!is_array($format)) {
                continue;
            }

            $handle = is_string($handle) ? $handle : ($format['handle'] ?? '');
            $handle = trim((string)$handle);
            if ($handle === '') {
                continue;
            }

            $options = [];
            foreach (['width', 'height', 'timeInSecs'] as $key) {
                if (isset($format[$key]) && $format[$key] !== '') {
                    $options[$key] = (int)$format[$key];
                }
            }
            $formats[$handle] = $options;
        }

        return $formats;
    }

    /**
     * Return the exact thumbnail options used by a configured poster format.
     */
    protected function getVideoPosterOptions(Asset|string $filePath, string $formatHandle): ?array
    {
        $formats = $this->getVideoPosterFormats();
        if (!isset($formats[$formatHandle])) {
            return null;
        }

        $options = $formats[$formatHandle];
        $options['posterFormat'] = $formatHandle;
        if (!empty($options['timeInSecs'])) {
            $fileInfo = $this->getFileInfo($filePath, true) ?? [];
            $duration = (float)($fileInfo['duration'] ?? 0);
            if ($duration > 0) {
                $options['posterSeekTimeInSecs'] = min(
                    (float)$options['timeInSecs'],
                    max(0, $duration - 0.1)
                );
            }
        }
        if (Transcoder::$plugin->getSettings()->preventVideoPosterBlackBars) {
            $options['preventBlackBars'] = true;
        }

        return $options;
    }

    /**
     * Return option keys that affect poster generation but not its canonical filename.
     */
    protected function getThumbnailFilenameExcludeParams(): array
    {
        return array_values(array_unique(array_merge(self::EXCLUDE_PARAMS, [
            'posterFormat',
            'posterSeekTimeInSecs',
            'preventBlackBars',
        ])));
    }

    /**
     * Build a poster filter that fills unused space with a blurred cover frame.
     */
    protected function getPosterBlackBarFilter(array $options): string
    {
        $width = (int)$options['width'];
        $height = (int)$options['height'];
        $blurRadius = max(1, min(20, intdiv(max(8, min($width, $height)), 4) - 1));

        return '[0:v]split=2[background][foreground];'
            . "[background]scale=$width:$height:force_original_aspect_ratio=increase,"
            . "crop=$width:$height,boxblur=$blurRadius:1[background];"
            . "[foreground]scale=$width:$height:force_original_aspect_ratio=decrease[foreground];"
            . '[background][foreground]overlay=(W-w)/2:(H-h)/2[poster]';
    }

    /**
     * Extract a file system path if $filePath is an Asset object
     *
     * @param Asset|string $filePath
     *
     * @return string
     * @throws InvalidConfigException
     */
    protected function getAssetPath(Asset|string $filePath): string
    {
        // If we're passed an Asset, extract the path from it
        if (($filePath instanceof Asset)) {
            $asset = $filePath;
            $assetVolume = null;
            try {
                $assetVolume = $asset->getVolume();
            } catch (InvalidConfigException $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }

            if ($assetVolume) {
                // If it's local, get a path to the file
                $fs = $assetVolume->getFs();
                if ($fs instanceof Local) {
                    $subPath = $assetVolume->getSubPath();
                    if (!empty($subPath)) {
                        $subPath = rtrim($subPath, DIRECTORY_SEPARATOR);
                        $subPath .= '' === $subPath ? '' : DIRECTORY_SEPARATOR;
                    }
                    $sourcePath = rtrim($fs->path, DIRECTORY_SEPARATOR);
                    $sourcePath .= '' === $sourcePath ? '' : DIRECTORY_SEPARATOR;
                    $folderPath = '';
                    try {
                        $folderPath = rtrim($asset->getFolder()->path, DIRECTORY_SEPARATOR);
                    } catch (InvalidConfigException $e) {
                        Craft::error($e->getMessage(), __METHOD__);
                    }
                    $folderPath .= '' === $folderPath ? '' : DIRECTORY_SEPARATOR;

                    $filePath = $sourcePath . $subPath . $folderPath . $asset->filename;
                } else {
                    // Otherwise, get a URL
                    $filePath = $asset->getUrl() ?? '';
                }
            }
        }

        $filePath = (string)App::parseEnv($filePath);

        // Make sure that $filePath is either an existing file, or a valid URL
        if (!file_exists($filePath)) {
            $validator = new UrlValidator();
            $error = '';
            if (!$validator->validate($filePath, $error)) {
                Craft::error($error, __METHOD__);
                $filePath = '';
            }
        }

        return $filePath;
    }

    /**
     * Set the width & height if desired
     *
     * @param array $options
     * @param string $ffmpegCmd
     *
     * @return string
     */
    protected function addScalingFfmpegArgs(array $options, string $ffmpegCmd): string
    {
        $filter = $this->getScalingFilter($options);
        if ($filter !== null) {
            $ffmpegCmd .= ' -vf ' . escapeshellarg($filter);
        }

        return $ffmpegCmd;
    }

    /**
     * Return the original scaling filter without command-line arguments.
     */
    protected function getScalingFilter(array $options): ?string
    {
        if (!empty($options['width']) && !empty($options['height'])) {
            // Handle "none", "crop", and "letterbox" aspectRatios
            $aspectRatio = '';
            if (!empty($options['aspectRatio'])) {
                switch ($options['aspectRatio']) {
                    // Scale to the appropriate aspect ratio, padding
                    case 'letterbox':
                        $letterboxColor = '';
                        if (!empty($options['letterboxColor'])) {
                            $letterboxColor = ':color=' . $options['letterboxColor'];
                        }
                        $aspectRatio = ':force_original_aspect_ratio=decrease'
                            . ',pad=' . $options['width'] . ':' . $options['height'] . ':(ow-iw)/2:(oh-ih)/2'
                            . $letterboxColor;
                        break;
                    // Scale to the appropriate aspect ratio, cropping
                    case 'crop':
                        $aspectRatio = ':force_original_aspect_ratio=increase'
                            . ',crop=' . $options['width'] . ':' . $options['height'];
                        break;
                    // No aspect ratio scaling at all
                    default:
                        $aspectRatio = ':force_original_aspect_ratio=disable';
                        $options['aspectRatio'] = 'none';
                        break;
                }
            }
            $sharpen = '';
            if (!empty($options['sharpen']) && ($options['sharpen'] !== false)) {
                $sharpen = ',unsharp=5:5:1.0:5:5:0.0';
            }
            return 'scale=' . $options['width'] . ':' . $options['height']
                . $aspectRatio
                . $sharpen;
        }

        return null;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Combine the options arrays
     *
     * @param string $defaultName
     * @param array $options
     *
     * @return array
     */
    protected function coalesceOptions(string $defaultName, array $options): array
    {
        // Default options
        $settings = Transcoder::$plugin->getSettings();
        $defaultOptions = $settings[$defaultName];

        // Coalesce the passed in $options with the $defaultOptions
        return array_merge($defaultOptions, $options);
    }

    /**
     * Execute a shell command
     *
     * @param string $command
     *
     * @return string
     */
    protected function executeShellCommand(string $command): string
    {
        return $this->executeShellCommandWithStatus($command)['output'];
    }

    /**
     * Execute a shell command and retain whether it exited successfully.
     *
     * @return array{success: bool, output: string}
     */
    protected function executeShellCommandWithStatus(string $command): array
    {
        // Create the shell command
        $shellCommand = new ShellCommand();
        $shellCommand->setCommand($command);

        // If we don't have proc_open, maybe we've got exec
        if (!function_exists('proc_open') && function_exists('exec')) {
            $shellCommand->useExec = true;
        }

        // Return the result of the command's output or error
        $success = $shellCommand->execute();
        if ($success) {
            $result = $shellCommand->getOutput();
        } else {
            $result = $shellCommand->getError();
        }

        return [
            'success' => $success,
            'output' => $result,
        ];
    }
}
