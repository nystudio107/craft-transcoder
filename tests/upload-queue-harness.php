<?php

declare(strict_types=1);

namespace {
    final class Craft
    {
        public static object $app;

        /** @var array<int, array{level: string, message: string, category: string}> */
        public static array $logs = [];

        public static function debug(string $message, string $category = ''): void
        {
            self::log('debug', $message, $category);
        }

        public static function error(string $message, string $category = ''): void
        {
            self::log('error', $message, $category);
        }

        public static function info(string $message, string $category = ''): void
        {
            self::log('info', $message, $category);
        }

        public static function warning(string $message, string $category = ''): void
        {
            self::log('warning', $message, $category);
        }

        public static function t(string $category, string $message, array $params = []): string
        {
            foreach ($params as $key => $value) {
                $message = str_replace('{' . $key . '}', (string)$value, $message);
            }

            return $message;
        }

        public static function parseEnv(string $value): string
        {
            return $value;
        }

        private static function log(string $level, string $message, string $category): void
        {
            self::$logs[] = compact('level', 'message', 'category');
        }
    }
}

namespace craft\base {
    class Model
    {
        public function __construct(array $config = [])
        {
            foreach ($config as $name => $value) {
                $this->{$name} = $value;
            }
        }
    }

    class Plugin
    {
        public string $controllerNamespace = '';
        public string $handle = 'transcoder';
        public string $name = 'Transcoder';

        private Model $settings;

        public function __construct(array $config = [])
        {
            $this->settings = $config['settings'];
        }

        public function init(): void
        {
        }

        public function getSettings(): Model
        {
            return $this->settings;
        }
    }
}

namespace craft\console {
    class Application
    {
    }
}

namespace craft\elements {
    class Asset
    {
        public const EVENT_AFTER_SAVE = 'afterSave';
        public const KIND_AUDIO = 'audio';
        public const KIND_VIDEO = 'video';

        public function __construct(
            public ?int $id = null,
            public string $filename = '',
            public bool $temporary = false,
        ) {
        }
    }
}

namespace craft\events {
    class DefineAssetThumbUrlEvent
    {
    }

    class ModelEvent extends \yii\base\Event
    {
        public bool $isNew = false;
    }

    class PluginEvent extends \yii\base\Event
    {
        public mixed $plugin = null;
    }

    class RegisterCacheOptionsEvent extends \yii\base\Event
    {
        public array $options = [];
    }

    class RegisterUrlRulesEvent extends \yii\base\Event
    {
        public array $rules = [];
    }

    class TemplateEvent extends \yii\base\Event
    {
        public string $template = '';
        public array $variables = [];
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

