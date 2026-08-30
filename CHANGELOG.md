# Transcoder Changelog

## 5.1.0 - UNRELEASED

### Changed
* Organize media workflow settings into video queue, poster, and watermark tabs in the Craft control panel.
* Use Craft 5 autosuggest fields for queue, subfolder, and watermark values, including environment-variable support.

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
* Retry failed queued video encodes with configurable attempt and delay settings.

### Fixed

* Use one byte-identical output-path calculation for replacement cleanup and queued encoding.
* Fail refresh jobs when Craft cannot queue replacement encoding instead of reporting completion.
* Add modification-time cache versions to generated video and poster URLs after regeneration.
* Preserve configured video output subfolders when `getVideoUrl()` receives a string URL or path.
* Preserve Craft 5 volume subpaths while resolving local source assets.
* Keep automatic Control Panel video thumbnails out of temporary upload storage and in the asset's final subfolder.
* Defer upload workflow queueing until Craft has moved field uploads from temporary storage into their final folder.
* Forward the optional `generate` argument from Twig's `getVideoThumbnailUrl()` helper.
* Keep poster format handles, duration-clamped seek times, and black-bar generation flags out of thumbnail filenames so refresh cleanup, queued posters, and equivalent Twig requests reuse the same canonical file.
* Reject and remove zero-byte video, GIF, audio, and poster derivatives instead of accepting them as completed jobs.
* Scope internal encoder lock files to the full output path so identical filenames in different Asset subfolders do not collide.
* Correct legacy `transcoderUrl` migration and remove validation for the obsolete singular `transcoderPath` property.

## 5.0.1 - 2026.09.01
### Changed
* Add in the volume's subpath, if any, to the path to the asset ([#78](https://github.com/nystudio107/craft-transcoder/issues/78))
* Remove the `bufsize` parameter entirely from the FFMPEG default command, which was preventing `WebM` files from being generated properly ([#72](https://github.com/nystudio107/craft-transcoder/issues/72))

## 5.0.0 - 2024.09.30
### Added
- Initial stable release for Craft CMS 5
