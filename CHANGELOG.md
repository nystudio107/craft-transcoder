# Transcoder Changelog

## 4.0.3 - UNRELEASED
### Changed
* Remove the `bufsize` parameter entirely from the FFMPEG default command, which was preventing `WebM` files from being generated properly ([#72](https://github.com/nystudio107/craft-transcoder/issues/72))

### Changed

* Organize video settings into separate queue, posters, and watermark tabs in the Craft control panel.
* Use Craft autosuggest fields for queue, subfolder, and watermark values, including environment-variable support.

### Added

* Queue video encoding when new video assets are uploaded, with a configurable delay.
* Queue GIF encoding when new GIF assets are uploaded, with a configurable delay.
* Queue audio encoding when new audio assets are uploaded, with a configurable delay.
* Queue configured video poster formats on asset upload without requiring full video encoding.
* Add source-asset and encoding-options video filename strategies while preserving bitrate-based filenames by default.
* Add configurable video watermark overlays.
* Generate configured video poster formats after queued encodes.
* Add blurred-background poster fitting to prevent black bars.
* Add `refreshVideoAsset()` for integrations that intentionally replace a video asset’s source file.

### Fixed

* Use one byte-identical output-path calculation for replacement cleanup and queued encoding.
* Fail refresh jobs when Craft cannot queue replacement encoding instead of reporting completion.
* Add modification-time cache versions to generated video and poster URLs after regeneration.
* Preserve configured video output subfolders when `getVideoUrl()` receives a string URL or path.
* Keep automatic Control Panel video thumbnails out of temporary upload storage and in the Asset's final subfolder.
* Forward the optional `generate` argument from Twig's `getVideoThumbnailUrl()` helper.

## 4.0.2 - 2024.09.30
## Added
* Add `phpstan` and `ecs` code linting
* Add `code-analysis.yaml` GitHub action

## 4.0.1 - 2023.04.20
### Changed
* Updated the docs to use VitePress `^1.0.0-alpha.29`
* Allow for versioning of the docs

### Fixed
* Fix Asset Volume file system access for Craft 4 ([#67](https://github.com/nystudio107/craft-transcoder/pull/67/files))
* Fix progress URLs and send application/json response ([#68](https://github.com/nystudio107/craft-transcoder/pull/68))
* Fix asset thumbnails ([#69](https://github.com/nystudio107/craft-transcoder/pull/69))
* Fix GIF filename generation ([#70](https://github.com/nystudio107/craft-transcoder/pull/70))

## 4.0.0 - 2022.09.20
### Changed
* Pinned `vitepress` to `^0.22.4` pending official `1.0.0` release
* Add comments to `Makefile`s for Fig
* Use Vite `^3.1.0` & rebuild assets
* Add `allow-plugins` to `composer.json` to allow CI tests to function

### Fixed
* Remove reference to now missing `DefineAssetThumbUrlEvent::generate` property 
* Change reference to now renamed `DefineAssetThumbUrlEvent::path` property

## 4.0.0-beta.6 - 2022.04.11
### Fixed
* Fixed method signature for `Transcode::getFileInfo()` so that an Asset object can be passed into it

## 4.0.0-beta.5 - 2022.04.09
### Changed
* Added `synchronous` & `stripMetadata` to the parameters that should be excluded from the generated file name

## 4.0.0-beta.4 - 2022.04.08
### Fixed
* Fixed incorrect return types in `TranscoderVariable` that could cause exceptions to be thrown

## 4.0.0-beta.3 - 2022.03.17

### Changed

* Refactored to use `Assets::EVENT_DEFINE_THUMB_URL` now available in Craft `4.0.0-beta.2`

## 4.0.0-beta.2 - 2022.03.04

### Fixed

* Updated types for Craft CMS `4.0.0-alpha.1` via Rector

## 4.0.0-beta.1 - 2022.02.27

### Added

* Initial Craft CMS 4 compatibility
