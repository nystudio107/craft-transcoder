<?php

namespace nystudio107\transcoder\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use nystudio107\transcoder\Transcoder;
use RuntimeException;

/**
 * Encodes an uploaded GIF inside Craft's queue worker.
 */
class EncodeGif extends BaseJob
{
    public ?int $assetId = null;

    public array $gifOptions = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset instanceof Asset || strtolower(pathinfo($asset->filename, PATHINFO_EXTENSION)) !== 'gif') {
            throw new RuntimeException("Unable to find GIF asset #{$this->assetId}.");
        }

        Craft::info("Starting queued GIF encoding for asset #{$this->assetId}.", __METHOD__);
        $executed = Transcoder::$plugin->transcode->runGifAssetWork($asset, function() use ($asset): void {
            $url = Transcoder::$plugin->transcode->getGifUrl($asset, $this->gifOptions, true);
            if (!is_string($url) || $url === '') {
                throw new RuntimeException("GIF encoding failed for asset #{$this->assetId}.");
            }

            Craft::info("Encoded GIF asset #{$this->assetId}: $url", __METHOD__);
        });

        if (!$executed) {
            throw new RuntimeException("GIF asset #{$this->assetId} is already being processed.");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return "Encoding GIF asset #{$this->assetId}";
    }
}
