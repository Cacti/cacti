# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

For a full diff see [`1.2.0...main`][1.2.0...main].

## [`1.2.0`][1.2.0]

For a full diff see [`1.1.1...1.2.0`][1.1.1...1.2.0].

### Added

- Added support for detecting the presence of an agent when the `COPILOT_CLI` or `PI_CODING_AGENT` environment variables are set ([#10]), by [@raphaelstolt]

## [`1.1.1`][1.1.1]

For a full diff see [`1.1.0...1.1.1`][1.1.0...1.1.1].

### Fixed

- Allowed installation on PHP 8.6 ([#4]), by [@localheinz]

## [`1.1.0`][1.1.0]

For a full diff see [`1.0.1...1.1.0`][1.0.1...1.1.0].

### Added

- Added support for detecting the presence of an agent when the `CURSOR_EXTENSION_HOST_ROLE` environment variable is set ([#2]), by [@localheinz]

## [`1.0.1`][1.0.1]

For a full diff see [`2655ea1...1.0.1`][2655ea1...1.0.1].

### Added

- Added `Detector` ([#1]), by [@localheinz]

[1.0.1]: https://github.com/ergebnis/agent-detector/releases/tag/1.0.1
[1.1.0]: https://github.com/ergebnis/agent-detector/releases/tag/1.1.0
[1.1.1]: https://github.com/ergebnis/agent-detector/releases/tag/1.1.1
[1.2.0]: https://github.com/ergebnis/agent-detector/releases/tag/1.2.0

[2655ea1...1.0.1]: https://github.com/ergebnis/agent-detector/compare/2655ea1...1.0.1
[1.0.1...1.1.0]: https://github.com/ergebnis/agent-detector/compare/1.0.1...1.1.0
[1.1.0...1.1.1]: https://github.com/ergebnis/agent-detector/compare/1.1.0...1.1.1
[1.1.1...1.2.0]: https://github.com/ergebnis/agent-detector/compare/1.1.1...1.2.0
[1.2.0...main]: https://github.com/ergebnis/agent-detector/compare/1.2.0...main

[#1]: https://github.com/ergebnis/agent-detector/pull/1
[#2]: https://github.com/ergebnis/agent-detector/pull/2
[#4]: https://github.com/ergebnis/agent-detector/pull/4
[#10]: https://github.com/ergebnis/agent-detector/pull/10

[@localheinz]: https://github.com/localheinz
[@raphaelstolt]: https://github.com/raphaelstolt
