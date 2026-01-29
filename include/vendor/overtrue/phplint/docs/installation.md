# Installation

1. [Requirements](#requirements)
2. [PHAR](#phar)
3. [Docker](#docker) 
4. [Phive](#phive)
5. [Composer](#composer)

## Requirements

| Version | Status                                    | Requirements   |
|:--------|:------------------------------------------|:---------------|
| **9.x** | **Active development**                    | **PHP >= 8.1** |
| 6.x     | End Of Life                               | PHP >= 8.2     |
| 5.x     | End Of Life                               | PHP >= 8.1     |
| 4.x     | End Of Life                               | PHP >= 8.0     |
| 3.x     | End Of Life                               | PHP >= 7.4     |

## PHAR

The preferred method of installation is to use the PHPLint PHAR which can be downloaded from the most recent
[Github Release][releases]. This method ensures you will not have any dependency conflict issue.

**IMPORTANT** : Embedded with Composer dependencies that are PHP 8.2 compatible !

## Docker

You can install `phplint` with [Docker][docker]

```shell
docker pull overtrue/phplint:latest
```

## Phive

You can install `phplint` globally with [Phive][phive]

```shell
phive install overtrue/phplint --force-accept-unsigned
```

To upgrade global `phplint` use the following command:

```shell
phive update overtrue/phplint --force-accept-unsigned
```

You can also install `phplint` locally to your project with [Phive][phive] and configuration file `.phive/phars.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phive xmlns="https://phar.io/phive">
    <phar name="overtrue/phplint" version="^9.6" copy="false" />
</phive>
```

```shell
phive install --force-accept-unsigned
```

## Composer

You can install `phplint` with [Composer][composer]

```shell
composer global require overtrue/phplint ^9.6
```

If you cannot install it because of a dependency conflict, or you prefer to install it for your project, we recommend
you to take a look at [bamarni/composer-bin-plugin][bamarni/composer-bin-plugin]. Example:

```shell
composer require --dev bamarni/composer-bin-plugin
composer bin phplint require --dev overtrue/phplint

vendor/bin/phplint
```

[releases]: https://github.com/overtrue/phplint/releases
[composer]: https://getcomposer.org
[bamarni/composer-bin-plugin]: https://github.com/bamarni/composer-bin-plugin
[phive]: https://github.com/phar-io/phive
[docker]: https://docs.docker.com/get-docker/
