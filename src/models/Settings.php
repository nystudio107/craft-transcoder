<?php
/**
 * Transcoder plugin for Craft CMS
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\models;

use craft\base\Model;
use craft\helpers\App;
use craft\validators\ArrayValidator;
use yii\validators\NumberValidator;

/**
 * Transcoder Settings model
 *
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * The path to the ffmpeg binary
     *
     * @var string
     */

    public string $ffmpegPath = '/usr/bin/ffmpeg';

    /**
     * The path to the ffprobe binary
     *
     * @var string
     */
    public string $ffprobePath = '/usr/bin/ffprobe';

    /**
     * The options to use for ffprobe
     *
     * @var string
     */
    public string $ffprobeOptions = '-v quiet -print_format json -show_format -show_streams';

    /**
     * The path where the transcoded videos are stored; must have a trailing /
     * Yii2 aliases are supported here
     *
     * @var array
     */
    public array $transcoderPaths = [
        'default' => '@webroot/transcoder/',
        'video' => '@webroot/transcoder/',
        'audio' => '@webroot/transcoder/',
        'thumbnail' => '@webroot/transcoder/',
        'gif' => '@webroot/transcoder/',
    ];

    /**
     * The URL where the transcoded videos are stored; must have a trailing /
     * Yii2 aliases are supported here
     *
     * @var array
     */
    public array $transcoderUrls = [
        'default' => '@web/transcoder/',
        'video' => '@web/transcoder/',
        'audio' => '@web/transcoder/',
        'thumbnail' => '@web/transcoder/',
        'gif' => '@web/transcoder/',
    ];

    /**
     * @var bool Determines whether the download file endpoint should be enabled for anonymous frontend access
     */
    public bool $enableDownloadFileEndpoint = false;

    /**
     * Use a md5 hash for the filenames instead of parameterized naming
     *
     * @var bool
     */
    public bool $useHashedNames = false;

    /**
     * if a upload location has a subfolder defined, add this to the transcoder
     * paths too
     *
     * @var bool
     */
    public bool $createSubfolders = true;

    /** @var bool|int|string One-based URL/path segment used as the output subfolder for string inputs. */
    public bool|int|string $subfolderUrlSegment = false;

    /**
     * clear caches when somebody clears all caches from the CP?
     *
     * @var bool
     */
    public bool $clearCaches = false;

    /** @var bool Queue video encoding when a new video asset is uploaded. */
    public bool $queueVideosOnAssetUpload = false;

    /** @var int|string Seconds to wait before an uploaded video starts encoding. */
    public int|string $videoQueueDelaySeconds = 0;

    /** @var array Options passed to queued video encodes. */
    public array $queuedVideoOptions = [];

    /** @var bool Queue GIF encoding when a new GIF asset is uploaded. */
    public bool $queueGifsOnAssetUpload = false;

    /** @var int|string Seconds to wait before an uploaded GIF starts encoding. */
    public int|string $gifQueueDelaySeconds = 0;

    /** @var array Options passed to queued GIF encodes. */
    public array $queuedGifOptions = [];

    /** @var bool Queue audio encoding when a new audio Asset is uploaded. */
    public bool $queueAudioOnAssetUpload = false;

    /** @var int|string Seconds to wait before uploaded audio starts encoding. */
    public int|string $audioQueueDelaySeconds = 0;

    /** @var array Options passed to queued audio encodes. */
    public array $queuedAudioOptions = [];

    /** @var string How encoded video filenames are generated: options or source. */
    public string $videoFilenameStrategy = 'options';

    /** @var bool Overlay a watermark on encoded videos. */
    public bool $enableVideoWatermark = false;

    /** @var string Local path, alias, environment value, or URL for the watermark image. */
    public string $videoWatermarkPath = '';

    /** @var int|string Optional watermark width in pixels. */
    public int|string $videoWatermarkWidth = '';

    /** @var string Watermark position. */
    public string $videoWatermarkPosition = 'bottom-right';

    /** @var int|string Watermark distance from the selected edges in pixels. */
    public int|string $videoWatermarkPadding = 24;

    /** @var int|string Watermark opacity percentage. */
    public int|string $videoWatermarkOpacity = 100;

    /** @var bool Generate configured poster images after queued video encoding. */
    public bool $enableVideoPosters = false;

    /** @var bool Queue configured poster generation when a new video Asset is uploaded. */
    public bool $queueVideoPostersOnAssetUpload = false;

    /** @var bool Fill unused poster space with a blurred cover image. */
    public bool $preventVideoPosterBlackBars = false;

    /** @var array Poster images generated for each queued video. */
    public array $videoPosterFormats = [
        '16_9' => [
            'width' => 800,
            'height' => 450,
            'timeInSecs' => 3,
        ],
    ];

    /**
     * Preset video encoders
     *
     * @var array
     */
    public array $videoEncoders = [
        'h264' => [
            'fileSuffix' => '.mp4',
            'fileFormat' => 'mp4',
            'videoCodec' => 'libx264',
            'videoCodecOptions' => '-vprofile high -preset slow -crf 22',
            'audioCodec' => 'libfdk_aac',
            'audioCodecOptions' => '-async 1000',
            'threads' => '0',
        ],
        'webm' => [
            'fileSuffix' => '.webm',
            'fileFormat' => 'webm',
            'videoCodec' => 'libvpx',
            'videoCodecOptions' => '-quality good -cpu-used 0',
            'audioCodec' => 'libvorbis',
            'audioCodecOptions' => '-async 1000',
            'threads' => '0',
        ],
        'gif' => [
            'fileSuffix' => '.mp4',
            'fileFormat' => 'mp4',
            'videoCodec' => 'libx264',
            'videoCodecOptions' => '-pix_fmt yuv420p -movflags +faststart -filter:v crop=\'floor(in_w/2)*2:floor(in_h/2)*2\' ',
            'threads' => '0',
        ],
    ];

    /**
     * Preset audio encoders
     *
     * @var array
     */
    public array $audioEncoders = [
        'mp3' => [
            'fileSuffix' => '.mp3',
            'fileFormat' => 'mp3',
            'audioCodec' => 'libmp3lame',
            'audioCodecOptions' => '',
            'threads' => '0',
        ],
        'aac' => [
            'fileSuffix' => '.m4a',
            'fileFormat' => 'aac',
            'audioCodec' => 'libfdk_aac',
            'audioCodecOptions' => '',
            'threads' => '0',

        ],
        'ogg' => [
            'fileSuffix' => '.ogg',
            'fileFormat' => 'ogg',
            'audioCodec' => 'libvorbis',
            'audioCodecOptions' => '',
            'threads' => '0',
        ],
    ];

    /**
     * Default options for encoded videos
     *
     * @var array
     */
    public array $defaultVideoOptions = [
        // Video settings
        'videoEncoder' => 'h264',
        'videoBitRate' => '800k',
        'videoFrameRate' => 15,
        // Audio settings
        'audioBitRate' => '',
        'audioSampleRate' => '',
        'audioChannels' => '',
        // Spatial settings
        'width' => '',
        'height' => '',
        'sharpen' => true,
        // Can be 'none', 'crop', or 'letterbox'
        'aspectRatio' => 'letterbox',
        'letterboxColor' => '',
    ];

    /**
     * Default options for video thumbnails
     *
     * @var array
     */
    public array $defaultThumbnailOptions = [
        'fileSuffix' => '.jpg',
        'timeInSecs' => 10,
        'width' => '',
        'height' => '',
        'sharpen' => true,
        // Can be 'none', 'crop', or 'letterbox'
        'aspectRatio' => 'letterbox',
        'letterboxColor' => '',
    ];

    /**
     * Default options for encoded videos
     *
     * @var array
     */
    public array $defaultAudioOptions = [
        'audioEncoder' => 'mp3',
        'audioBitRate' => '128k',
        'audioSampleRate' => '44100',
        'audioChannels' => '2',
        'synchronous' => false,
        'stripMetadata' => false,
    ];

    /**
     * Default options for encoded GIF
     *
     * @var array
     */
    public array $defaultGifOptions = [
        'videoEncoder' => 'gif',
        'fileSuffix' => '',
        'fileFormat' => '',
        'videoCodec' => '',
        'videoCodecOptions' => '',
    ];

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        // Unset any deprecated properties
        if (!empty($config)) {
            // If the old properties are set, remap them to the default
            if (isset($config['transcoderPath'])) {
                $config['transcoderPaths']['default'] = $config['transcoderPath'];
                unset($config['transcoderPath']);
            }
            if (isset($config['transcoderUrl'])) {
                $config['transcoderUrls']['default'] = $config['transcoderUrl'];
                unset($config['transcoderUrl']);
            }
        }
        parent::__construct($config);
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['ffmpegPath', 'string'],
            ['ffmpegPath', 'required'],
            ['ffprobePath', 'string'],
            ['ffprobePath', 'required'],
            ['ffprobeOptions', 'string'],
            ['ffprobeOptions', 'safe'],
            ['transcoderPaths', ArrayValidator::class],
            ['transcoderPaths', 'required'],
            ['transcoderUrls', ArrayValidator::class],
            ['enableDownloadFileEndpoint', 'boolean'],
            ['useHashedNames', 'boolean'],
            ['createSubfolders', 'boolean'],
            ['subfolderUrlSegment', 'validateIntegerSetting', 'params' => ['min' => 1], 'skipOnEmpty' => true],
            ['clearCaches', 'boolean'],
            ['queueVideosOnAssetUpload', 'boolean'],
            ['videoQueueDelaySeconds', 'validateIntegerSetting', 'params' => ['min' => 0]],
            ['queuedVideoOptions', ArrayValidator::class],
            ['queueGifsOnAssetUpload', 'boolean'],
            ['gifQueueDelaySeconds', 'validateIntegerSetting', 'params' => ['min' => 0]],
            ['queuedGifOptions', ArrayValidator::class],
            ['queueAudioOnAssetUpload', 'boolean'],
            ['audioQueueDelaySeconds', 'validateIntegerSetting', 'params' => ['min' => 0]],
            ['queuedAudioOptions', ArrayValidator::class],
            ['videoFilenameStrategy', 'in', 'range' => ['options', 'source']],
            ['enableVideoWatermark', 'boolean'],
            ['videoWatermarkPath', 'string'],
            ['videoWatermarkWidth', 'validateIntegerSetting', 'params' => ['min' => 1], 'skipOnEmpty' => true],
            ['videoWatermarkPosition', 'in', 'range' => ['top-left', 'top-right', 'bottom-left', 'bottom-right']],
            ['videoWatermarkPadding', 'validateIntegerSetting', 'params' => ['min' => 0]],
            ['videoWatermarkOpacity', 'validateIntegerSetting', 'params' => ['min' => 0, 'max' => 100]],
            ['enableVideoPosters', 'boolean'],
            ['queueVideoPostersOnAssetUpload', 'boolean'],
            ['preventVideoPosterBlackBars', 'boolean'],
            ['videoPosterFormats', ArrayValidator::class],
            ['videoEncoders', 'required'],
            ['audioEncoders', 'required'],
            ['defaultVideoOptions', 'required'],
            ['defaultThumbnailOptions', 'required'],
            ['defaultAudioOptions', 'required'],
        ];
    }

    /**
     * Validate a numeric setting after resolving its environment variable.
     */
    public function validateIntegerSetting(string $attribute, array $params): void
    {
        if ($this->$attribute === false) {
            return;
        }

        $validator = new NumberValidator([
            'integerOnly' => true,
            'min' => $params['min'] ?? null,
            'max' => $params['max'] ?? null,
        ]);
        $error = null;
        $value = App::parseEnv((string)$this->$attribute);

        if (!$validator->validate($value, $error)) {
            $this->addError($attribute, $error);
        }
    }

    /**
     * Return poster formats as editable-table rows.
     */
    public function getVideoPosterFormatRows(): array
    {
        $rows = [];
        foreach ($this->videoPosterFormats as $handle => $format) {
            if (!is_array($format)) {
                continue;
            }

            $rows[] = [
                'handle' => is_string($handle) ? $handle : ($format['handle'] ?? ''),
                'width' => $format['width'] ?? '',
                'height' => $format['height'] ?? '',
                'timeInSecs' => $format['timeInSecs'] ?? '',
            ];
        }

        return $rows;
    }
}
