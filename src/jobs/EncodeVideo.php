<?php

namespace nystudio107\transcoder\jobs;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\queue\BaseJob;
use nystudio107\transcoder\Transcoder;
use RuntimeException;
use Throwable;

/**
 * Encodes an uploaded video inside Craft's queue worker.
 */
class EncodeVideo extends BaseJob
{
    public ?int $assetId = null;

    public array $videoOptions = [];

    /** Current attempt number, starting at one. */
    public int $attempt = 1;

    /** Number of retries after the initial attempt; null uses the plugin setting. */
    public int|string|null $maxRetries = null;

    /** Seconds before the next attempt; null uses the plugin setting. */
    public int|string|null $retryDelaySeconds = null;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset instanceof Asset) {
            throw new RuntimeException("Unable to find video asset #{$this->assetId}.");
        }

        try {
            Craft::info(
                "Starting queued video encoding for asset #{$this->assetId}, attempt {$this->attempt}.",
                __METHOD__
            );
            $executed = Transcoder::$plugin->transcode->runVideoAssetWork($asset, function() use ($asset): void {
                $url = Transcoder::$plugin->transcode->getVideoUrl(
                    $asset,
                    $this->videoOptions,
                    true,
                    true
                );

                if ($url === '') {
                    throw new RuntimeException("Video encoding failed for asset #{$this->assetId}.");
                }

                Craft::info("Encoded video asset #{$this->assetId}: $url", __METHOD__);

                if (Transcoder::$plugin->getSettings()->enableVideoPosters) {
                    $posters = Transcoder::$plugin->transcode->generateVideoPosters($asset);
                    if (in_array('', $posters, true)) {
                        throw new RuntimeException("Video poster generation failed for asset #{$this->assetId}.");
                    }
                }
            });

            if (!$executed) {
                throw new RuntimeException("Video asset #{$this->assetId} is already being processed.");
            }
        } catch (Throwable $e) {
            if ($this->retryLater($queue, $asset, $e)) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Queue the next attempt, leaving the final failure visible to Craft.
     */
    protected function retryLater(mixed $queue, Asset $asset, Throwable $error): bool
    {
        $settings = Transcoder::$plugin->getSettings();
        $maxRetries = max(0, (int)App::parseEnv((string)($this->maxRetries ?? $settings->videoEncodeMaxRetries)));
        if ($this->attempt > $maxRetries) {
            return false;
        }

        $delay = max(
            0,
            (int)App::parseEnv((string)($this->retryDelaySeconds ?? $settings->videoEncodeRetryDelaySeconds))
        );
        $nextAttempt = $this->attempt + 1;
        $totalAttempts = $maxRetries + 1;
        $message = Craft::t('transcoder', 'Retrying video encode attempt {attempt} of {total} in {seconds}s', [
            'attempt' => $nextAttempt,
            'total' => $totalAttempts,
            'seconds' => $delay,
        ]);

        try {
            $queueService = Craft::$app->getQueue();
            if ($delay > 0) {
                $queueService = $queueService->delay($delay);
            }
            $jobId = $queueService->push(new self([
                'assetId' => (int)$asset->id,
                'videoOptions' => $this->videoOptions,
                'attempt' => $nextAttempt,
                'maxRetries' => $maxRetries,
                'retryDelaySeconds' => $delay,
            ]));
        } catch (Throwable $queueError) {
            Craft::error(
                "Unable to queue video encoding retry for asset #{$asset->id}: {$queueError->getMessage()}",
                __METHOD__
            );
            return false;
        }

        if ($jobId === null) {
            Craft::error("Unable to queue video encoding retry for asset #{$asset->id}.", __METHOD__);
            return false;
        }

        Craft::warning(
            "$message; asset #{$asset->id}; retry job ID: $jobId; previous error: {$error->getMessage()}",
            __METHOD__
        );
        $this->setProgress($queue, 1, $message);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $description = "Encoding video asset #{$this->assetId}";
        if ($this->attempt > 1) {
            $description .= " (attempt {$this->attempt})";
        }

        return $description;
    }
}
