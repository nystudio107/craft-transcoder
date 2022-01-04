# Transcoder Changelog

## 1.2.20 - UNRELEASED
### Changed
* Switch to Node 16 via `16-alpine` Docker tag by default
* Update to Tailwind CSS `^3.0.0`
* Changed buildchain to Vite from webpack 5

### Fixed
* Use `${CURDIR}` instead of `pwd` to be cross-platform compatible with Windows WSL2

## 1.2.19 - 2021.05.16
### Changed
* Refactor to better directory structure
* Use Textlint for the documentation

## 1.2.18 - 2021.05.14
### Changed
* Updated to work with `craft-vite-plugin` version `^1.0.4`

## 1.2.17 - 2021.05.09
### Changed
* Switched buildchain to Vite & `craft-vite-plugin`
* Switched documentation system to VitePress

## 1.2.16 - 2021.04.06
### Added
* Added `make update` to update NPM packages
* Added `make update-clean` to completely remove `node_modules/`, then update NPM packages

### Changed
* More consistent `makefile` build commands
* Use Tailwind CSS `^2.1.0` with JIT
* Move settings from the `composer.json` “extra” to the plugin main class
* Move the manifest service registration to the constructor
* Remove deprecated ManifestController

## 1.2.15 - 2021.03.03
### Changed
* Dockerized the buildchain, using `craft-plugin-manifest` for the webpack HMR bridge

## 1.2.14 - 2021.01.03
### Changed
* Changed how we detect whether the transcoding processing is running, so it will work with Alpine Linux

## 1.2.13 - 2020.12.21 [CRITICAL]
### Security
* Added a `$enableDownloadFileEndpoint` settings/config option (set to `false` by default) to control whether the download files action is publicly accessible
* The download files action now strips any relative paths from the incoming request
* The download files action now restricts downloads to Craft's [allowedFileExtensions](https://craftcms.com/docs/3.x/config/config-settings.html#allowedfileextensions)

### Changed
* Moved the CSS/JS buildchain over to webpack 5

## 1.2.12 - 2020.04.06
### Added
* Added `seekInSecs` option to audio encoding options

### Changed
* Updated to latest npm dependencies via `npm audit fix` for both the primary app and the docs

## 1.2.11 - 2020.03.11
### Added
* Transcoder now requires Craft CMS 3.1.0 or later
* Both aliases and environment variables are now supported where previously only aliases were

### Fixed
* Only swap in a thumbnail for videos if a thumbnail is successfully returned

## 1.2.10 - 2020.02.25
### Added
* Added `-vn` flag for audio transcoding to remove video tracks on transcoded audio

## 1.2.9 - 2020.01.27
### Fixed
* Fixed an issue if `ffprobe` isn't installed
* Handle the case of empty or malformed status data from `ffprobe` better

## 1.2.8 - 2019.11.12
### Changed
* Fixed more issues with the `synchronous` option

## 1.2.7 - 2019.11.12
### Changed
* Fixed issues with the `synchronous` and `stripMetadata` options

## 1.2.6 - 2019.11.11
### Added
* Added trimming to audio transcoding

### Changed
* Updated to latest npm dependencies via `npm audit fix`
* Changed `.first()` → `.one()` in the docs

## 1.2.5 - 2019.05.23
### Changed
* Updated build system

## 1.2.4 - 2019.04.22
### Changed
* Updated Twig namespacing to be compliant with deprecated class aliases in 2.7.x

## 1.2.3 - 2019.03.20
### Changed
* Allow setting threads in config
* Added a "generate" (bool) parameter to `getVideoUrl()`, just like `getVideoThumbnailUrl()`, to optionally skip encoding
* Added return value false to `getVideoThumbnailUrl()` when `ffmpeg` is executed which prevents a URL is always returned, also in case of `ffmpeg` fails to run/create the thumbnail
* Added new config parameter "createSubfolder" (boolean) to create the same subfolders that are defined in the upload target paths of the asset.
* Added config option to prevent cache clearing
* Fixed an issue where `getFileInfo()` would throw an error if `null` was returned
* Fixed an error where certain types of video streams would cause the encoder to throw an exception

## 1.2.2 - 2018.10.05
### Changed
* Updated build process

## 1.2.1 - 2018.08.23
### Changed
* Fixed namespacing issues

## 1.2.0 - 2018.08.22
### Added
* Added the ability to encode to animated `.gif` files
* Added multiple output paths and URLs for different media types

### Changed
* Moved to a modern webpack build config for the Control Panel
* Added install confetti

## 1.1.3 - 2018.03.02
### Changed
* Fixed deprecation errors from Craft CMS 3 RC13

## 1.1.2 - 2018.02.06
### Changed
* Switched video thumbnail generation to use `EVENT_GET_THUMB_PATH`
* Transcoder now requires Craft CMS 3 RC 9 or later

## 1.1.1 - 2018.02.03
### Changed
* Only generate a thumbnail when we're actually asked to do so via `$generate1`

## 1.1.0 - 2018.02.02
### Added
* Transcoder now supports the transcoding of remote video & audio files
* Added the ability to generate a thumbnail for videos in the Control Panel Assets index

### Changed
* Cleaned up the exception handling

## 1.0.11 - 2018.02.01
### Added
* Renamed the composer package name to `craft-transcoder`

## 1.0.10 - 2018.01.29
### Added
* Added support for Yii2 aliases for `transcoderPath` & `transcoderUrl` settings in `config.php`

### Changed
* Changed the default `config.php` to use `@webroot` and `@web` Yii2 aliases

## 1.0.9 - 2018.01.25
### Changed
* Handle Asset Volumes that use aliases
* Updated DocBlock comments

## 1.0.8 - 2017.12.06
### Changed
* Updated to require craftcms/cms `^3.0.0-RC1`

## 1.0.7 - 2017.08.05
### Changed
* Craft 3 beta 23 compatibility

## 1.0.6 - 2017.07.15
### Changed
* Craft 3 beta 20 compatibility

## 1.0.5 - 2017.03.24
### Changed
* `hasSettings` -> `hasCpSettings` for Craft 3 beta 8 compatibility
* Added Craft 3 beta 8 compatible settings
* Modified config service calls for Craft 3 beta 8

## 1.0.4 - 2017.03.12
### Added
- Added `craft/cms` as a composer dependency
- Added code inspection typehinting for the plugin & services

### Changed
- Code refactor/cleanup

## 1.0.3 - 2017.03.11
### Added
- Use `php-shellcommand` to allow for proper execution on Windows & Unix servers

### Changed
- Minor code cleanup

## 1.0.2 - 2017.03.07
### Added
- Added a summary option to `getFileInfo()`

### Changed
- Refactored the `config.php` options to be more consistent

## 1.0.1 - 2017.03.06
### Added
- Added `height` and `width` options for resizing the videos
- Added an `aspectRatio` option to control how aspect ratio scaling is done
- Added a `letterboxColor` option
- Added a `sharpen` option
- Added the `getFileInfo` variable to extract information from a video/audio file
- The `ffmpeg` progress for video transcoding is now written out to a `.progress` file
- Added a `progress` controller to return video transcoding progress
- Moved all of the default settings out to the `config.php` file
- Added support for multiple video encoding formats
- Added the ability to transcode audio files
- Transcoder caches can be cleared via the ClearCaches utility

### Fixed
- Fixed some issues with the lockfile naming

## 1.0.0 - 2017.03.05
### Added
- Initial release
