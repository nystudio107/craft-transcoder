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

use nystudio107\transcoder\Transcoder;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\volumes\Local;

use yii\base\Exception;

use mikehaertl\shellcommand\Command as ShellCommand;

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class Transcode extends Component
{
    // Protected Properties
    // =========================================================================

    // Suffixes to add to the generated filename params
    protected $suffixMap = [
        'videoFrameRate' => 'fps',
        'videoBitRate'   => 'bps',
        'audioBitRate'   => 'bps',
        'audioChannels'  => 'c',
        'height'         => 'h',
        'width'          => 'w',
        'timeInSecs'     => 's',
    ];

    // Params that should be excluded from being part of the generated filename
    protected $excludeParams = [
        'videoEncoder',
        'audioEncoder',
        'fileSuffix',
        'sharpen',
    ];

    // Mappings for getFileInfo() summary values
    protected $infoSummary = [
        'format' => [
            'filename' => 'filename',
            'duration' => 'duration',
            'size'     => 'size',
        ],
        'audio'  => [
            'codec_name'  => 'audioEncoder',
            'bit_rate'    => 'audioBitRate',
            'sample_rate' => 'audioSampleRate',
            'channels'    => 'audioChannels',
        ],
        'video'  => [
            'codec_name'     => 'videoEncoder',
            'bit_rate'       => 'videoBitRate',
            'avg_frame_rate' => 'videoFrameRate',
            'height'         => 'height',
            'width'          => 'width',
        ],
    ];

    // Public Methods
    // =========================================================================

    /**
     * Returns a URL to the transcoded video or "" if it doesn't exist (at which
     * time it will create it).
     *
     * @param $filePath     string  path to the original video -OR- an Asset
     * @param $videoOptions array   of options for the video
     *
     * @return string       URL of the transcoded video or ""
     * @throws Exception
     */
    public function getVideoUrl($filePath, $videoOptions): string
    {

        $result = "";
        $settings = Transcoder::$plugin->getSettings();
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            $destVideoPath = Craft::getAlias($settings['transcoderPath']);

            $videoOptions = $this->coalesceOptions("defaultVideoOptions", $videoOptions);

            // Get the video encoder presets to use
            $videoEncoders = $settings['videoEncoders'];
            $thisEncoder = $videoEncoders[$videoOptions['videoEncoder']];

            $videoOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -i ' . escapeshellarg($filePath)
                . ' -vcodec ' . $thisEncoder['videoCodec']
                . ' ' . $thisEncoder['videoCodecOptions']
                . ' -bufsize 1000k'
                . ' -threads 0';

            // Set the framerate if desired
            if (!empty($videoOptions['videoFrameRate'])) {
                $ffmpegCmd .= ' -r ' . $videoOptions['videoFrameRate'];
            }

            // Set the bitrate if desired
            if (!empty($videoOptions['videoBitRate'])) {
                $ffmpegCmd .= ' -b:v ' . $videoOptions['videoBitRate'] . ' -maxrate ' . $videoOptions['videoBitRate'];
            }

            // Adjust the scaling if desired
            $ffmpegCmd = $this->addScalingFfmpegArgs(
                $videoOptions,
                $ffmpegCmd
            );

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
            if (!file_exists($destVideoPath)) {
                mkdir($destVideoPath);
            }

            $destVideoFile = $this->getFilename($filePath, $videoOptions);

            // File to store the video encoding progress in
            $progressFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $destVideoFile . ".progress";

            // Assemble the destination path and final ffmpeg command
            $destVideoPath = $destVideoPath . $destVideoFile;
            $ffmpegCmd .= ' -f '
                . $thisEncoder['fileFormat']
                . ' -y ' . escapeshellarg($destVideoPath)
                . ' 1> ' . $progressFile . ' 2>&1 & echo $!';

            // Make sure there isn't a lockfile for this video already
            $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $destVideoFile . ".lock";
            $oldPid = @file_get_contents($lockFile);
            if ($oldPid !== false) {
                exec("ps $oldPid", $ProcessState);
                if (count($ProcessState) >= 2) {
                    return $result;
                }
                // It's finished transcoding, so delete the lockfile and progress file
                @unlink($lockFile);
                @unlink($progressFile);
            }

            // If the video file already exists and hasn't been modified, return it.  Otherwise, start it transcoding
            if (file_exists($destVideoPath) && (filemtime($destVideoPath) >= filemtime($filePath))) {
                $result = Craft::getAlias($settings['transcoderUrl']) . $destVideoFile;
            } else {
                // Kick off the transcoding
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
     * @param $filePath         string  path to the original video or an Asset
     * @param $thumbnailOptions array   of options for the thumbnail
     *
     * @return string           URL of the video thumbnail
     * @throws Exception
     */
    public function getVideoThumbnailUrl($filePath, $thumbnailOptions): string
    {

        $result = "";
        $settings = Transcoder::$plugin->getSettings();
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            $destThumbnailPath = Craft::getAlias($settings['transcoderPath']);

            $thumbnailOptions = $this->coalesceOptions("defaultThumbnailOptions", $thumbnailOptions);

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -i ' . escapeshellarg($filePath)
                . ' -vcodec mjpeg'
                . ' -vframes 1';

            // Adjust the scaling if desired
            $ffmpegCmd = $this->addScalingFfmpegArgs(
                $thumbnailOptions,
                $ffmpegCmd
            );

            // Set the timecode to get the thumbnail from if desired
            if (!empty($thumbnailOptions['timeInSecs'])) {
                $timeCode = gmdate("H:i:s", $thumbnailOptions['timeInSecs']);
                $ffmpegCmd .= ' -ss ' . $timeCode . '.00';
            }

            // Create the directory if it isn't there already
            if (!file_exists($destThumbnailPath)) {
                mkdir($destThumbnailPath);
            }

            $destThumbnailFile = $this->getFilename($filePath, $thumbnailOptions);

            // Assemble the destination path and final ffmpeg command
            $destThumbnailPath = $destThumbnailPath . $destThumbnailFile;
            $ffmpegCmd .= ' -f image2 -y ' . escapeshellarg($destThumbnailPath) . ' >/dev/null 2>/dev/null';

            // If the thumbnail file already exists, return it.  Otherwise, generate it and return it
            if (!file_exists($destThumbnailPath)) {
                $shellOutput = $this->executeShellCommand($ffmpegCmd);
                Craft::info($ffmpegCmd, __METHOD__);
            }
            $result = Craft::getAlias($settings['transcoderUrl']) . $destThumbnailFile;
        }

        return $result;
    }

    /**
     * Returns a URL to the transcoded audio file or "" if it doesn't exist
     * (at which time it will create it).
     *
     * @param $filePath     string path to the original audio file -OR- an Asset
     * @param $audioOptions array of options for the audio file
     *
     * @return string       URL of the transcoded audio file or ""
     * @throws Exception
     */
    public function getAudioUrl($filePath, $audioOptions): string
    {

        $result = "";
        $settings = Transcoder::$plugin->getSettings();
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            $destAudioPath = Craft::getAlias($settings['transcoderPath']);

            $audioOptions = $this->coalesceOptions("defaultAudioOptions", $audioOptions);

            // Get the audio encoder presets to use
            $audioEncoders = $settings['audioEncoders'];
            $thisEncoder = $audioEncoders[$audioOptions['audioEncoder']];

            $audioOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

            // Build the basic command for ffmpeg
            $ffmpegCmd = $settings['ffmpegPath']
                . ' -i ' . escapeshellarg($filePath)
                . ' -acodec ' . $thisEncoder['audioCodec']
                . ' ' . $thisEncoder['audioCodecOptions']
                . ' -bufsize 1000k'
                . ' -threads 0';

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


            // Create the directory if it isn't there already
            if (!file_exists($destAudioPath)) {
                mkdir($destAudioPath);
            }

            $destAudioFile = $this->getFilename($filePath, $audioOptions);

            // File to store the audio encoding progress in
            $progressFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $destAudioFile . ".progress";

            // Assemble the destination path and final ffmpeg command
            $destAudioPath = $destAudioPath . $destAudioFile;
            $ffmpegCmd .= ' -f '
                . $thisEncoder['fileFormat']
                . ' -y ' . escapeshellarg($destAudioPath)
                . ' 1> ' . $progressFile . ' 2>&1 & echo $!';

            // Make sure there isn't a lockfile for this audio file already
            $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $destAudioFile . ".lock";
            $oldPid = @file_get_contents($lockFile);
            if ($oldPid !== false) {
                exec("ps $oldPid", $ProcessState);
                if (count($ProcessState) >= 2) {
                    return $result;
                }
                // It's finished transcoding, so delete the lockfile and progress file
                @unlink($lockFile);
                @unlink($progressFile);
            }

            // If the audio file already exists and hasn't been modified, return it.  Otherwise, start it transcoding
            if (file_exists($destAudioPath) && (filemtime($destAudioPath) >= filemtime($filePath))) {
                $result = Craft::getAlias($settings['transcoderUrl']) . $destAudioFile;
            } else {
                // Kick off the transcoding
                $pid = $this->executeShellCommand($ffmpegCmd);
                Craft::info($ffmpegCmd . "\nffmpeg PID: " . $pid, __METHOD__);

                // Create a lockfile in tmp
                file_put_contents($lockFile, $pid);
            }
        }

        return $result;
    }

    /**
     * Extract information from a video/audio file
     *
     * @param      $filePath
     * @param bool $summary
     *
     * @return array
     * @throws Exception
     */
    public function getFileInfo($filePath, $summary = false): array
    {

        $result = null;
        $settings = Transcoder::$plugin->getSettings();
        $filePath = $this->getAssetPath($filePath);

        if (file_exists($filePath)) {
            // Build the basic command for ffprobe
            $ffprobeOptions = $settings['ffprobeOptions'];
            $ffprobeCmd = $settings['ffprobePath']
                . ' ' . $ffprobeOptions
                . ' ' . escapeshellarg($filePath);

            $shellOutput = $this->executeShellCommand($ffprobeCmd);
            Craft::info($ffprobeCmd, __METHOD__);
            $result = json_decode($shellOutput, true);
            Craft::info(print_r($result, true), __METHOD__);

            // Trim down the arrays to just a summary
            if ($summary && !empty($result)) {
                $summaryResult = [];
                foreach ($result as $topLevelKey => $topLevelValue) {
                    switch ($topLevelKey) {
                        // Format info
                        case "format":
                            foreach ($this->infoSummary['format'] as $settingKey => $settingValue) {
                                if (!empty($topLevelValue[$settingKey])) {
                                    $summaryResult[$settingValue] = $topLevelValue[$settingKey];
                                }
                            }
                            break;
                        // Stream info
                        case "streams":
                            foreach ($topLevelValue as $stream) {
                                $infoSummaryType = $stream['codec_type'];
                                foreach ($this->infoSummary[$infoSummaryType] as $settingKey => $settingValue) {
                                    if (!empty($stream[$settingKey])) {
                                        $summaryResult[$settingValue] = $stream[$settingKey];
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
                    && (strpos($summaryResult['videoFrameRate'], '/') !== false)
                ) {
                    $parts = explode('/', $summaryResult['videoFrameRate']);
                    $summaryResult['videoFrameRate'] = floatval($parts[0]) / floatval($parts[1]);
                }
                $result = $summaryResult;
            }
        }

        return $result;
    }

    /**
     * Get the name of a video file from a path and options
     *
     * @param $filePath
     * @param $videoOptions
     *
     * @return string
     * @throws Exception
     */
    public function getVideoFilename($filePath, $videoOptions): string
    {
        $settings = Transcoder::$plugin->getSettings();
        $videoOptions = $this->coalesceOptions("defaultVideoOptions", $videoOptions);

        // Get the video encoder presets to use
        $videoEncoders = $settings['videoEncoders'];
        $thisEncoder = $videoEncoders[$videoOptions['videoEncoder']];

        $videoOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

        $result = $this->getFilename($filePath, $videoOptions);

        return $result;
    }

    /**
     * Get the name of an audio file from a path and options
     *
     * @param $filePath
     * @param $audioOptions
     *
     * @return string
     * @throws Exception
     */
    public function getAudioFilename($filePath, $audioOptions): string
    {
        $settings = Transcoder::$plugin->getSettings();
        $audioOptions = $this->coalesceOptions("defaultAudioOptions", $audioOptions);

        // Get the video encoder presets to use
        $audioEncoders = $settings['audioEncoders'];
        $thisEncoder = $audioEncoders[$audioOptions['audioEncoder']];

        $audioOptions['fileSuffix'] = $thisEncoder['fileSuffix'];

        $result = $this->getFilename($filePath, $audioOptions);

        return $result;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Get the name of a file from a path and options
     *
     * @param $filePath
     * @param $options
     *
     * @return string
     * @throws Exception
     */
    protected function getFilename($filePath, $options)
    {
        $settings = Transcoder::$plugin->getSettings();
        $filePath = $this->getAssetPath($filePath);
        $pathParts = pathinfo($filePath);
        $fileName = $pathParts['filename'];

        // Add our options to the file name
        foreach ($options as $key => $value) {
            if (!empty($value)) {
                $suffix = "";
                if (!empty($this->suffixMap[$key])) {
                    $suffix = $this->suffixMap[$key];
                }
                if (is_bool($value)) {
                    $value = $value ? $key : 'no' . $key;
                }
                if (!in_array($key, $this->excludeParams)) {
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

            $filePath = $sourcePath . $folderPath . $asset->filename;
        }

        return Craft::getAlias($filePath);
    }

    /**
     * Set the width & height if desired
     *
     * @param $options
     * @param $ffmpegCmd
     *
     * @return string
     */
    protected function addScalingFfmpegArgs($options, $ffmpegCmd): string
    {
        if (!empty($options['width']) && !empty($options['height'])) {
            // Handle "none", "crop", and "letterbox" aspectRatios
            $aspectRatio = "";
            if (!empty($options['aspectRatio'])) {
                switch ($options['aspectRatio']) {
                    // Scale to the appropriate aspect ratio, padding
                    case "letterbox":
                        $letterboxColor = "";
                        if (!empty($options['letterboxColor'])) {
                            $letterboxColor = ":color=" . $options['letterboxColor'];
                        }
                        $aspectRatio = ':force_original_aspect_ratio=decrease'
                            . ',pad=' . $options['width'] . ':' . $options['height'] . ':(ow-iw)/2:(oh-ih)/2'
                            . $letterboxColor;
                        break;
                    // Scale to the appropriate aspect ratio, cropping
                    case "crop":
                        $aspectRatio = ':force_original_aspect_ratio=increase'
                            . ',crop=' . $options['width'] . ':' . $options['height'];
                        break;
                    // No aspect ratio scaling at all
                    default:
                        $aspectRatio = ':force_original_aspect_ratio=disable';
                        $options['aspectRatio'] = "none";
                        break;
                }
            }
            $sharpen = "";
            if (!empty($options['sharpen']) && ($options['sharpen'] !== false)) {
                $sharpen = ',unsharp=5:5:1.0:5:5:0.0';
            }
            $ffmpegCmd .= ' -vf "scale='
                . $options['width'] . ':' . $options['height']
                . $aspectRatio
                . $sharpen
                . '"';
        }

        return $ffmpegCmd;
    }

    /**
     * Combine the options arrays
     *
     * @param $defaultName
     * @param $options
     *
     * @return array
     */
    protected function coalesceOptions($defaultName, $options): array
    {
        // Default options
        $settings = Transcoder::$plugin->getSettings();
        $defaultOptions = $settings[$defaultName];

        // Coalesce the passed in $options with the $defaultOptions
        $options = array_merge($defaultOptions, $options);

        return $options;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Execute a shell command
     *
     * @param string $command
     *
     * @return string
     */
    protected function executeShellCommand(string $command): string
    {
        // Create the shell command
        $shellCommand = new ShellCommand();
        $shellCommand->setCommand($command);

        // If we don't have proc_open, maybe we've got exec
        if (!function_exists('proc_open') && function_exists('exec')) {
            $shellCommand->useExec = true;
        }

        // Return the result of the command's output or error
        if ($shellCommand->execute()) {
            $result = $shellCommand->getOutput();
        } else {
            $result = $shellCommand->getError();
        }

        return $result;
    }
}