            return $value;
        }
    }

    final class Assets
    {
        public static function getFileKindByExtension(string $filename): string
        {
            return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
                'mp4', 'mov', 'webm' => \craft\elements\Asset::KIND_VIDEO,
                'aac', 'flac', 'm4a', 'mp3', 'ogg', 'wav' => \craft\elements\Asset::KIND_AUDIO,
                default => 'unknown',
            };
        }
    }

    final class FileHelper
    {
        public static function clearDirectory(string $directory): void
        {
        }
    }

    final class UrlHelper
    {
        public static function cpUrl(string $path): string
        {
            return $path;
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

namespace craft\services {
    final class Assets
    {
        public const EVENT_DEFINE_THUMB_URL = 'defineThumbUrl';
    }

    final class Plugins
    {
        public const EVENT_AFTER_INSTALL_PLUGIN = 'afterInstallPlugin';
    }
}

namespace craft\utilities {
    final class ClearCaches
    {
        public const EVENT_REGISTER_CACHE_OPTIONS = 'registerCacheOptions';
    }
}

namespace craft\web {
    final class UrlManager
    {
        public const EVENT_REGISTER_SITE_URL_RULES = 'registerSiteUrlRules';
    }

    final class View
    {
        public const EVENT_BEFORE_RENDER_TEMPLATE = 'beforeRenderTemplate';
    }
}

namespace craft\web\twig\variables {
    final class CraftVariable
    {
        public const EVENT_INIT = 'init';

        public function set(string $name, array $config): void
        {
        }
    }
}

namespace yii\base {
    class ErrorException extends \Exception
    {
    }

    class Event
    {
        /** @var array<string, array<string, callable[]>> */
        private static array $handlers = [];

        public mixed $sender = null;

        public function __construct(array $config = [])
        {
            foreach ($config as $name => $value) {
                $this->{$name} = $value;
            }
        }

        public static function on(string $class, string $name, callable $handler): void
        {
            self::$handlers[$class][$name][] = $handler;
        }

        public static function trigger(string $class, string $name, self $event): void
        {
            foreach (self::$handlers[$class][$name] ?? [] as $handler) {
                $handler($event);
            }
        }
    }
}

namespace nystudio107\transcoder\models {
    final class Settings extends \craft\base\Model
    {
        public bool $clearCaches = false;
        public bool $enableVideoPosters = true;
        public bool $queueAudioOnAssetUpload = true;
        public bool $queueGifsOnAssetUpload = true;
        public bool $queueVideoPostersOnAssetUpload = true;
        public bool $queueVideosOnAssetUpload = true;
        public int|string $audioQueueDelaySeconds = 31;
        public int|string $gifQueueDelaySeconds = 23;
        public int|string $videoQueueDelaySeconds = '$VIDEO_UPLOAD_DELAY';
        public array $queuedAudioOptions = ['audioEncoder' => 'mp3', 'audioBitRate' => '192k'];
        public array $queuedGifOptions = ['width' => 640, 'frameRate' => 12];
        public array $queuedVideoOptions = ['videoEncoder' => 'webm', 'width' => 1280];
    }
}

namespace nystudio107\transcoder\services {
    trait ServicesTrait
    {
        public object $transcode;
        public object $vite;
    }
}

namespace nystudio107\transcoder\variables {
    final class TranscoderVariable
    {
    }
}

namespace {
    use craft\elements\Asset;
    use craft\events\ModelEvent;
    use nystudio107\transcoder\jobs\EncodeAudio;
    use nystudio107\transcoder\jobs\EncodeGif;
    use nystudio107\transcoder\jobs\EncodeVideo;
    use nystudio107\transcoder\jobs\GenerateVideoPosters;
    use nystudio107\transcoder\models\Settings;
    use nystudio107\transcoder\Transcoder;
    use yii\base\Event;

    require dirname(__DIR__) . '/src/jobs/EncodeAudio.php';
    require dirname(__DIR__) . '/src/jobs/EncodeGif.php';
    require dirname(__DIR__) . '/src/jobs/EncodeVideo.php';
    require dirname(__DIR__) . '/src/jobs/GenerateVideoPosters.php';
    require dirname(__DIR__) . '/src/Transcoder.php';

    final class HarnessQueue
    {
        /** @var array<int, array{delay: int, job: object}> */
        public array $pushes = [];
        public bool $failPush = false;

        private int $nextDelay = 0;

        public function delay(int $seconds): self
        {
            $this->nextDelay = $seconds;

            return $this;
        }

        public function push(object $job): ?string
        {
            $delay = $this->nextDelay;
            $this->nextDelay = 0;
            if ($this->failPush) {
                return null;
            }

            $this->pushes[] = [
                'delay' => $delay,
                'job' => $job,
            ];

            return 'job-' . count($this->pushes);
        }

        public function reset(): void
        {
            $this->pushes = [];
            $this->failPush = false;
            $this->nextDelay = 0;
        }
    }

    final class HarnessRequest
    {
        public bool $isCpRequest = false;

        public function getIsConsoleRequest(): bool
        {
            return false;
        }

        public function getIsSiteRequest(): bool
        {
            return false;
        }
    }

    final class HarnessApp
    {
        public function __construct(
            private readonly HarnessQueue $queue,
            private readonly HarnessRequest $request,
        ) {
        }

        public function getQueue(): HarnessQueue
        {
            return $this->queue;
        }

        public function getRequest(): HarnessRequest
        {
            return $this->request;
        }
    }

    final class HarnessTranscode
    {
        public function __construct(private readonly Settings $settings)
        {
        }

        public function shouldQueueStandaloneVideoPostersOnUpload(): bool
        {
            return $this->settings->enableVideoPosters
                && $this->settings->queueVideoPostersOnAssetUpload
                && !$this->settings->queueVideosOnAssetUpload;
        }

        public function isTemporaryUploadAsset(Asset $asset): bool
        {
            return $asset->temporary;
        }
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $message
                . "\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true)
            );
        }
    }

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function triggerUpload(Asset $asset, bool $isNew = true): void
    {
        Event::trigger(Asset::class, Asset::EVENT_AFTER_SAVE, new ModelEvent([
            'sender' => $asset,
            'isNew' => $isNew,
        ]));
    }

    function assertPush(
        HarnessQueue $queue,
        string $expectedClass,
        int $expectedDelay,
        int $expectedAssetId,
        ?string $optionsProperty = null,
        ?array $expectedOptions = null,
    ): object {
        assertSameValue(1, count($queue->pushes), 'Exactly one queue job should be pushed.');
        $push = $queue->pushes[0];
        assertSameValue($expectedClass, $push['job']::class, 'The upload routed to the wrong queue job.');
        assertSameValue($expectedDelay, $push['delay'], 'The configured queue delay was not preserved.');
        assertSameValue($expectedAssetId, $push['job']->assetId, 'The Asset ID was not preserved.');
        if ($optionsProperty !== null) {
            assertSameValue(
                $expectedOptions,
                $push['job']->{$optionsProperty},
                'The configured encode options were not preserved.'
            );
        }

        return $push['job'];
    }

    putenv('VIDEO_UPLOAD_DELAY=17');
    $queue = new HarnessQueue();
    $settings = new Settings();
    Craft::$app = new HarnessApp($queue, new HarnessRequest());
    $plugin = new Transcoder(['settings' => $settings]);
    $plugin->transcode = new HarnessTranscode($settings);
    $plugin->vite = (object)[];
    $plugin->init();

    try {
        triggerUpload(new Asset(100, 'existing.mp4'), false);
        assertSameValue([], $queue->pushes, 'Saving an existing Asset must not queue upload work.');

        $queue->reset();
        triggerUpload(new Asset(101, 'feature.MP4'));
        assertPush(
            $queue,
            EncodeVideo::class,
            17,
            101,
            'videoOptions',
            $settings->queuedVideoOptions
        );
        assertTrue(
            !$queue->pushes[0]['job'] instanceof GenerateVideoPosters,
            'Video encoding must not also queue a standalone poster job.'
        );

        $queue->reset();
        $temporaryUpload = new Asset(106, 'temporary.mp4', true);
        triggerUpload($temporaryUpload);
        assertSameValue([], $queue->pushes, 'A temporary first save must not race the final Asset move.');
        $temporaryUpload->temporary = false;
        triggerUpload($temporaryUpload, false);
        assertPush(
            $queue,
            EncodeVideo::class,
            17,
            106,
            'videoOptions',
            $settings->queuedVideoOptions
        );

        $queue->reset();
        triggerUpload(new Asset(102, 'animation.GIF'));
        assertPush(
            $queue,
            EncodeGif::class,
            23,
            102,
            'gifOptions',
            $settings->queuedGifOptions
        );

        $queue->reset();
        triggerUpload(new Asset(103, 'podcast.mp3'));
        assertPush(
            $queue,
            EncodeAudio::class,
            31,
            103,
            'audioOptions',
            $settings->queuedAudioOptions
        );

        $queue->reset();
        $settings->queueVideosOnAssetUpload = false;
        triggerUpload(new Asset(104, 'poster-only.mov'));
        assertPush($queue, GenerateVideoPosters::class, 17, 104);

        $queue->reset();
        Craft::$logs = [];
        $queue->failPush = true;
        triggerUpload(new Asset(105, 'failed.webm'));
        assertSameValue([], $queue->pushes, 'A failed queue push must not be recorded as successful.');
        assertTrue(
            count(array_filter(
                Craft::$logs,
                static fn(array $log): bool => $log['level'] === 'error'
                    && str_contains($log['message'], 'Unable to queue video poster asset #105 for encoding.')
            )) === 1,
            'A null queue job ID should be logged as an error.'
        );

        echo "upload queue harness: OK\n";
    } finally {
        putenv('VIDEO_UPLOAD_DELAY');
    }
}
