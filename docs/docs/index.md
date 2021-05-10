[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/quality-score.png?b=v1)](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/?branch=v1) [![Code Coverage](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/coverage.png?b=v1)](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/?branch=v1) [![Build Status](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/build.png?b=v1)](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/build-status/v1) [![Code Intelligence Status](https://scrutinizer-ci.com/g/nystudio107/craft-transcoder/badges/code-intelligence.svg?b=v1)](https://scrutinizer-ci.com/code-intelligence)

# Transcoder plugin for Craft CMS 3.x

Transcode video & audio files to various formats, and provide video thumbnails

![Screenshot](./resources/img/plugin-banner.jpg)

Related: [Transcoder for Craft 2.x](https://github.com/nystudio107/transcoder)

**Note**: _The license fee for this plugin is $59.00 via the Craft Plugin Store._

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Installation

To install Transcoder, follow these steps:

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require nystudio107/craft-transcoder

3. Install the plugin via `./craft install/plugin transcoder` via the CLI, or in the Control Panel, go to Settings → Plugins and click the “Install” button for Transcoder.

You can also install Transcoder via the **Plugin Store** in the Craft Control Panel.

Transcoder works on Craft 3.x.

You will also need [ffmpeg](https://ffmpeg.org/) installed for Transcoder to work. On Ubuntu 16.04, you can do just:

```bash
    sudo apt-get update
    sudo apt-get install ffmpeg
```

To install `ffmpeg` on Centos 6/7, you can follow the guide [How to Install FFmpeg on CentOS](https://www.vultr.com/docs/how-to-install-ffmpeg-on-centos)

If you have managed hosting, contact your sysadmin to get `ffmpeg` installed.

Brought to you by [nystudio107](https://nystudio107.com)
