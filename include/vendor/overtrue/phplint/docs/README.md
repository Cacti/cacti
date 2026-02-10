# Documentation

Full documentation may be found in `docs` folder in repository, and may be read online without to do anything else.

As alternative, you may generate a professional static site with [Material for MkDocs][mkdocs-material].

Configuration file `mkdocs.yaml` is available and if you have Docker support, the documentation site can be simply build
with following command: 

`docker run --rm -it -u "$(id -u):$(id -g)" -v ${PWD}:/docs squidfunk/mkdocs-material build --verbose`

## Goal

The PHPLint is a command line tool that can speed up linting of php files by running several lint processes at once.

## Architecture

> As a developer you want to learn more about PHPLint architecture!.

See [Architecture's Guide](architecture/README.md)

## Usage

> Learn more about different usages with console, Docker, CI, and programmatically.

See [Getting-Started's Guide](usage/README.md) to know how to use it.

## Contributing

> Contribution are always welcome and much appreciated!. 

See [Contributor's Guide](contributing.md#contributing) before you start.

## Credits

Project originally created by [@overtrue](https://github.com/overtrue), which is now (since version 9.0) 
actively supported by [Laurent Laville (@llaville)](https://github.com/llaville).

See the list of [all contributors][contributors].

[mkdocs-material]: https://github.com/squidfunk/mkdocs-material
[contributors]: https://github.com/overtrue/phplint/graphs/contributors
