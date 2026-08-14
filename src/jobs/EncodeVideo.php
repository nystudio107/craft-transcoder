<?php

namespace nystudio107\transcoder\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use nystudio107\transcoder\Transcoder;
use RuntimeException;

/**
 * Encodes an uploaded video inside Craft's queue worker.
 */
class EncodeVideo extends BaseJob
{
    public ?int $assetId = null;

    public array $videoOptions = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset instanceof Asset) {
            throw new RuntimeException("Unable to find video asset #{$this->assetId}.");
        }

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
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return "Encoding video asset #{$this->assetId}";
    }
}
