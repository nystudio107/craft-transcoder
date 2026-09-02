<?php

declare(strict_types=1);

namespace {
    final class Craft
    {
        public static object $app;

        /** @var string[] */
        public static array $logs = [];

        /** @var array<string, string> */
        public static array $aliases = [];

        public static function getAlias(string $value, bool $throwException = true): string|false
        {
            foreach (self::$aliases as $alias => $path) {
                if ($value === $alias || str_starts_with($value, $alias . '/')) {
                    return $path . substr($value, strlen($alias));
                }
            }

            return $throwException ? $value : false;
        }

        public static function info(string $message, string $category = ''): void
        {
            self::$logs[] = $message;
        }

        public static function warning(string $message, string $category = ''): void
        {
            self::$logs[] = $message;
        }

        public static function error(string $message, string $category = ''): void
        {
            self::$logs[] = $message;
        }

        public static function t(string $category, string $message, array $params = []): string
        {
            return strtr($message, array_combine(
                array_map(static fn(string $key): string => "{{$key}}", array_keys($params)),
                array_map('strval', $params)
            ) ?: []);
        }
    }
}

namespace craft\base {
    class Component
    {
    }
}

namespace craft\elements {
    class Asset
    {
        public const KIND_AUDIO = 'audio';
        public const KIND_VIDEO = 'video';

        /** @var array<int, self> */
        public static array $assets = [];

        public ?int $id = null;
        public string $filename = '';
        public ?string $folderPath = '';
        public string $path = '';
        public string $sourcePath = '';
        public ?object $folder = null;
        public ?object $volume = null;
        public ?int $volumeId = 1;

        public static function find(): AssetQuery
        {
            return new AssetQuery();
        }

        public function getFolder(): object
        {
            return $this->folder ?? (object)['path' => $this->folderPath ?? ''];
        }

        public function getPath(): string
        {
            if ($this->path !== '') {
                return $this->path;
            }

            return ($this->folderPath ?? '') . $this->filename;
        }

        public function getVolume(): ?object
        {
            return $this->volume;
        }

        public function getVolumeId(): ?int
        {
            return $this->volumeId;
        }

        public function getUrl(): ?string
        {
            return null;
        }
    }

    class AssetQuery
    {
        private ?int $assetId = null;

        public function id(?int $assetId): self
        {
            $this->assetId = $assetId;
            return $this;
        }

        public function one(): ?Asset
        {
            return $this->assetId === null ? null : (Asset::$assets[$this->assetId] ?? null);
        }
    }
}

namespace craft\events {
    use craft\elements\Asset;

    class DefineAssetThumbUrlEvent
    {
        public function __construct(
            public Asset $asset,
            public int $width,
            public int $height,
        ) {
        }
    }
}

namespace craft\fs {
    class Local
    {
        public function __construct(public string $path = '')
        {
        }
    }
}

namespace craft\helpers {
    final class App
    {
        public static function parseEnv(?string $value): bool|string|null
        {
            if ($value !== null && str_starts_with($value, '$')) {
                $environmentValue = getenv(substr($value, 1));
                return $environmentValue === false ? $value : $environmentValue;
            }

            if ($value !== null && str_starts_with($value, '@')) {
                return \Craft::getAlias($value, false) ?: $value;
            }

            return $value;
        }
    }

    final class Assets
    {
        public static function getFileKindByExtension(string $filename): string
        {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($extension, ['mp4', 'mov', 'webm'], true)) {
                return \craft\elements\Asset::KIND_VIDEO;
            }
            if (in_array($extension, ['aac', 'flac', 'm4a', 'mp3', 'ogg', 'wav'], true)) {
                return \craft\elements\Asset::KIND_AUDIO;
            }

