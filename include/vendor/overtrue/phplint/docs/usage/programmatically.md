# Programmatically

```php
<?php

use Overtrue\PHPLint\Command\LintCommand;
use Overtrue\PHPLint\Configuration\ConsoleOptionsResolver;
use Overtrue\PHPLint\Event\EventDispatcher;
use Overtrue\PHPLint\Finder;
use Overtrue\PHPLint\Linter;
use Symfony\Component\Console\Input\ArrayInput;

$dispatcher = new EventDispatcher([]);

$arguments = [
    'path' => [dirname(__DIR__) . '/src', dirname(__DIR__) . '/tests'],
    '--no-configuration' => true,
    '--no-cache' => true,
    '--exclude' => ['vendor'],
    '--extensions' => ['php'],
    '--warning' => true,
];
$command = new LintCommand($dispatcher);
$definition = $command->getDefinition();
$input = new ArrayInput($arguments, $definition);

$configResolver = new ConsoleOptionsResolver($input);

$finder = new Finder($configResolver);
$linter = new Linter($configResolver, $dispatcher);
$results = $linter->lintFiles($finder->getFiles());

var_dump($results->getErrors());
/*
 array(1) {
  ["/absolute/path/to/tests/fixtures/syntax_error.php"]=>
  array(4) {
    ["absolute_file"]=>
    string(62) "/absolute/path/to/tests/fixtures/syntax_error.php"
    ["relative_file"]=>
    string(25) "fixtures/syntax_error.php"
    ["error"]=>
    string(32) "unexpected end of file in line 4"
    ["line"]=>
    int(4)
  }
}
 */

var_dump($results->getWarnings());
/*
array(1) {
  ["/absolute/path/to/tests/fixtures/syntax_warning.php"]=>
  array(4) {
    ["absolute_file"]=>
    string(64) "/absolute/path/to/tests/fixtures/syntax_warning.php"
    ["relative_file"]=>
    string(27) "fixtures/syntax_warning.php"
    ["error"]=>
    string(97) " declare(encoding=...) ignored because Zend multibyte feature is turned off by settings in line 1"
    ["line"]=>
    int(1)
  }
}
 */
```
