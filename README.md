[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/quality-score.png?b=v1)](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/?branch=v1) [![Code Coverage](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/coverage.png?b=v1)](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/?branch=v1) [![Build Status](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/build.png?b=v1)](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/build-status/v1) [![Code Intelligence Status](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/code-intelligence.svg?b=v1)](https://scrutinizer-ci.com/code-intelligence)

# Transcoder plugin for Craft CMS 4.x

Transcode video & audio files to various formats, and provide video thumbnails

![Screenshot](./docs/docs/resources/img/plugin-banner.jpg)

**Note**: _The license fee for this plugin is $59.00 via the Craft Plugin Store._

## Requirements

This plugin requires Craft CMS 4.0.0 or later.

## Installation

To install Transcoder, follow these steps:

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require nystudio107/craft-transcoder

3. Install the plugin via `./craft install/plugin transcoder` via the CLI, or in the Control Panel, go to Settings → Plugins and click the “Install” button for Transcoder.

You can also install Transcoder via the **Plugin Store** in the Craft Control Panel.

Transcoder works on Craft 4.x.

You will also need [ffmpeg](https://ffmpeg.org/) installed for Transcoder to work. On Ubuntu 16.04, you can do just:

    sudo apt-get update
    sudo apt-get install ffmpeg

To install `ffmpeg` on Centos 6/7, you can follow the guide [How to Install FFmpeg on CentOS](https://www.vultr.com/docs/how-to-install-ffmpeg-on-centos)

If you have managed hosting, contact your sysadmin to get `ffmpeg` installed.

## Documentation

Click here -> [Transcoder Documentation](https://nystudio107.com/plugins/transcoder/documentation)

## Transcoder Roadmap

Some things to do, and ideas for potential features:

* Add a console command for doing encodings via console
* Figure out a way to reliably do multi-pass video encoding
* Add audio normalization via `loudnorm` http://k.ylo.ph/2016/04/04/loudnorm.html

Brought to you by [nystudio107](https://nystudio107.com)
