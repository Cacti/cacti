<?php
use Gettext\Languages\Exporter\Exporter;
use Gettext\Languages\Language;

// Let's start by imposing that we don't accept any error or warning.
// This is a really life-saving approach.
error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    Enviro::echoErr("$errstr\nFile: $errfile\nLine: $errline\nCode: $errno\n");
    die(5);
});

require_once dirname(__DIR__).'/src/autoloader.php';

// Parse the command line options
Enviro::initialize();

try {
    if (isset(Enviro::$languages)) {
        $languages = array();
        foreach (Enviro::$languages as $languageId) {
            $language = Language::getById($languageId);
            if (!isset($language)) {
                throw new Exception("Unable to find the language with id '$languageId'");
            }
            $languages[] = $language;
        }
    } else {
        $languages = Language::getAll();
    }
    if (Enviro::$reduce) {
        $languages = Enviro::reduce($languages);
    }
    if (isset(Enviro::$outputFilename)) {
        echo call_user_func(array(Exporter::getExporterClassName(Enviro::$outputFormat), 'toFile'), $languages, Enviro::$outputFilename, array('us-ascii' => Enviro::$outputUSAscii));
    } else {
        echo call_user_func(array(Exporter::getExporterClassName(Enviro::$outputFormat), 'toString'), $languages, array('us-ascii' => Enviro::$outputUSAscii));
    }
} catch (Exception $x) {
    Enviro::echoErr($x->getMessage()."\n");
    Enviro::echoErr("Trace:\n");
    Enviro::echoErr($x->getTraceAsString()."\n");
    die(4);
}

die(0);

/**
 * Helper class to handle command line options.
 */
class Enviro
{
    /**
     * Shall the output contain only US-ASCII characters?
     * @var bool
     */
    public static $outputUSAscii;
    /**
     * The output format.
     * @var string
     */
    public static $outputFormat;
    /**
     * Output file name.
     * @var string
     */
    public static $outputFilename;
    /**
     * List of wanted language IDs; it not set: all languages will be returned.
     * @var array|null
     */
    public static $languages;
    /**
     * Reduce the language list to the minimum common denominator.
     * @var bool
     */
    public static $reduce;
    /**
     * Parse the command line options.
     */
    public static function initialize()
    {
        global $argv;
        self::$outputUSAscii = false;
        self::$outputFormat = null;
        self::$outputFilename = null;
        self::$languages = null;
        self::$reduce = null;
        $exporters = Exporter::getExporters();
        if (isset($argv) && is_array($argv)) {
            foreach ($argv as $argi => $arg) {
                if ($argi === 0) {
                    continue;
                }
                if (is_string($arg)) {
                    $argLC = trim(strtolower($arg));
                    switch ($argLC) {
                        case '--us-ascii':
                            self::$outputUSAscii = true;
                            break;
                        case '--reduce=yes':
                            self::$reduce = true;
                            break;
                        case '--reduce=no':
                            self::$reduce = false;
                            break;
                        default:
                            if (preg_match('/^--output=.+$/', $argLC)) {
                                if (isset(self::$outputFilename)) {
                                    self::echoErr("The output file name has been specified more than once!\n");
                                    self::showSyntax();
                                    die(3);
                                }
                                list(, self::$outputFilename) = explode('=', $arg, 2);
                                self::$outputFilename = trim(self::$outputFilename);
                            } elseif (preg_match('/^--languages?=.+$/', $argLC)) {
                                list(, $s) = explode('=', $arg, 2);
                                $list = explode(',', $s);
                                if (is_array(self::$languages)) {
                                    self::$languages = array_merge(self::$languages, $list);
                                } else {
                                    self::$languages = $list;
                                }
                            } elseif (isset($exporters[$argLC])) {
                                if (isset(self::$outputFormat)) {
                                    self::echoErr("The output format has been specified more than once!\n");
                                    self::showSyntax();
                                    die(3);
                                }
                                self::$outputFormat = $argLC;
                            } else {
                                self::echoErr("Unknown option: $arg\n");
                                self::showSyntax();
                                die(2);
                            }
                            break;
                    }
                }
            }
        }
        if (!isset(self::$outputFormat)) {
            self::showSyntax();
            die(1);
        }
        if (isset(self::$languages)) {
            self::$languages = array_values(array_unique(self::$languages));
        }
        if (!isset(self::$reduce)) {
            self::$reduce = isset(self::$languages) ? false : true;
        }
    }

    /**
     * Write out the syntax.
     */
    public static function showSyntax()
    {
        $exporters = array_keys(Exporter::getExporters(true));
        self::echoErr("Syntax: php ".basename(__FILE__)." [--us-ascii] [--languages=<LanguageId>[,<LanguageId>,...]] [--reduce=yes|no] [--output=<file name>] <".implode('|', $exporters).">\n");
        self::echoErr("Where:\n");
        self::echoErr("--us-ascii : if specified, the output will contain only US-ASCII characters.\n");
        self::echoErr("--languages: (or --language) export only the specified language codes.\n");
        self::echoErr("             Separate languages with commas; you can also use this argument\n");
        self::echoErr("             more than once; it's case insensitive and accepts both '_' and\n");
        self::echoErr("             '-' as locale chunks separator (eg we accept 'it_IT' as well as\n");
        self::echoErr("            'it-it').\n");
        self::echoErr("--reduce   : if set to yes the output won't contain languages with the same\n");
        self::echoErr("             base language and rules.\n For instance nl_BE ('Flemish') will be\n");
        self::echoErr("             omitted because it's the same as nl ('Dutch').\n");
        self::echoErr("             Defaults to 'no' --languages is specified, to 'yes' otherwise.\n");
        self::echoErr("--output   : if specified, the output will be saved to <file name>. If not\n");
        self::echoErr("             specified we'll output to standard output.\n");
        self::echoErr("Output formats\n");
        $len = max(array_map('strlen', $exporters));
        foreach ($exporters as $exporter) {
            self::echoErr(str_pad($exporter, $len).": ".Exporter::getExporterDescription($exporter)."\n");
        }
    }
    /**
     * Print a string to stderr.
     * @param string $str The string to be printed out.
     */
    public static function echoErr($str)
    {
        $hStdErr = @fopen('php://stderr', 'a');
        if ($hStdErr === false) {
            echo $str;
        } else {
            fwrite($hStdErr, $str);
            fclose($hStdErr);
        }
    }
    /**
     * Reduce a language list to the minimum common denominator.
     * @param Language[] $languages
     * @return Language[]
     */
    public static function reduce($languages)
    {
        for ($numChunks = 3; $numChunks >= 2; $numChunks--) {
            $filtered = array();
            foreach ($languages as $language) {
                $chunks = explode('_', $language->id);
                $compatibleFound = false;
                if (count($chunks) === $numChunks) {
                    $categoriesHash = serialize($language->categories);
                    $otherIds = array();
                    $otherIds[] = $chunks[0];
                    for ($k = 2; $k < $numChunks; $k++) {
                        $otherIds[] = $chunks[0].'_'.$chunks[$numChunks - 1];
                    }

                    foreach ($languages as $check) {
                        foreach ($otherIds as $otherId) {
                            if (($check->id === $otherId) && ($check->formula === $language->formula) && (serialize($check->categories) === $categoriesHash)) {
                                $compatibleFound = true;
                                break;
                            }
                        }
                        if ($compatibleFound === true) {
                            break;
                        }
                    }
                }
                if (!$compatibleFound) {
                    $filtered[] = $language;
                }
            }
            $languages = $filtered;
        }

        return $languages;
    }
}
