<!-- markdownlint-disable MD013 -->
# Configuration

## Table Of Contents

1. [Path](#path)
2. [Exclude](#exclude)
3. [Extensions](#extensions)
4. [Show Warnings](#show-warnings)
5. [Jobs](#jobs)
6. [Cache](#cache)
7. [Cache invalidation](#cache-ttl)
8. [No caching](#no-caching)
9. [Memory limit](#memory-limit)
10. [JSON output](#json-output)
11. [Junit output](#junit-output)
12. [Checkstyle output](#checkstyle-output)
13. [SARIF output](#sarif-output)
14. [Exit Code](#exit-code)

The `phplint` command relies on a configuration file for loading settings. 
If a configuration file is not specified through the `--configuration|-c` option, following file will be used : `.phplint.yml`. 
If no configuration file is found, PHPLint will proceed with the default settings.

## Path

The `path` (`string`|`string[]` default `.`) setting is used to specify where all directories and files to scan should resolve to.

If not specified, the base path used is the current working directory.

## Exclude

The `exclude` (`string[]` default `[]`) setting is a list of directory paths relative to the base `path`.
All files listed inside these paths won't be scanned by PHPLint.

## Extensions

The `extensions` (`string[]` default `[php]`) setting will check only files with selected extensions.

## Show Warnings

Use the `warning` (`bool` default `false`) setting (with `true`) if you want to show PHP Warnings too.
For example:

```php
<?php declare(encoding="utf8");
```
Script above generate `PHP Warning:  declare(encoding=...) ignored because Zend multibyte feature is turned off by settings in /.../tests/fixtures/encoding.php on line 1`.

## Jobs

The `jobs` (`int`|`string` default `5`) setting declare the maximum number of paralleled jobs to run (depend on your computer infrastructure).

## Cache

The `cache` (`string` default `.phplint.cache`) setting identify the local directory where to save lint results.
Default behavior will store results as regular files in a collection of directories on a locally mounted filesystem.

This setting is used only when the `cache-adapter` is set to `Filesystem` value (string is case-sensitive).

If you don't want to store results in a sub-folder of your working directory, please specify an absolute path. 
For example: `/tmp/my-phplint-cache`

> [!NOTE]
> 
> if you give an empty `cache` setting value, default directory used will be `/tmp/symfony-cache` (See [Symfony Cache component][symfony/cache])

> [!IMPORTANT]
> 
> The option `cache` is deprecated and will be removed in version 10, use 'cache-dir' instead.

## Cache Directory

The `cache-dir` (`string` default `.phplint.cache`) setting identify the local directory where to save lint results.

> [!IMPORTANT]
>
> This option replace the `cache` legacy option

## Cache invalidation

The `cache-ttl` (`int`|`string` default `3600` seconds => 1 hour) setting was introduced on version 9.6
to limit cache life of files syntax checking results.

> [!CAUTION]
> 
> On previous versions, caching results were permanently stored.

## No caching

Use the `no-cache` (`bool` default `false`) setting (with `true`) if you want to ignore previous scan results.

All files to analyse are lint again. That means checking may be slower than with an active cache.

## Memory limit

Sometimes if a file to lint is too big for your current PHP ini `memory_limit` setting, 
you may override it temporally for your PHPLint processes.

The `memory-limit` (`init`|`string` default to your current PHP ini `memory_limit` setting) accept values defined by [following syntax][shorthand-byte-options].

Use `-1` if you want to have no memory limit.

## JSON output

The `format` setting with value `json` allow to write results in a JSON format.

For example:

```json
{
    "status": "failure",
    "failures": {
        "/phplint/tests/fixtures/php-8.2_syntax.php": {
            "absolute_file": "/phplint/tests/fixtures/php-8.2_syntax.php",
            "relative_file": "fixtures/php-8.2_syntax.php",
            "error": "False can not be used as a standalone type in line 12",
            "line": 12
        },
        "/phplint/tests/fixtures/syntax_error.php": {
            "absolute_file": "/phplint/tests/fixtures/syntax_error.php",
            "relative_file": "fixtures/syntax_error.php",
            "error": "unexpected end of file in line 4",
            "line": 4
        },
        "/phplint/tests/fixtures/syntax_warning.php": {
            "absolute_file": "/phplint/tests/fixtures/syntax_warning.php",
            "relative_file": "fixtures/syntax_warning.php",
            "error": " declare(encoding=...) ignored because Zend multibyte feature is turned off by settings in line 12",
            "line": 12
        }
    },
    "application_version": {
        "long": "phplint 9.4.0",
        "short": "9.4.0"
    },
    "time_usage": "< 1 sec",
    "memory_usage": "8.0 MiB",
    "cache_usage": "0 hit, 54 misses",
    "process_count": 54,
    "files_count": 54,
    "options_used": {
        "path": [
            "src/",
            "tests/"
        ],
        "configuration": ".phplint.yml",
        "no-configuration": false,
        "exclude": [
            "vendor"
        ],
        "extensions": [
            "php"
        ],
        "jobs": 10,
        "no-cache": true,
        "cache": ".phplint.cache",
        "no-progress": false,
        "progress": "printer",
        "output": "sar.json",
        "format": [
            "json"
        ],
        "warning": true,
        "memory-limit": -1,
        "ignore-exit-code": false,
        "bootstrap": ""
    }
}
```

## Junit output

The `format` setting with value `junit` allow to write results in a JUnit XML format.

For example:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="PHP Linter 9.4.0" timestamp="2024-07-05T07:52:39+0000" time="&lt; 1 sec" tests="1" errors="1">
    <testcase errors="1" failures="0">
      <error type="Error" message="unexpected end of file in line 4">/absolute/path/to/tests/fixtures/syntax_error.php</error>
    </testcase>
  </testsuite>
</testsuites>
```

## Checkstyle output

The `format` setting with value `checkstyle` allow to write results in a Checkstyle XML format.

For example:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle>
  <file name="/phplint/tests/fixtures/syntax_error.php">
    <error line="4" severity="error" message="unexpected end of file in line 4"/>
  </file>
  <file name="/phplint/tests/fixtures/php-8.2_syntax.php">
    <error line="12" severity="error" message="False can not be used as a standalone type in line 12"/>
  </file>
  <file name="/phplint/tests/fixtures/syntax_warning.php">
    <error line="12" severity="error" message=" declare(encoding=...) ignored because Zend multibyte feature is turned off by settings in line 12"/>
  </file>
</checkstyle>
```

## SARIF output

> [!NOTE]
> 
> Since version 9.4.0, this format is optional and requires an extra package to be installed.
>
> ```composer require --dev bartlett/sarif-php-converters```

The `format` setting with value `'\Overtrue\PHPLint\Output\SarifOutput'` allow to write results in a SARIF 2.1.0 JSON format.

For example:

```json
{
    "$schema": "https://json.schemastore.org/sarif-2.1.0.json",
    "version": "2.1.0",
    "runs": [
        {
            "tool": {
                "driver": {
                    "name": "PHPLint",
                    "shortDescription": {
                        "text": "Syntax check only (lint) of PHP files"
                    },
                    "fullDescription": {
                        "text": "PHPLint is a tool that can speed up linting of php files by running several lint processes at once."
                    },
                    "fullName": "PHPLint version 9.4.0 by overtrue and contributors",
                    "semanticVersion": "9.4.0",
                    "informationUri": "https://github.com/overtrue/phplint",
                    "rules": [
                        {
                            "id": "PHPLINT101",
                            "shortDescription": {
                                "text": "Syntax error"
                            },
                            "fullDescription": {
                                "text": "Syntax error detected when lint a file"
                            },
                            "helpUri": "https://www.php.net/manual/en/langref.php",
                            "help": {
                                "text": "https://www.php.net/manual/en/features.commandline.options.php"
                            }
                        }
                    ]
                },
                "extensions": [
                    {
                        "name": "bartlett/sarif-php-converters",
                        "shortDescription": {
                            "text": "PHPLint SARIF Converter"
                        },
                        "version": "1.0.0"
                    }
                ]
            },
            "invocations": [
                {
                    "executionSuccessful": true,
                    "commandLine": "bin/phplint",
                    "arguments": [
                        "--no-cache",
                        "--format",
                        "\\Overtrue\\PHPLint\\Output\\SarifOutput",
                        "-v",
                        "src/",
                        "tests/"
                    ],
                    "workingDirectory": {
                        "uri": "file:///shared/backups/github/phplint/"
                    }
                }
            ],
            "originalUriBaseIds": {
                "WORKINGDIR": {
                    "uri": "file:///shared/backups/github/phplint/"
                }
            },
            "results": [
                {
                    "message": {
                        "text": "unexpected end of file in line 4"
                    },
                    "ruleId": "PHPLINT101",
                    "locations": [
                        {
                            "physicalLocation": {
                                "artifactLocation": {
                                    "uri": "tests/fixtures/syntax_error.php",
                                    "uriBaseId": "WORKINGDIR"
                                },
                                "region": {
                                    "startLine": 4,
                                    "snippet": {
                                        "rendered": {
                                            "text": "\u001b[31m  > \u001b[0m\u001b[90m4| \u001b[0m"
                                        }
                                    }
                                },
                                "contextRegion": {
                                    "startLine": 2,
                                    "endLine": 6,
                                    "snippet": {
                                        "rendered": {
                                            "text": "\u001b[31m  > \u001b[0m\u001b[90m2| \u001b[0m\n    \u001b[90m3| \u001b[0m\u001b[32mprint(\u001b[0m\u001b[39m$a\u001b[0m\u001b[32m)\u001b[0m\n    \u001b[90m4| \u001b[0m"
                                        }
                                    }
                                }
                            }
                        }
                    ],
                    "partialFingerprints": {
                        "PHPLINT101": "9d2c5cee410c5007acb62ee25b9a0dfb740fb8f531235e6abc5dd7535930ef2f"
                    }
                },
                {
                    "message": {
                        "text": "False can not be used as a standalone type in line 12"
                    },
                    "ruleId": "PHPLINT101",
                    "locations": [
                        {
                            "physicalLocation": {
                                "artifactLocation": {
                                    "uri": "tests/fixtures/php-8.2_syntax.php",
                                    "uriBaseId": "WORKINGDIR"
                                },
                                "region": {
                                    "startLine": 12,
                                    "snippet": {
                                        "rendered": {
                                            "text": "\u001b[31m  > \u001b[0m\u001b[90m12| \u001b[0m\u001b[32mfunction \u001b[0m\u001b[39malwaysReturnsFalse\u001b[0m\u001b[32m(): \u001b[0m\u001b[39mfalse\u001b[0m"
                                        }
                                    }
                                },
                                "contextRegion": {
                                    "startLine": 10,
                                    "endLine": 14,
                                    "snippet": {
                                        "rendered": {
                                            "text": "\u001b[31m  > \u001b[0m\u001b[90m10| \u001b[0m\u001b[33m */\u001b[0m\n    \u001b[90m11| \u001b[0m\n    \u001b[90m12| \u001b[0m\u001b[32mfunction \u001b[0m\u001b[39malwaysReturnsFalse\u001b[0m\u001b[32m(): \u001b[0m\u001b[39mfalse\u001b[0m\n    \u001b[90m13| \u001b[0m\u001b[32m{\u001b[0m\n    \u001b[90m14| \u001b[0m\u001b[32m}\u001b[0m"
                                        }
                                    }
                                }
                            }
                        }
                    ],
                    "partialFingerprints": {
                        "PHPLINT101": "b4f5ba1d66790be578109d251ced990b42fe6117554a275142ab750f50ca39f4"
                    }
                },
                {
                    "message": {
                        "text": " declare(encoding=...) ignored because Zend multibyte feature is turned off by settings in line 12"
                    },
                    "ruleId": "PHPLINT101",
                    "locations": [
                        {
                            "physicalLocation": {
                                "artifactLocation": {
                                    "uri": "tests/fixtures/syntax_warning.php",
                                    "uriBaseId": "WORKINGDIR"
                                },
                                "region": {
                                    "startLine": 12,
                                    "snippet": {
                                        "rendered": {
                                            "text": "\u001b[31m  > \u001b[0m\u001b[90m12| \u001b[0m\u001b[32mdeclare(\u001b[0m\u001b[39mencoding\u001b[0m\u001b[32m=\u001b[0m\u001b[31m\"utf8\"\u001b[0m\u001b[32m);\u001b[0m"
                                        }
                                    }
                                },
                                "contextRegion": {
                                    "startLine": 10,
                                    "endLine": 14,
                                    "snippet": {
                                        "rendered": {
                                            "text": "\u001b[31m  > \u001b[0m\u001b[90m10| \u001b[0m\u001b[33m */\u001b[0m\n    \u001b[90m11| \u001b[0m\n    \u001b[90m12| \u001b[0m\u001b[32mdeclare(\u001b[0m\u001b[39mencoding\u001b[0m\u001b[32m=\u001b[0m\u001b[31m\"utf8\"\u001b[0m\u001b[32m);\u001b[0m\n    \u001b[90m13| \u001b[0m"
                                        }
                                    }
                                }
                            }
                        }
                    ],
                    "partialFingerprints": {
                        "PHPLINT101": "a1bed88116ad4e69c924107f5fa77a80379a08f2723871f5d0af6eb272dcf3c2"
                    }
                }
            ],
            "automationDetails": {
                "id": "Daily run 2024-07-05T08:21:21+00:00"
            }
        }
    ]
}
```

## Exit Code

The `no-files-exit-code` (`bool` default `false`) setting allow to exit `phplint` command with failure (status code `1`) when no files processed.
By default, `phplint` exit with success (status code `0`)

[symfony/cache]: https://github.com/symfony/cache/
[shorthand-byte-options]: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
