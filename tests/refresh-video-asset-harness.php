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
        public const KIND_VIDEO = 'video';

        public ?int $id = null;
        public string $filename = '';
        public string $folderPath = '';
        public string $sourcePath = '';

        public static function find(): object
        {
            throw new \RuntimeException('Asset queries are not used by this focused harness.');
        }
    }
}

namespace craft\events {
    class DefineAssetThumbUrlEvent
    {
    }
}

namespace craft\fs {
    class Local
    {
    }
}

namespace craft\helpers {
    final class App
    {
        public static function parseEnv(?string $value): bool|string|null
        {
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
            return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm'], true)
                ? \craft\elements\Asset::KIND_VIDEO
                : 'unknown';
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

namespace {
    use craft\elements\Asset;
    use nystudio107\transcoder\Transcoder;
    use nystudio107\transcoder\jobs\EncodeVideo;
    use nystudio107\transcoder\services\Transcode as BaseTranscode;

    require dirname(__DIR__) . '/src/jobs/EncodeVideo.php';
    require dirname(__DIR__) . '/src/jobs/RefreshVideoAsset.php';
    require dirname(__DIR__) . '/src/services/Transcode.php';

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
    mkdir($sourceDirectory, 0777, true);
    mkdir($encodedDirectory, 0777, true);

    try {
        $filename = 'asset-89b333afee70d7c0f2d21c1230b33777.mp4';
        $sourcePath = $sourceDirectory . $filename;
        $ffmpegBinary = trim((string)shell_exec('command -v ffmpeg'));
        assertTrue($ffmpegBinary !== '', 'FFmpeg is required by the refresh regression harness.');
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
            'videoQueueDelaySeconds' => 9,
            'queuedVideoOptions' => [],
            'createSubfolders' => true,
            'subfolderUrlSegment' => false,
            'transcoderPaths' => [
                'default' => '@encoded/',
                'video' => '@encoded/video/',
                'thumbnail' => '@encoded/thumbnail/',
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
            ],
            'videoFilenameStrategy' => 'options',
            'useHashedNames' => false,
            'enableVideoWatermark' => false,
            'videoWatermarkPath' => '',
            'videoWatermarkWidth' => '',
            'videoWatermarkPosition' => 'bottom-right',
            'videoWatermarkPadding' => 24,
            'videoWatermarkOpacity' => 100,
            'enableVideoPosters' => false,
            'ffmpegPath' => $ffmpegBinary,
            'transcoderUrls' => [
                'default' => 'https://example.test/encoded/',
                'video' => 'https://example.test/encoded/video/',
                'thumbnail' => 'https://example.test/encoded/thumbnail/',
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

        $expectedFilename = 'asset-89b333afee70d7c0f2d21c1230b33777_bps_fps_bps__c_w_h_letterbox_.mp4';
        $expectedPath = $encodedDirectory . $expectedFilename;
        file_put_contents($expectedPath, 'old-encoded-bytes');

        $outputInfo = $service->outputInfo($asset, $settings->queuedVideoOptions);
        $targets = $service->refreshTargets($asset);
        assertSameValue($expectedFilename, $outputInfo['filename'], 'The production empty-option filename shape changed.');
        assertSameValue($expectedPath, $outputInfo['path'], 'Encode output path does not match the production subfolder shape.');
        assertSameValue($outputInfo['path'], $targets['output'][0], 'Refresh and EncodeVideo must use a byte-identical output path.');

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
        file_put_contents($watermarkPath, 'watermark');
        $settings->enableVideoWatermark = true;
        $settings->videoWatermarkPath = $watermarkPath;
        $watermarked = $service->outputInfo($asset, []);
        assertTrue((bool)preg_match('/_[0-9a-f]{10}\.mp4$/', $watermarked['filename']), 'Watermark fingerprint is missing from the filename.');
        assertSameValue($watermarked['path'], $service->refreshTargets($asset)['output'][0], 'Watermarked paths diverge.');

        $settings->enableVideoWatermark = false;
        $settings->videoWatermarkPath = '';
        $settings->createSubfolders = false;
        $flat = $service->outputInfo($asset, []);
        assertSameValue($encodedRoot . $expectedFilename, $flat['path'], 'Flat output path or trailing separator changed.');
        assertSameValue($flat, $service->outputInfo($sourcePath, []), 'Asset and string source conversion produce different output metadata.');
        assertSameValue($flat['path'], $service->refreshTargets($asset)['output'][0], 'Flat paths diverge.');

        $settings->createSubfolders = true;
        $settings->subfolderUrlSegment = 3;
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

        $result = $service->performVideoAssetRefresh($asset);
        assertSameValue(false, $settings->queueVideosOnAssetUpload, 'The regression must run with upload queueing disabled.');
        assertTrue(!file_exists($expectedPath), 'Explicit refresh did not remove the old managed derivative.');
        assertSameValue(1, $result['removedFiles'], 'Explicit refresh should report one removed derivative.');
        assertSameValue(true, $result['queued'], 'Explicit refresh did not queue replacement encoding.');
        assertSameValue('job-1', $result['jobId'], 'Unexpected follow-up job ID.');
        assertTrue($queue->jobs[0] instanceof EncodeVideo, 'The follow-up job is not EncodeVideo.');
        assertSameValue(197915, $queue->jobs[0]->assetId, 'EncodeVideo received the wrong asset ID.');
        assertSameValue(9, $queue->delaySeconds, 'Configured video queue delay was not applied.');
        assertTrue(
            in_array('Refreshed video asset #197915; removed files: 1; follow-up encode job ID: job-1', Craft::$logs, true),
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

        echo "refresh-video-asset harness: OK\n";
    } finally {
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
