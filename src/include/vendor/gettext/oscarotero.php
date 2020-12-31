<?php
// Interfaces first
require_once(__DIR__ . '/src/TranslatorInterface.php');
require_once(__DIR__ . '/src/Extractors/ExtractorInterface.php');
require_once(__DIR__ . '/src/Generators/GeneratorInterface.php');

// Utils before extractors
require_once(__DIR__ . '/src/Utils/HeadersGeneratorTrait.php');
require_once(__DIR__ . '/src/Utils/HeadersExtractorTrait.php');
require_once(__DIR__ . '/src/Utils/MultidimensionalArrayTrait.php');
require_once(__DIR__ . '/src/Utils/FunctionsScanner.php');
require_once(__DIR__ . '/src/Utils/ParsedFunction.php');
require_once(__DIR__ . '/src/Utils/CsvTrait.php');
require_once(__DIR__ . '/src/Utils/PhpFunctionsScanner.php');
require_once(__DIR__ . '/src/Utils/StringReader.php');
require_once(__DIR__ . '/src/Utils/DictionaryTrait.php');
require_once(__DIR__ . '/src/Utils/ParsedComment.php');
require_once(__DIR__ . '/src/Utils/JsFunctionsScanner.php');

require_once(__DIR__ . '/src/Extractors/Extractor.php');
require_once(__DIR__ . '/src/Extractors/Csv.php');
require_once(__DIR__ . '/src/Extractors/Jed.php');
require_once(__DIR__ . '/src/Extractors/YamlDictionary.php');
require_once(__DIR__ . '/src/Extractors/JsonDictionary.php');
require_once(__DIR__ . '/src/Extractors/Xliff.php');
require_once(__DIR__ . '/src/Extractors/Twig.php');
require_once(__DIR__ . '/src/Extractors/Po.php');
require_once(__DIR__ . '/src/Extractors/Blade.php');
require_once(__DIR__ . '/src/Extractors/Json.php');
require_once(__DIR__ . '/src/Extractors/JsCode.php');
require_once(__DIR__ . '/src/Extractors/CsvDictionary.php');
require_once(__DIR__ . '/src/Extractors/Mo.php');
require_once(__DIR__ . '/src/Extractors/PhpArray.php');
require_once(__DIR__ . '/src/Extractors/VueJs.php');
require_once(__DIR__ . '/src/Extractors/PhpCode.php');
require_once(__DIR__ . '/src/Extractors/Yaml.php');
require_once(__DIR__ . '/src/Translations.php');
require_once(__DIR__ . '/src/BaseTranslator.php');
require_once(__DIR__ . '/src/autoloader.php');
require_once(__DIR__ . '/src/Translation.php');
require_once(__DIR__ . '/src/Translator.php');
require_once(__DIR__ . '/src/GettextTranslator.php');
require_once(__DIR__ . '/src/Generators/Csv.php');
require_once(__DIR__ . '/src/Generators/Jed.php');
require_once(__DIR__ . '/src/Generators/YamlDictionary.php');
require_once(__DIR__ . '/src/Generators/JsonDictionary.php');
require_once(__DIR__ . '/src/Generators/Xliff.php');
require_once(__DIR__ . '/src/Generators/Generator.php');
require_once(__DIR__ . '/src/Generators/Po.php');
require_once(__DIR__ . '/src/Generators/Json.php');
require_once(__DIR__ . '/src/Generators/CsvDictionary.php');
require_once(__DIR__ . '/src/Generators/Mo.php');
require_once(__DIR__ . '/src/Generators/PhpArray.php');
require_once(__DIR__ . '/src/Generators/Yaml.php');
require_once(__DIR__ . '/src/Merge.php');

