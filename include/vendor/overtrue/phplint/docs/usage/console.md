# Console CLI

Linting PHP source files should be as simple as running `phplint` with one or more source paths (no config required!).
It will however assume some defaults that you might want to change.

PHPLint will by default be looking in order for the file `.phplint.yml` in the current working directory.
You can use another filename by option: `--configuration=FILENAME` or `-c FILENAME`.

A basic configuration could be for example:

```yaml
path: ./src
jobs: 10
extensions:
  - php
exclude:
  - vendor
warning: true
memory-limit: -1
no-cache: true
```

> If you want to ignore the configuration file directives, you should specify option `--no-configuration`.

You can then find more advanced configuration settings in [the configuration documentation](../configuration.md).
For more information on which options are available, you can run: `phplint --help`

```text
Description:
  Files syntax check only

Usage:
  lint [options] [--] [<path>...]

Arguments:
  path                               Path to file or directory to lint (default: working directory)

Options:
      --exclude=EXCLUDE              Path to file or directory to exclude from linting (multiple values allowed)
      --extensions=EXTENSIONS        Check only files with selected extensions (multiple values allowed)
  -j, --jobs=JOBS                    Number of paralleled jobs to run
  -c, --configuration=CONFIGURATION  Read configuration from config file [default: ".phplint.yml"]
      --no-configuration             Ignore default configuration file (.phplint.yml)
      --cache=CACHE                  Path to the cache directory
      --no-cache                     Ignore cached data
  -p, --progress=PROGRESS            Show the progress output
      --no-progress                  Hide the progress output
  -o, --output=OUTPUT                Generate an output to the specified path (default: standard output)
      --format=FORMAT                Format of requested reports (multiple values allowed)
  -w, --warning                      Also show warnings
      --memory-limit=MEMORY-LIMIT    Memory limit for analysis
      --ignore-exit-code             Ignore exit codes so there are no "failure" exit code even when no files processed
      --bootstrap=BOOTSTRAP          A PHP script that is included before the linter run
  -h, --help                         Display help for the given command. When no command is given display help for the lint command
  -q, --quiet                        Do not output any message
  -V, --version                      Display this application version
      --ansi|--no-ansi               Force (or disable --no-ansi) ANSI output
  -n, --no-interaction               Do not ask any interactive question
  -v|vv|vvv, --verbose               Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
