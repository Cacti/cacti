<?php

error_reporting(E_ALL);

include_once dirname(__DIR__).'/vendor/autoload.php';

if(class_exists('PHPUnit_Framework_Error_Notice')) {
    class_alias('PHPUnit_Framework_Error_Notice', 'PHPUnit\Framework\Error\Notice');
}

PHPUnit\Framework\Error\Notice::$enabled = true;

//Config
Gettext\Translations::$options['defaultDateHeaders'] = [];
Gettext\Translations::$options['headersSorting'] = true;

Gettext\Extractors\PhpCode::$options['extractComments'] = '';

Gettext\Generators\Csv::$options['includeHeaders'] = true;

Gettext\Generators\Jed::$options['json'] = JSON_PRETTY_PRINT;

Gettext\Generators\Json::$options['json'] = JSON_PRETTY_PRINT;
Gettext\Generators\Json::$options['includeHeaders'] = true;

Gettext\Generators\JsonDictionary::$options['json'] = JSON_PRETTY_PRINT;

Gettext\Generators\Yaml::$options['includeHeaders'] = true;
