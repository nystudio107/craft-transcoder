---
title: Configuring Transcoder
description: Configuring Transcoder documentation for the Transcoder plugin. The Transcoder plugin allows you to transcode video & audio files to various formats, and provide video thumbnails
---
# Configuring Transcoder

The only configuration for Transcoder is in the `config.php` file, which is a multi-environment friendly way to store the default settings.  Don’t edit this file, instead copy it to `craft/config` as `transcoder.php` and make your changes there.

You will also need [ffmpeg](https://ffmpeg.org/) installed for Transcoder to work. On Ubuntu 16.04, you can do just:

```bash
    sudo apt-get update
    sudo apt-get install ffmpeg
```

To install `ffmpeg` on Centos 6/7, you can follow the guide [How to Install FFmpeg on CentOS](https://www.vultr.com/docs/how-to-install-ffmpeg-on-centos)

If you have managed hosting, contact your sysadmin to get `ffmpeg` installed.

## Video queue and filenames

Video uploads can be encoded by Craft’s queue instead of starting ffmpeg during a web request:

```php
return [
    'queueVideosOnAssetUpload' => true,
    'videoQueueDelaySeconds' => 5,
    'queuedVideoOptions' => [
        'videoBitRate' => '1200k',
        'videoFrameRate' => 30,
        'width' => 1280,
        'height' => 720,
    ],
];
```

A working Craft queue runner is required. The queue job waits for ffmpeg to finish, so Craft can report failures and retry the job through the configured queue driver.

`videoFilenameStrategy` supports:

- `options` (default): preserves Transcoder’s original parameterized filenames. Output-affecting options such as `videoBitRate` remain part of the filename.
- `source`: uses the source asset name plus the encoder suffix, producing one stable output name per source asset.

### Subfolders for URL inputs

Passing the actual Craft `Asset` to `getVideoUrl()` is preferred. With `createSubfolders` enabled, Transcoder uses the Asset’s `folderPath` for both the encoded filesystem path and public URL.

When an integration can only pass a string URL or path, configure `subfolderUrlSegment` with the one-based path segment that contains the desired output folder. For example, segment `3` extracts `197915` from `/content/videos/197915/video.mp4`:

```php
return [
    'createSubfolders' => true,
    'subfolderUrlSegment' => 3,
];
```

Leave `subfolderUrlSegment` as `false` (the default) when string inputs should use the base video output directory. Transcoder normalizes path and URL separators, so configured transcoder paths no longer depend on a trailing slash for video output.

## Watermarks

Watermarking accepts a local path, Yii alias, environment value, or public image URL:

```php
return [
    'enableVideoWatermark' => true,
    'videoWatermarkPath' => '@webroot/assets/watermark.png',
    'videoWatermarkWidth' => 180,
    'videoWatermarkPosition' => 'bottom-right',
    'videoWatermarkPadding' => 24,
    'videoWatermarkOpacity' => 80,
];
```

Watermark settings are included in option-based output filenames through a short fingerprint. Changing the watermark therefore creates a new option-based output instead of reusing an incompatible encode.

## Video posters

Configured poster formats are generated after a queued video encode:

```php
return [
    'enableVideoPosters' => true,
    'preventVideoPosterBlackBars' => true,
    'videoPosterFormats' => [
        '16_9' => [
            'width' => 800,
            'height' => 450,
            'timeInSecs' => 3,
        ],
        'square' => [
            'width' => 800,
            'height' => 800,
            'timeInSecs' => 1,
        ],
    ],
];
```

When `preventVideoPosterBlackBars` is enabled, the video frame is fitted over a blurred cover version of the same frame. Poster timestamps are clamped to the source duration for short videos.

Brought to you by [nystudio107](https://nystudio107.com)