            return 'unknown';
        }
    }

    final class FileHelper
    {
        public static function normalizePath(string $path): string
        {
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        public static function createDirectory(string $path): bool
        {
            return is_dir($path) || mkdir($path, 0777, true);
        }
    }

    final class Json
    {
        public static function encode(mixed $value): string
        {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        public static function decodeIfJson(string $value, bool $asArray = false): mixed
        {
            return json_decode($value, $asArray);
        }
    }

    final class UrlHelper
    {
    }
}

namespace craft\queue {
    class BaseJob
    {
        public function __construct(array $config = [])
        {
            foreach ($config as $name => $value) {
                $this->{$name} = $value;
            }
        }

        protected function setProgress(mixed $queue, float $progress, string $label = ''): void
        {
        }
    }
}

namespace yii\base {
    class Exception extends \Exception
    {
    }

    class InvalidConfigException extends Exception
    {
    }
}

namespace yii\validators {
    class UrlValidator
    {
        public function validate(mixed $value, mixed &$error = null): bool
        {
            return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
        }
    }
}

namespace mikehaertl\shellcommand {
    class Command
    {
        public bool $useExec = false;
        private string $command = '';
        private string $output = '';
        private string $error = '';

        public function setCommand(string $command): void
        {
            $this->command = $command;
        }

        public function execute(): bool
        {
            $output = [];
            $exitCode = 0;
            exec($this->command . ' 2>&1', $output, $exitCode);
            $this->output = implode("\n", $output);
            $this->error = $exitCode === 0 ? '' : $this->output;
            return $exitCode === 0;
        }

        public function getOutput(): string
        {
            return $this->output;
        }

        public function getError(): string
        {
            return $this->error;
        }
    }
}

namespace nystudio107\transcoder {
    final class Transcoder
    {
        public static object $plugin;
    }
}

namespace nystudio107\pluginvite\variables {
    interface ViteVariableInterface
    {
    }

    trait ViteVariableTrait
    {
    }
}

namespace {
    use craft\elements\Asset;
    use craft\events\DefineAssetThumbUrlEvent;
    use craft\fs\Local;
    use nystudio107\transcoder\Transcoder;
    use nystudio107\transcoder\jobs\EncodeAudio;
    use nystudio107\transcoder\jobs\EncodeGif;
    use nystudio107\transcoder\jobs\EncodeVideo;
    use nystudio107\transcoder\jobs\GenerateVideoPosters;
    use nystudio107\transcoder\jobs\RefreshVideoAsset;
    use nystudio107\transcoder\services\Transcode as BaseTranscode;
    use nystudio107\transcoder\variables\TranscoderVariable;

    require dirname(__DIR__) . '/src/jobs/EncodeAudio.php';
    require dirname(__DIR__) . '/src/jobs/EncodeGif.php';
    require dirname(__DIR__) . '/src/jobs/EncodeVideo.php';
    require dirname(__DIR__) . '/src/jobs/GenerateVideoPosters.php';
    require dirname(__DIR__) . '/src/jobs/RefreshVideoAsset.php';
    require dirname(__DIR__) . '/src/services/Transcode.php';
    require dirname(__DIR__) . '/src/variables/TranscoderVariable.php';

    final class HarnessSettings extends ArrayObject
    {
        public function __get(string $name): mixed
        {
            return $this[$name] ?? null;
        }
    }

    final class HarnessQueue
    {
        /** @var object[] */
        public array $jobs = [];
        public int $delaySeconds = 0;
        public bool $failPush = false;

        public function delay(int $seconds): self
        {
            $this->delaySeconds = $seconds;
            return $this;
        }

        public function push(object $job): ?string
        {
            if ($this->failPush) {
                return null;
            }

            $this->jobs[] = $job;
            return 'job-' . count($this->jobs);
        }
    }

    final class HarnessApp
    {
        public function __construct(private readonly HarnessQueue $queue)
        {
        }

        public function getQueue(): HarnessQueue
        {
            return $this->queue;
        }
    }

    final class HarnessPlugin
    {
        public BaseTranscode $transcode;

        public function __construct(private readonly HarnessSettings $settings)
        {
        }

        public function getSettings(): HarnessSettings
        {
            return $this->settings;
        }
    }

    final class HarnessTranscode extends BaseTranscode
    {
        protected function getAssetPath(Asset|string $filePath): string
        {
            return $filePath instanceof Asset ? $filePath->sourcePath : $filePath;
        }

        public function outputInfo(Asset|string $asset, array $options): array
        {
            return $this->getVideoOutputInfo($asset, $options) ?? [];
        }

        public function refreshTargets(Asset $asset): array
        {
            return $this->getVideoAssetRefreshTargets($asset);
        }

        public function versionedUrl(string $url, string $path): string
        {
            return $this->getVersionedMediaUrl($url, $path);
        }

        public function mediaLockFile(string $mediaType, string $outputPath): string
        {
            return $this->getMediaLockFile($mediaType, $outputPath);
        }
    }

    final class AssetPathTranscode extends BaseTranscode
    {
        public function resolveAssetPath(Asset $asset): string
        {
            return $this->getAssetPath($asset);
        }
    }

    final class FailingVideoTranscode extends BaseTranscode
    {
        public int $encodeAttempts = 0;

        public function runVideoAssetWork(Asset $asset, callable $callback): bool
        {
            $callback();
            return true;
        }

        public function getVideoUrl(
            string|Asset $filePath,
            array $videoOptions,
            bool $generate = true,
            bool $synchronous = false
        ): string {
            $this->encodeAttempts++;
            return '';
        }
    }

    final class HarnessVariableTranscode extends BaseTranscode
    {
        public ?bool $thumbnailGenerate = null;

        public function getVideoThumbnailUrl(
            Asset|string $filePath,
            array $thumbnailOptions,
            bool $generate = true,
            bool $asPath = false,
            bool $synchronous = false
        ): string|false|null {
            $this->thumbnailGenerate = $generate;
            return null;
        }
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
        }
    }

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    $root = sys_get_temp_dir() . '/transcoder-refresh-harness-' . bin2hex(random_bytes(6));
    $sourceDirectory = $root . '/content/videos/197915/';
    $encodedRoot = $root . '/content/encoded/video/';
    $encodedDirectory = $encodedRoot . '197915/';
    $gifDirectory = $root . '/content/encoded/gif/197915/';
    $audioDirectory = $root . '/content/encoded/audio/197915/';
    $thumbnailRoot = $root . '/content/encoded/thumbnail/';
    mkdir($sourceDirectory, 0777, true);
    mkdir($encodedDirectory, 0777, true);
    mkdir($gifDirectory, 0777, true);
    mkdir($audioDirectory, 0777, true);
    mkdir($thumbnailRoot, 0777, true);

    try {
        putenv('TRANSCODER_QUEUE_DELAY=9');
        putenv('TRANSCODER_SUBFOLDER_SEGMENT=3');
        putenv('TRANSCODER_WATERMARK_WIDTH=16');
        putenv('TRANSCODER_WATERMARK_PADDING=24');
        putenv('TRANSCODER_WATERMARK_OPACITY=100');

        $filename = 'asset-89b333afee70d7c0f2d21c1230b33777.mp4';
        $sourcePath = $sourceDirectory . $filename;
        $ffmpegBinary = trim((string)shell_exec('command -v ffmpeg'));
        $ffprobeBinary = trim((string)shell_exec('command -v ffprobe'));
        assertTrue($ffmpegBinary !== '', 'FFmpeg is required by the refresh regression harness.');
        assertTrue($ffprobeBinary !== '', 'FFprobe is required by the refresh regression harness.');
        exec(
            escapeshellarg($ffmpegBinary)
            . ' -loglevel error -f lavfi -i color=c=blue:s=64x64:d=0.2'
            . ' -c:v libx264 -pix_fmt yuv420p -y ' . escapeshellarg($sourcePath),
            $ffmpegOutput,
            $ffmpegExitCode
        );
        assertSameValue(0, $ffmpegExitCode, 'Could not create the focused FFmpeg source fixture.');

        Craft::$aliases['@encoded'] = $root . '/content/encoded';
        $settings = new HarnessSettings([
            'queueVideosOnAssetUpload' => false,
            'videoQueueDelaySeconds' => '$TRANSCODER_QUEUE_DELAY',
            'queuedVideoOptions' => [],
            'createSubfolders' => true,
            'subfolderUrlSegment' => false,
            'transcoderPaths' => [
                'default' => '@encoded/',
                'video' => '@encoded/video/',
                'thumbnail' => '@encoded/thumbnail/',
                'gif' => '@encoded/gif/',
                'audio' => '@encoded/audio/',
            ],
            'defaultVideoOptions' => [
                'videoEncoder' => 'h264',
                'videoBitRate' => '',
                'videoFrameRate' => '',
                'audioBitRate' => '',
                'audioSampleRate' => '',
                'audioChannels' => '',
                'width' => '',
                'height' => '',
                'sharpen' => true,
                'aspectRatio' => 'letterbox',
                'letterboxColor' => '',
            ],
            'videoEncoders' => [
                'h264' => [
                    'fileSuffix' => '.mp4',
                    'fileFormat' => 'mp4',
                    'videoCodec' => 'libx264',
                    'videoCodecOptions' => '',
                    'audioCodec' => 'aac',
                    'audioCodecOptions' => '',
                    'threads' => '0',
                ],
                'webm' => [
                    'fileSuffix' => '.webm',
                    'fileFormat' => 'webm',
                    'videoCodec' => 'libvpx',
                    'videoCodecOptions' => '-quality good -cpu-used 0',
                    'audioCodec' => 'libvorbis',
                    'audioCodecOptions' => '',
                    'threads' => '0',
                ],
                'gif' => [
                    'fileSuffix' => '.mp4',
                    'fileFormat' => 'mp4',
                    'videoCodec' => 'libx264',
                    'videoCodecOptions' => '-pix_fmt yuv420p -movflags +faststart',
                    'threads' => '0',
                ],
            ],
            'defaultGifOptions' => [
                'videoEncoder' => 'gif',
                'fileSuffix' => '',
                'fileFormat' => '',
                'videoCodec' => '',
                'videoCodecOptions' => '',
            ],
            'audioEncoders' => [
                'wav' => [
                    'fileSuffix' => '.wav',
                    'fileFormat' => 'wav',
                    'audioCodec' => 'pcm_s16le',
                    'audioCodecOptions' => '',
                    'threads' => '0',
                ],
            ],
            'defaultAudioOptions' => [
                'audioEncoder' => 'wav',
                'audioBitRate' => '',
                'audioSampleRate' => '22050',
                'audioChannels' => '1',
                'timeInSecs' => '',
                'seekInSecs' => '',
                'synchronous' => false,
                'stripMetadata' => false,
            ],
            'defaultThumbnailOptions' => [
                'fileSuffix' => '.jpg',
                'timeInSecs' => 4,
                'width' => '',
                'height' => '',
                'sharpen' => true,
                'aspectRatio' => 'letterbox',
                'letterboxColor' => '',
            ],
            'videoFilenameStrategy' => 'options',
            'useHashedNames' => false,
            'enableVideoWatermark' => false,
            'videoWatermarkPath' => '',
            'videoWatermarkWidth' => '$TRANSCODER_WATERMARK_WIDTH',
            'videoWatermarkPosition' => 'bottom-right',
            'videoWatermarkPadding' => '$TRANSCODER_WATERMARK_PADDING',
            'videoWatermarkOpacity' => '$TRANSCODER_WATERMARK_OPACITY',
            'enableVideoPosters' => false,
            'queueVideoPostersOnAssetUpload' => false,
            'preventVideoPosterBlackBars' => false,
            'videoPosterFormats' => [],
            'ffmpegPath' => $ffmpegBinary,
            'ffprobePath' => $ffprobeBinary,
            'ffprobeOptions' => '-v quiet -print_format json -show_format -show_streams',
            'transcoderUrls' => [
                'default' => 'https://example.test/encoded/',
                'video' => 'https://example.test/encoded/video/',
                'thumbnail' => 'https://example.test/encoded/thumbnail/',
                'gif' => 'https://example.test/encoded/gif/',
                'audio' => 'https://example.test/encoded/audio/',
            ],
        ], ArrayObject::ARRAY_AS_PROPS);

        $queue = new HarnessQueue();
        Craft::$app = new HarnessApp($queue);
        $plugin = new HarnessPlugin($settings);
        $service = new HarnessTranscode();
        $plugin->transcode = $service;
        Transcoder::$plugin = $plugin;

        $asset = new Asset();
        $asset->id = 197915;
        $asset->filename = $filename;
        $asset->folderPath = '197915/';
        $asset->sourcePath = $sourcePath;
        Asset::$assets[$asset->id] = $asset;

        $volumeRoot = $root . '/volume';
        $volumeSubpath = 'site-assets';
        $volumeAssetDirectory = $volumeRoot . '/' . $volumeSubpath . '/197915/';
        mkdir($volumeAssetDirectory, 0777, true);
        copy($sourcePath, $volumeAssetDirectory . $filename);
        $volumeAsset = new Asset();
        $volumeAsset->filename = $filename;
        $volumeAsset->folder = (object)['path' => '197915/'];
        $volumeAsset->volume = new class(new Local($volumeRoot), $volumeSubpath) {
            public function __construct(
                private readonly Local $fs,
                private readonly string $subpath,
            ) {
            }

            public function getFs(): Local
            {
                return $this->fs;
            }

            public function getSubPath(): string
            {
                return $this->subpath;
            }
        };
        assertSameValue(
            $volumeAssetDirectory . $filename,
            (new AssetPathTranscode())->resolveAssetPath($volumeAsset),
            'Craft 5 volume subpaths were dropped from the local source path.'
        );

        $cpFilename = 'asset-cp-thumbnail.mp4';
        $cpSourcePath = $sourceDirectory . $cpFilename;
        copy($sourcePath, $cpSourcePath);
        $persistedCpAsset = new Asset();
        $persistedCpAsset->id = 200961;
        $persistedCpAsset->filename = $cpFilename;
        $persistedCpAsset->folderPath = null;
        $persistedCpAsset->folder = (object)['path' => '200961/'];
        $persistedCpAsset->path = '200961/' . $cpFilename;
        $persistedCpAsset->sourcePath = $cpSourcePath;
        Asset::$assets[$persistedCpAsset->id] = $persistedCpAsset;

        $uploadEventAsset = new Asset();
        $uploadEventAsset->id = $persistedCpAsset->id;
        $uploadEventAsset->filename = $cpFilename;
        $uploadEventAsset->folderPath = null;
        $uploadEventAsset->folder = (object)['path' => 'user_42/'];
        $uploadEventAsset->path = 'user_42/' . $cpFilename;
        $uploadEventAsset->sourcePath = $cpSourcePath;

        $cpThumbnailFilename = 'asset-cp-thumbnail_4s_800w_450h_letterbox_.jpg';
        $cpThumbnailDirectory = $thumbnailRoot . '200961/';
        mkdir($cpThumbnailDirectory, 0777, true);
        file_put_contents($cpThumbnailDirectory . $cpThumbnailFilename, 'cp-thumbnail');
        file_put_contents($thumbnailRoot . $cpThumbnailFilename, 'stale-root-thumbnail');
        $cpThumbnailUrl = $service->handleGetAssetThumbPath(
            new DefineAssetThumbUrlEvent($uploadEventAsset, 800, 450)
        );
        assertTrue(
            is_string($cpThumbnailUrl)
                && str_starts_with(
                    $cpThumbnailUrl,
                    'https://example.test/encoded/thumbnail/200961/' . $cpThumbnailFilename . '?v='
                ),
            'The Control Panel thumbnail event did not reload the final Asset folder.'
        );
        assertSameValue(
            'stale-root-thumbnail',
            file_get_contents($thumbnailRoot . $cpThumbnailFilename),
            'The Control Panel thumbnail event reused or replaced the stale root derivative.'
        );
        assertSameValue(
            $cpThumbnailDirectory . $cpThumbnailFilename,
            $service->getVideoThumbnailUrl(
                $persistedCpAsset,
                ['width' => 800, 'height' => 450],
                false,
                true
            ),
            'Thumbnail generation did not fall back to the Craft folder model when folderPath was empty.'
        );

        $temporaryAsset = new Asset();
        $temporaryAsset->id = 200962;
        $temporaryAsset->filename = 'temporary-upload.mp4';
        $temporaryAsset->folderPath = null;
        $temporaryAsset->folder = (object)['path' => 'user_84/'];
        $temporaryAsset->path = 'user_84/temporary-upload.mp4';
        $temporaryAsset->sourcePath = $cpSourcePath;
        $temporaryAsset->volumeId = null;
        Asset::$assets[$temporaryAsset->id] = $temporaryAsset;
        assertSameValue(
            null,
            $service->handleGetAssetThumbPath(new DefineAssetThumbUrlEvent($temporaryAsset, 800, 450)),
            'A temporary Control Panel upload should not generate a Transcoder thumbnail.'
        );
        assertTrue(
            in_array(
                'Skipped Control Panel video thumbnail generation for temporary asset #200962.',
                Craft::$logs,
                true
            ),
            'The skipped temporary Control Panel thumbnail was not logged.'
        );

        $variableService = new HarnessVariableTranscode();
        $plugin->transcode = $variableService;
        (new TranscoderVariable())->getVideoThumbnailUrl($asset, [], false);
        assertSameValue(
            false,
            $variableService->thumbnailGenerate,
            'The Twig thumbnail variable did not forward its generate argument.'
        );
        $plugin->transcode = $service;

        $settings->enableVideoPosters = true;
        $settings->queueVideoPostersOnAssetUpload = true;
        assertSameValue(
            true,
            $service->shouldQueueStandaloneVideoPostersOnUpload(),
            'Poster-only upload queueing was not enabled independently.'
        );
        $settings->queueVideosOnAssetUpload = true;
        assertSameValue(
            false,
            $service->shouldQueueStandaloneVideoPostersOnUpload(),
            'Poster-only upload queueing would duplicate the full video job.'
        );
        $settings->queueVideosOnAssetUpload = false;
        $settings->videoPosterFormats = [
            'upload' => [
                'width' => 80,
                'height' => 45,
                'timeInSecs' => 3,
            ],
        ];
        $settings->preventVideoPosterBlackBars = true;
        $posterJob = new GenerateVideoPosters([
            'assetId' => $asset->id,
        ]);
        $posterJob->execute($queue);
        $configuredPosterUrl = $service->getVideoPosterUrl($asset, 'upload', false);
        $posterPath = $service->getVideoThumbnailUrl(
            $asset,
            [
                'width' => 80,
                'height' => 45,
                'timeInSecs' => 3,
            ],
            false,
            true
        );
        assertTrue(is_string($posterPath) && is_file($posterPath), 'The standalone poster job did not create its output.');
        assertSameValue(
            'asset-89b333afee70d7c0f2d21c1230b33777_3s_80w_45h_letterbox_.jpg',
            basename($posterPath),
            'Internal duration clamping changed the canonical filename used by refresh and direct Twig requests.'
        );
        clearstatcache(true, $posterPath);
        assertSameValue(
            'https://example.test/encoded/thumbnail/197915/' . basename($posterPath) . '?v=' . filemtime($posterPath),
            $configuredPosterUrl,
            'The configured poster URL was not cache-busted from the generated file modification time.'
        );
        assertTrue(
            str_contains($posterPath, DIRECTORY_SEPARATOR . 'thumbnail' . DIRECTORY_SEPARATOR . '197915' . DIRECTORY_SEPARATOR),
            'The standalone poster job ignored the Asset subfolder.'
        );
        assertTrue(
            in_array('Generated 1 video poster(s) for asset #197915.', Craft::$logs, true),
            'The standalone poster job did not log successful completion.'
        );
        file_put_contents($posterPath, '');
        touch($posterPath, time() + 5);
        $posterJob->execute($queue);
        clearstatcache(true, $posterPath);
        assertTrue(
            is_file($posterPath) && filesize($posterPath) > 0,
            'A newer zero-byte poster was incorrectly accepted as a successful cached output.'
        );

        $settings->enableVideoPosters = false;
        $settings->preventVideoPosterBlackBars = false;
        assertSameValue(
            false,
            $service->shouldQueueStandaloneVideoPostersOnUpload(),
            'Disabled poster generation still requested an upload job.'
        );
        $disabledPosterMtime = filemtime($posterPath);
        $posterJob->execute($queue);
        clearstatcache(true, $posterPath);
        assertSameValue(
            $disabledPosterMtime,
            filemtime($posterPath),
            'A disabled standalone poster job changed its existing output.'
        );
        assertTrue(
            in_array('Skipped disabled video poster generation for asset #197915.', Craft::$logs, true),
            'The disabled standalone poster job did not log its no-op.'
        );

        $settings->enableVideoPosters = true;
        try {
            (new GenerateVideoPosters(['assetId' => $temporaryAsset->id]))->execute($queue);
            throw new RuntimeException('A temporary upload poster job should fail visibly.');
        } catch (RuntimeException $e) {
            assertTrue(
                str_contains($e->getMessage(), 'temporary upload storage'),
                'A temporary upload poster job did not report the expected failure.'
            );
        }
        $settings->enableVideoPosters = false;
        $settings->queueVideoPostersOnAssetUpload = false;
        $settings->videoPosterFormats = [];

        $gifSourcePath = $sourceDirectory . 'animated.gif';
        exec(
            escapeshellarg($ffmpegBinary)
            . ' -loglevel error -f lavfi -i color=c=red:s=64x64:d=0.4'
            . ' -vf fps=5 -y ' . escapeshellarg($gifSourcePath),
            $gifFixtureOutput,
            $gifFixtureExitCode
        );
        assertSameValue(0, $gifFixtureExitCode, 'Could not create the focused GIF source fixture.');
        $gifAsset = new Asset();
        $gifAsset->id = 197916;
        $gifAsset->filename = 'animated.gif';
        $gifAsset->folderPath = '197915/';
        $gifAsset->sourcePath = $gifSourcePath;
        Asset::$assets[$gifAsset->id] = $gifAsset;

        $gifJob = new EncodeGif([
            'assetId' => $gifAsset->id,
            'gifOptions' => [],
        ]);
        $gifJob->execute($queue);
        $gifFilename = $service->getGifFilename($gifAsset, []);
        assertTrue(is_file($gifDirectory . $gifFilename), 'Queued GIF encoding did not create its expected output.');
        assertTrue(filesize($gifDirectory . $gifFilename) > 0, 'Queued GIF encoding created an empty output.');
        assertTrue(
            in_array("Encoded GIF asset #197916: https://example.test/encoded/gif/197915/$gifFilename", Craft::$logs, true),
            'Queued GIF encoding did not log successful completion.'
        );
        file_put_contents($gifDirectory . $gifFilename, '');
        touch($gifDirectory . $gifFilename, time() + 5);
        $gifJob->execute($queue);
        clearstatcache(true, $gifDirectory . $gifFilename);
        assertTrue(
            filesize($gifDirectory . $gifFilename) > 0,
            'A newer zero-byte GIF derivative was incorrectly accepted as a completed queue job.'
        );
        $gifPidLock = $service->mediaLockFile('gif', $gifDirectory . $gifFilename);
        file_put_contents($gifPidLock, (string)getmypid());
        try {
            $gifJob->execute($queue);
            throw new RuntimeException('Queued GIF encoding ignored an active legacy PID lock.');
        } catch (RuntimeException $e) {
            assertTrue(
                str_contains($e->getMessage(), 'GIF encoding failed'),
                'An active GIF PID lock did not fail visibly in Craft’s queue.'
            );
        } finally {
            @unlink($gifPidLock);
        }

        $audioSourcePath = $sourceDirectory . 'tone.wav';
        exec(
            escapeshellarg($ffmpegBinary)
            . ' -loglevel error -f lavfi -i sine=frequency=1000:duration=0.2'
            . ' -c:a pcm_s16le -y ' . escapeshellarg($audioSourcePath),
            $audioFixtureOutput,
            $audioFixtureExitCode
        );
        assertSameValue(0, $audioFixtureExitCode, 'Could not create the focused audio source fixture.');
        $audioAsset = new Asset();
        $audioAsset->id = 197917;
        $audioAsset->filename = 'tone.wav';
        $audioAsset->folderPath = '197915/';
        $audioAsset->sourcePath = $audioSourcePath;
        Asset::$assets[$audioAsset->id] = $audioAsset;

        $audioJob = new EncodeAudio([
            'assetId' => $audioAsset->id,
            'audioOptions' => [],
        ]);
        $audioJob->execute($queue);
        $audioFilename = $service->getAudioFilename($audioAsset, []);
        assertTrue(is_file($audioDirectory . $audioFilename), 'Queued audio encoding did not create its expected output.');
        assertTrue(filesize($audioDirectory . $audioFilename) > 0, 'Queued audio encoding created an empty output.');
        assertTrue(
            in_array("Encoded audio asset #197917: https://example.test/encoded/audio/197915/$audioFilename", Craft::$logs, true),
            'Queued audio encoding did not log successful completion.'
        );
        file_put_contents($audioDirectory . $audioFilename, '');
        touch($audioDirectory . $audioFilename, time() + 5);
        $audioJob->execute($queue);
        clearstatcache(true, $audioDirectory . $audioFilename);
        assertTrue(
            filesize($audioDirectory . $audioFilename) > 0,
            'A newer zero-byte audio derivative was incorrectly accepted as a completed queue job.'
        );
        $audioPidLock = $service->mediaLockFile('audio', $audioDirectory . $audioFilename);
        file_put_contents($audioPidLock, (string)getmypid());
        try {
            $audioJob->execute($queue);
            throw new RuntimeException('Queued audio encoding ignored an active legacy PID lock.');
        } catch (RuntimeException $e) {
            assertTrue(
                str_contains($e->getMessage(), 'Audio encoding failed'),
                'An active audio PID lock did not fail visibly in Craft’s queue.'
            );
        } finally {
            @unlink($audioPidLock);
        }

        $expectedFilename = 'asset-89b333afee70d7c0f2d21c1230b33777_bps_fps_bps__c_w_h_letterbox_.mp4';
        $expectedPath = $encodedDirectory . $expectedFilename;
        file_put_contents($expectedPath, 'old-encoded-bytes');

        $outputInfo = $service->outputInfo($asset, $settings->queuedVideoOptions);
        $targets = $service->refreshTargets($asset);
        $sameNameOtherFolder = new Asset();
        $sameNameOtherFolder->id = 197918;
        $sameNameOtherFolder->filename = $filename;
        $sameNameOtherFolder->folderPath = '197918/';
        $sameNameOtherFolder->sourcePath = $sourcePath;
        assertTrue(
            $outputInfo['lockFile'] !== $service->outputInfo($sameNameOtherFolder, [])['lockFile'],
            'Video process locks collide for identical filenames in different Asset subfolders.'
        );
        assertSameValue($expectedFilename, $outputInfo['filename'], 'The production empty-option filename shape changed.');
        assertSameValue($expectedPath, $outputInfo['path'], 'Encode output path does not match the production subfolder shape.');
        assertSameValue($outputInfo['path'], $targets['output'][0], 'Refresh and EncodeVideo must use a byte-identical output path.');
        file_put_contents($expectedPath, '');
        touch($expectedPath, time() + 5);
        assertSameValue(
            '',
            $service->getVideoUrl($asset, $settings->queuedVideoOptions, false, true),
            'A zero-byte failed encode was treated as a reusable video output.'
        );
        file_put_contents($expectedPath, 'old-encoded-bytes');

        $settings->videoFilenameStrategy = 'source';
        $sourceStrategy = $service->outputInfo($asset, []);
        assertSameValue('asset-89b333afee70d7c0f2d21c1230b33777.mp4', $sourceStrategy['filename'], 'Source filename strategy changed.');
        assertSameValue($sourceStrategy['path'], $service->refreshTargets($asset)['output'][0], 'Source-strategy paths diverge.');

        $settings->videoFilenameStrategy = 'options';
        $settings->useHashedNames = true;
        $hashed = $service->outputInfo($asset, []);
        assertTrue((bool)preg_match('/^asset-89b333afee70d7c0f2d21c1230b33777[0-9a-f]{32}\.mp4$/', $hashed['filename']), 'Hashed filename shape changed.');
        assertSameValue($hashed['path'], $service->refreshTargets($asset)['output'][0], 'Hashed paths diverge.');

        $settings->useHashedNames = false;
        $watermarkPath = $root . '/watermark.png';
        exec(
            escapeshellarg($ffmpegBinary)
            . ' -loglevel error -f lavfi -i color=c=red:s=16x16:d=0.1'
            . ' -frames:v 1 -y ' . escapeshellarg($watermarkPath),
            $watermarkFixtureOutput,
            $watermarkFixtureExitCode
        );
        assertSameValue(0, $watermarkFixtureExitCode, 'Could not create the watermark image fixture.');
        $settings->enableVideoWatermark = true;
        $settings->videoWatermarkPath = $watermarkPath;
        $watermarked = $service->outputInfo($asset, []);
        assertTrue((bool)preg_match('/_[0-9a-f]{10}\.mp4$/', $watermarked['filename']), 'Watermark fingerprint is missing from the filename.');
        assertSameValue($watermarked['path'], $service->refreshTargets($asset)['output'][0], 'Watermarked paths diverge.');
        $watermarkedUrl = $service->getVideoUrl($asset, [], true, true);
        assertTrue(
            is_file($watermarked['path'])
                && filesize($watermarked['path']) > 0
                && str_starts_with($watermarkedUrl, $watermarked['url'] . '?v='),
            'The real FFmpeg watermark filter did not produce a non-empty cache-versioned output.'
        );

        $settings->enableVideoWatermark = false;
        $settings->videoWatermarkPath = '';
        $settings->createSubfolders = false;
        $flat = $service->outputInfo($asset, []);
        assertSameValue($encodedRoot . $expectedFilename, $flat['path'], 'Flat output path or trailing separator changed.');
        assertSameValue($flat, $service->outputInfo($sourcePath, []), 'Asset and string source conversion produce different output metadata.');
        assertSameValue($flat['path'], $service->refreshTargets($asset)['output'][0], 'Flat paths diverge.');

        $settings->createSubfolders = true;
        $settings->subfolderUrlSegment = '$TRANSCODER_SUBFOLDER_SEGMENT';
        $paths = $settings->transcoderPaths;
        $paths['video'] = '@encoded/video';
        $settings->transcoderPaths = $paths;
        $urls = $settings->transcoderUrls;
        $urls['video'] = 'https://example.test/encoded/video';
        $settings->transcoderUrls = $urls;
        $urlInput = 'https://example.test/content/videos/197915/' . $filename;
        $urlOutput = $service->outputInfo($urlInput, []);
        assertSameValue($expectedPath, $urlOutput['path'], 'String URL input ignored its configured output subfolder.');
        assertSameValue(
            'https://example.test/encoded/video/197915/' . $expectedFilename,
            $urlOutput['url'],
            'String URL output URL ignored its configured subfolder.'
        );
        assertSameValue($outputInfo['path'], $urlOutput['path'], 'Asset and URL callers do not resolve to the same output path.');
        $unsafeUrl = 'https://example.test/content/videos/%2E%2E/' . $filename;
        assertSameValue(
            $encodedRoot . $expectedFilename,
            $service->outputInfo($unsafeUrl, [])['path'],
            'An unsafe URL segment escaped the configured video output directory.'
        );
        $settings->subfolderUrlSegment = false;

        $webmOptions = ['videoEncoder' => 'webm'];
        $webmUrl = $service->getVideoUrl($asset, $webmOptions, true, true);
        $webmFilename = $service->getVideoFilename($asset, $webmOptions);
        $webmPath = $encodedDirectory . $webmFilename;
        assertTrue(
            is_file($webmPath) && filesize($webmPath) > 0,
            'The upstream Craft 5 WebM workflow no longer produces a non-empty output.'
        );
        assertTrue(
            str_starts_with($webmUrl, 'https://example.test/encoded/video/197915/' . $webmFilename . '?v='),
            'The WebM output did not retain the generated subfolder or cache version.'
        );

        $publicRefresh = $service->refreshVideoAsset($asset);
        assertSameValue(true, $publicRefresh['queued'], 'The public refresh API did not report a queued job.');
        assertSameValue('job-1', $publicRefresh['jobId'], 'The public refresh API returned the wrong job ID.');
        assertTrue(
            $queue->jobs[0] instanceof RefreshVideoAsset,
            'The public refresh API did not queue a RefreshVideoAsset job.'
        );
        $queue->jobs = [];
        $queue->delaySeconds = 0;

        $settings->enableVideoPosters = true;
        $settings->preventVideoPosterBlackBars = true;
        $settings->videoPosterFormats = [
            'upload' => [
                'width' => 80,
                'height' => 45,
                'timeInSecs' => 3,
            ],
        ];
        assertTrue(is_file($posterPath), 'The managed poster fixture disappeared before refresh cleanup.');
        exec(
            escapeshellarg($ffmpegBinary)
            . ' -loglevel error -f lavfi -i color=c=green:s=64x64:d=1.2'
            . ' -c:v libx264 -pix_fmt yuv420p -y ' . escapeshellarg($sourcePath),
            $replacementFixtureOutput,
            $replacementFixtureExitCode
        );
        assertSameValue(0, $replacementFixtureExitCode, 'Could not replace the video source fixture before refresh.');
        $targets = $service->refreshTargets($asset);
        assertTrue(
            in_array($posterPath, $targets['output'], true),
            'Refresh cleanup did not target the canonical poster generated for the previous source duration.'
        );
        file_put_contents($targets['temporary'][0], 'stale-lock');
        file_put_contents($targets['temporary'][1], 'stale-progress');
        $result = $service->performVideoAssetRefresh($asset);
        assertSameValue(false, $settings->queueVideosOnAssetUpload, 'The regression must run with upload queueing disabled.');
        assertTrue(!file_exists($expectedPath), 'Explicit refresh did not remove the old managed derivative.');
        assertTrue(!file_exists($posterPath), 'Explicit refresh did not remove the exact configured poster derivative.');
        assertTrue(is_file($webmPath), 'Explicit refresh removed an unconfigured video derivative.');
        assertTrue(!file_exists($targets['temporary'][0]), 'Explicit refresh did not remove its exact stale lock file.');
        assertTrue(!file_exists($targets['temporary'][1]), 'Explicit refresh did not remove its exact stale progress file.');
        assertSameValue(4, $result['removedFiles'], 'Explicit refresh did not report its exact managed files.');
        assertSameValue(true, $result['queued'], 'Explicit refresh did not queue replacement encoding.');
        assertSameValue('job-1', $result['jobId'], 'Unexpected follow-up job ID.');
        assertTrue($queue->jobs[0] instanceof EncodeVideo, 'The follow-up job is not EncodeVideo.');
        assertSameValue(197915, $queue->jobs[0]->assetId, 'EncodeVideo received the wrong asset ID.');
        assertSameValue(9, $queue->delaySeconds, 'Configured video queue delay was not applied.');
        assertTrue(
            in_array('Refreshed video asset #197915; removed files: 4; follow-up encode job ID: job-1', Craft::$logs, true),
            'The refresh audit log is missing removedFiles and follow-up job ID.'
        );

        $encodedUrl = '';
        assertTrue(
            $service->runVideoAssetWork($asset, function() use ($asset, $service, &$encodedUrl): void {
                $encodedUrl = $service->getVideoUrl($asset, [], true, true);
            }),
            'The queued EncodeVideo-equivalent work lock could not be acquired.'
        );
        assertTrue(is_file($expectedPath) && filesize($expectedPath) > 0, 'FFmpeg did not recreate the deleted output.');
        assertTrue(file_get_contents($expectedPath) !== 'old-encoded-bytes', 'FFmpeg returned or preserved the old derivative.');
        assertTrue(str_contains($encodedUrl, '?v='), 'The regenerated video URL is not cache-versioned.');

        file_put_contents($expectedPath, 'old-encoded-bytes-again');
        touch($expectedPath, 1786700000);
        assertSameValue(
            'https://example.test/encoded/video/197915/' . $expectedFilename . '?v=1786700000',
            $service->versionedUrl('https://example.test/encoded/video/197915/' . $expectedFilename, $expectedPath),
            'Stable generated media URLs are not versioned from the actual output modification time.'
        );
        $queue->failPush = true;
        try {
            $service->performVideoAssetRefresh($asset);
            throw new RuntimeException('A null follow-up queue ID must fail the refresh job.');
        } catch (RuntimeException $e) {
            assertTrue(
                str_contains($e->getMessage(), 'Unable to queue replacement video encoding'),
                'A failed follow-up queue push did not surface the expected error.'
            );
        }
        assertTrue(
            in_array(
                'Video refresh handoff failed for asset #197915; removed files: 1; follow-up encode job ID: none',
                Craft::$logs,
                true
            ),
            'The failed handoff log does not expose removedFiles=1 and a missing follow-up job ID.'
        );

        $queue->failPush = false;
        $missingResult = $service->performVideoAssetRefresh($asset);
        assertSameValue(0, $missingResult['removedFiles'], 'Missing derivatives must be a successful no-op.');
        assertTrue(
            in_array('Refreshed video asset #197915; removed files: 0; follow-up encode job ID: job-2', Craft::$logs, true),
            'The refresh log does not make removedFiles=0 obvious.'
        );

        $queue->jobs = [];
        $queue->delaySeconds = 0;
        (new RefreshVideoAsset(['assetId' => $asset->id]))->execute($queue);
        assertTrue(
            $queue->jobs[0] instanceof EncodeVideo,
            'The queued refresh job did not hand off to EncodeVideo.'
        );
        (new RefreshVideoAsset(['assetId' => 999999]))->execute($queue);
        assertTrue(
            in_array('Video refresh skipped missing asset #999999.', Craft::$logs, true),
            'The queued refresh job did not safely skip a missing Asset.'
        );

        putenv('TRANSCODER_VIDEO_RETRIES=2');
        putenv('TRANSCODER_VIDEO_RETRY_DELAY=17');
        $settings->videoEncodeMaxRetries = '$TRANSCODER_VIDEO_RETRIES';
        $settings->videoEncodeRetryDelaySeconds = '$TRANSCODER_VIDEO_RETRY_DELAY';
        $settings->enableVideoPosters = false;
        $queue->jobs = [];
        $queue->delaySeconds = 0;
        $retryService = new FailingVideoTranscode();
        $plugin->transcode = $retryService;

        $firstAttempt = new EncodeVideo([
            'assetId' => $asset->id,
            'videoOptions' => ['width' => 1280],
        ]);
        $firstAttempt->execute($queue);
        assertSameValue(1, $retryService->encodeAttempts, 'The initial queued video encode did not run once.');
        assertSameValue(1, count($queue->jobs), 'A failed video encode did not queue exactly one retry.');
        assertTrue($queue->jobs[0] instanceof EncodeVideo, 'The queued video retry is not an EncodeVideo job.');
        assertSameValue(2, $queue->jobs[0]->attempt, 'The queued video retry has the wrong attempt number.');
        assertSameValue(2, $queue->jobs[0]->maxRetries, 'The queued video retry lost the configured retry count.');
        assertSameValue(17, $queue->jobs[0]->retryDelaySeconds, 'The queued video retry lost its configured delay.');
        assertSameValue(['width' => 1280], $queue->jobs[0]->videoOptions, 'The queued video retry lost its encoding options.');
        assertSameValue(17, $queue->delaySeconds, 'The configured video retry delay was not applied.');
        $retryLog = 'Retrying video encode attempt 2 of 3 in 17s; asset #197915; retry job ID: job-1;'
            . ' previous error: Video encoding failed for asset #197915.';
        assertTrue(
            in_array(
                $retryLog,
                Craft::$logs,
                true
            ),
            'The queued video retry was not logged with its attempt, delay, and job ID.'
        );

        $queue->jobs = [];
        $finalAttempt = new EncodeVideo([
            'assetId' => $asset->id,
            'attempt' => 3,
            'maxRetries' => 2,
            'retryDelaySeconds' => 17,
        ]);
        try {
            $finalAttempt->execute($queue);
            throw new RuntimeException('The final video attempt must remain a failed Craft queue job.');
        } catch (RuntimeException $e) {
            assertTrue(
                str_contains($e->getMessage(), 'Video encoding failed'),
                'The final video attempt did not expose the original encoding failure.'
            );
        }
        assertSameValue([], $queue->jobs, 'The final video attempt exceeded the configured retry count.');

        $queue->failPush = true;
        try {
            (new EncodeVideo(['assetId' => $asset->id]))->execute($queue);
            throw new RuntimeException('A failed video retry push must leave the current Craft job failed.');
        } catch (RuntimeException $e) {
            assertTrue(
                str_contains($e->getMessage(), 'Video encoding failed'),
                'A failed video retry push hid the original encoding failure.'
            );
        }
        assertTrue(
            in_array('Unable to queue video encoding retry for asset #197915.', Craft::$logs, true),
            'A failed video retry push was not logged.'
        );
        $queue->failPush = false;
        $plugin->transcode = $service;

        echo "refresh-video-asset harness: OK\n";
    } finally {
        putenv('TRANSCODER_QUEUE_DELAY');
        putenv('TRANSCODER_SUBFOLDER_SEGMENT');
        putenv('TRANSCODER_WATERMARK_WIDTH');
        putenv('TRANSCODER_WATERMARK_PADDING');
        putenv('TRANSCODER_WATERMARK_OPACITY');
        putenv('TRANSCODER_VIDEO_RETRIES');
        putenv('TRANSCODER_VIDEO_RETRY_DELAY');

        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($root);
        }
    }
}
