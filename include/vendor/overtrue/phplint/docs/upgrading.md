# Upgrading

The change from previous versions to 9.0 is quite significant but should be really smooth for the user. The main changes are:

- **Cache** system used now the [`symfony/cache`][symfony/cache] component and must be [`PSR-6`][psr-6] compatible.
- **JUnit XML** output did not use anymore the [`n98/junit-xml`][n98/junit-xml] dependency. Replaced by a native [`DOMDocument`][domdocument] implementation.
- **`--json`** option was renamed to **`--log-json`**
- **`--xml`** option was renamed to **`--log-junit`**
- **`php://stdout`** alias to write results stream to standard output is automagically applied (default) when invoking `phplint` with `log-json` or `log-xml` options.
- Progress display has comestic evolved.
- New option **--progress** added to version 9.0 to be able to change progress display (default `printer` mode is legacy behavior).

For Developers (read also the [Architecture Guide](./architecture/event.md))

- API: `Overtrue\PHPLint\Finder` is the central point to identify files to scan and use the [`symfony/finder`][symfony/finder] component.
- API: `Overtrue\PHPLint\Extension\ProgressPrinter` replace the Linter process callback (see `Overtrue\PHPLint\Linter::setProcessCallback`).
- API: `Overtrue\PHPLint\Extension\ProgressBar` use the [symfony/console progressBar helper][symfony-progressbar]
- API: `Overtrue\PHPLint\Output\JsonOutput` allow to log scan results in JSON format to file or standard output.
- API: `Overtrue\PHPLint\Output\JunitOutput` allow to log scan results in JUnit XML format to file or standard output.
- API: `Overtrue\PHPLint\Event\EventDispatcher` is the central point of event listener system and use the [`symfony/event-dispatcher`][symfony/event-dispatcher] component.
- API: `Overtrue\PHPLint\Configuration\ConsoleOptionsResolver` is the configuration resolver for console CLI usage without YAML file and use the [`symfony/options-resolver`][symfony/options-resolver] component.
- API: `Overtrue\PHPLint\Configuration\FileOptionsResolver` is the configuration resolver for YAML file and use the [`symfony/options-resolver`][symfony/options-resolver] component.

[symfony/cache]: https://github.com/symfony/cache
[symfony/event-dispatcher]: https://github.com/symfony/event-dispatcher
[symfony/options-resolver]: https://github.com/symfony/options-resolver
[symfony/finder]: https://github.com/symfony/finder
[psr-6]: https://www.php-fig.org/psr/psr-6/
[n98/junit-xml]: https://packagist.org/packages/n98/junit-xml
[domdocument]: https://www.php.net/manual/en/class.domdocument
[symfony-progressbar]: https://symfony.com/doc/current/components/console/helpers/progressbar.html
