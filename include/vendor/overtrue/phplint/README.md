<!--suppress HtmlDeprecatedAttribute -->
<h1 align="center">PHPLint</h1>

<p align="center">`phplint` is a tool that can speed up linting of php files by running several lint processes at once.</p>

[![Release Status](https://github.com/overtrue/phplint/actions/workflows/release.yml/badge.svg)](https://github.com/overtrue/phplint/actions/workflows/release.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/overtrue/phplint)](https://packagist.org/packages/overtrue/phplint)
[![Total Downloads](https://poser.pugx.org/overtrue/phplint/downloads.svg)](https://packagist.org/packages/overtrue/phplint) 
[![License](https://poser.pugx.org/overtrue/phplint/license.svg)](https://packagist.org/packages/overtrue/phplint)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2Fovertrue%2Fphplint.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2Fovertrue%2Fphplint?ref=badge_shield)


| Version | Status                 | Requirements   |
|:--------|:-----------------------|:---------------|
| **9.x** | **Active development** | **PHP >= 8.1** |
| 9.6     | Active support         | PHP >= 8.1     |
| 9.5     | Active support         | PHP >= 8.1     |
| 9.4     | End Of Life            | PHP >= 8.1     |
| 9.3     | End Of Life            | PHP >= 8.1     |
| 9.2     | End Of Life            | PHP >= 8.1     |
| 9.1     | End Of Life            | PHP >= 8.1     |
| 9.0     | End Of Life            | PHP >= 8.0     |
| 6.x     | End Of Life            | PHP >= 8.2     |
| 5.x     | End Of Life            | PHP >= 8.1     |
| 4.x     | End Of Life            | PHP >= 8.0     |
| 3.x     | End Of Life            | PHP >= 7.4     |

**NOTE** if you have an older version of PHP lower than 8.0, we recommend to use the latest version [3.4.0](https://github.com/overtrue/phplint/releases/tag/3.4.0)

Major version 9.0 is a full code rewrites that have the same source code (`main` branch) for all PHP 8.x versions (`4.x`, `5.x` or `6.x`),
with a lot of improvement, full documentation, and full unit code tested. 


## Table of Contents

1. [Installation](docs/installation.md#installation)
   1. [Requirements](docs/installation.md#requirements)
   1. [PHAR](docs/installation.md#phar)
   1. [Docker](docs/installation.md#docker)
   1. [Phive](docs/installation.md#phive)
   1. [Composer](docs/installation.md#composer)
1. [Usage](docs/README.md#usage)
1. [Configuration](docs/configuration.md#configuration)
1. [Upgrading](docs/upgrading.md#upgrading) 
1. [Contributing](docs/contributing.md#contributing)
1. [Architecture](docs/architecture/README.md#architecture)

## :heart: Sponsor me 

[![Sponsor me](https://github.com/overtrue/overtrue/blob/master/sponsor-me.svg?raw=true)](https://github.com/sponsors/overtrue)

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)

## Project supported by JetBrains

Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)


## PHP 扩展包开发

> 想知道如何从零开始构建 PHP 扩展包？
>
> 请关注我的实战课程，我会在此课程中分享一些扩展开发经验 —— [《PHP 扩展包实战教程 - 从入门到发布》](https://learnku.com/courses/creating-package)

## License

MIT
