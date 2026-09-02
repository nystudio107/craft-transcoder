<?php

namespace nystudio107\transcoder\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use nystudio107\transcoder\Transcoder;
use RuntimeException;

/**
 * Invalidates managed derivatives after an integration replaces a video asset.
 */
class RefreshVideoAsset extends BaseJob
{
    private const RETRY_DELAY_SECONDS = 15;

    public ?int $assetId = null;

    public int $attempt = 1;

    public int $maxAttempts = 372;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset instanceof Asset) {
            Craft::warning("Video refresh skipped missing asset #{$this->assetId}.", __METHOD__);
            return;
        }

        $result = Transcoder::$plugin->transcode->performVideoAssetRefresh($asset);
        if (!empty($result['active'])) {
            if ($this->attempt >= max(1, $this->maxAttempts)) {
                throw new RuntimeException('Video refresh timed out waiting for current encoding to finish.');
            }

            $retryJobId = Craft::$app->getQueue()->delay(self::RETRY_DELAY_SECONDS)->push(new self([
                'assetId' => $this->assetId,
                'attempt' => $this->attempt + 1,
                'maxAttempts' => $this->maxAttempts,
            ]));
            if ($retryJobId === null) {
                throw new RuntimeException("Unable to requeue video refresh for asset #{$this->assetId}.");
            }

            Craft::info(
                "Video refresh for asset #{$this->assetId} is waiting; retry job ID: $retryJobId",
                __METHOD__
            );
            $this->setProgress($queue, 1, Craft::t('transcoder', 'Waiting for current video encoding to finish'));
            return;
        }

        if (empty($result['queued']) || empty($result['jobId'])) {
            throw new RuntimeException("Video refresh did not queue replacement encoding for asset #{$this->assetId}.");
        }

        $this->setProgress($queue, 1, Craft::t('transcoder', 'Video refresh complete'));
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('transcoder', 'Refreshing video asset #{id}', [
            'id' => $this->assetId ?? 'unknown',
        ]);
    }
}
