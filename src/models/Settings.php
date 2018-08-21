<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\models;

use craft\base\Model;

/**
 * Transcoder Settings model
 *
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class Settings extends Model
{

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        // Unset any deprecated properties
        if (!empty($config)) {
            unset($config['transcoderPath'], $config['transcoderUrl']);
        }
        parent::__construct($config);
    }

    // Public Properties
    // =========================================================================

    /**
     * The path to the ffmpeg binary
     *
     * @var string
     */
    public $ffmpegPath = '/usr/bin/ffmpeg';

    /**
     * The path to the ffprobe binary
     *
     * @var string
     */
    public $ffprobePath = '/usr/bin/ffprobe';

    /**
     * The options to use for ffprobe
     *
     * @var string
     */
    public $ffprobeOptions = '-v quiet -print_format json -show_format -show_streams';

    /**
     * The path where the transcoded videos are stored; must have a trailing /
     * Yii2 aliases are supported here
     *
     * @var string
     */
    public $transcoderPaths = [
        'default' => '@webroot/transcoder/',
        'video' => '@webroot/transcoder/video/',
        'audio' => '@webroot/transcoder/audio/',
        'thumbnail' => '@webroot/transcoder/thumbnail/',
        'gif' => '@webroot/transcoder/gif/',
    ];

    /**
     * The URL where the transcoded videos are stored; must have a trailing /
     * Yii2 aliases are supported here
     *
     * @var string
     */
    public $transcoderUrls = [
        'default' => '@web/transcoder/',
        'video' => '@web/transcoder/video/',
        'audio' => '@web/transcoder/audio/',
        'thumbnail' => '@web/transcoder/thumbnail/',
        'gif' => '@web/transcoder/gif/',
    ];

    /**
     * Use a md5 hash for the filenames instead of parameterized naming
     *
     * @var bool
     */
    public $useHashedNames = false;

    /**
     * Preset video encoders
     *
     * @var array
     */
    public $videoEncoders = [
        'h264' => [
            'fileSuffix' => '.mp4',
            'fileFormat' => 'mp4',
            'videoCodec' => 'libx264',
            'videoCodecOptions' => '-vprofile high -preset slow -crf 22',
            'audioCodec' => 'libfdk_aac',
            'audioCodecOptions' => '-async 1000',
        ],
        'webm' => [
            'fileSuffix' => '.webm',
            'fileFormat' => 'webm',
            'videoCodec' => 'libvpx',
            'videoCodecOptions' => '-quality good -cpu-used 0',
            'audioCodec' => 'libvorbis',
            'audioCodecOptions' => '-async 1000',
        ],
        'gif' => [
            'fileSuffix' => '.mp4',
            'fileFormat' => 'mp4',
            'videoCodec' => 'libx264',
            'videoCodecOptions' => '-pix_fmt yuv420p -movflags +faststart -filter:v crop=\'floor(in_w/2)*2:floor(in_h/2)*2\' ',
        ],
    ];

    /**
     * Preset audio encoders
     *
     * @var array
     */
    public $audioEncoders = [
        'mp3' => [
            'fileSuffix' => '.mp3',
            'fileFormat' => 'mp3',
            'audioCodec' => 'libmp3lame',
            'audioCodecOptions' => '',
        ],
        'aac' => [
            'fileSuffix' => '.m4a',
            'fileFormat' => 'aac',
            'audioCodec' => 'libfdk_aac',
            'audioCodecOptions' => '',

        ],
        'ogg' => [
            'fileSuffix' => '.ogg',
            'fileFormat' => 'ogg',
            'audioCodec' => 'libvorbis',
            'audioCodecOptions' => '',
        ],
    ];

    /**
     * Default options for encoded videos
     *
     * @var array
     */
    public $defaultVideoOptions = [
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
    public $defaultThumbnailOptions = [
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
    public $defaultAudioOptions = [
        'audioEncoder' => 'mp3',
        'audioBitRate' => '128k',
        'audioSampleRate' => '44100',
        'audioChannels' => '2',
    ];

    /**
     * Default options for encoded GIF
     *
     * @var array
     */
    public $defaultGifOptions = [
        'videoEncoder' => 'gif',
        'fileSuffix' => '',
        'fileFormat' => '',
        'videoCodec' => '',
        'videoCodecOptions' => '',
    ];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $tokens = [
            '{DOCUMENT_ROOT}' => $_SERVER['DOCUMENT_ROOT'],
        ];
        $this->transcoderPath = str_replace(array_keys($tokens), array_values($tokens), $this->transcoderPath);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['ffmpegPath', 'string'],
            ['ffmpegPath', 'required'],
            ['ffprobePath', 'string'],
            ['ffprobePath', 'required'],
            ['ffprobeOptions', 'string'],
            ['ffprobeOptions', 'safe'],
            ['transcoderPath', 'string'],
            ['transcoderPath', 'required'],
            ['transcoderPaths', 'array'],
            ['transcoderPaths', 'required'],
            ['transcoderUrls', 'array'],
            ['transcoderUrls', 'required'],
            ['useHashedNames', 'boolean'],
            ['useHashedNames', 'default', 'value' => false],
            ['videoEncoders', 'required'],
            ['audioEncoders', 'required'],
            ['defaultVideoOptions', 'required'],
            ['defaultThumbnailOptions', 'required'],
            ['defaultAudioOptions', 'required'],
        ];
    }
}
