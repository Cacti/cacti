<?php

namespace Gettext\Utils;

use Exception;
use Gettext\Translations;

abstract class FunctionsScanner
{
    /**
     * Scan and returns the functions and the arguments.
     *
     * @param array $constants Constants used in the code to replace
     *
     * @return array
     */
    abstract public function getFunctions(array $constants = []);

    /**
     * Search for specific functions and create translations.
     *
     * @param Translations $translations The translations instance where save the values
     * @param array $options The extractor options
     * @throws Exception
     */
    public function saveGettextFunctions(Translations $translations, array $options)
    {
        $functions = $options['functions'];
        $file = $options['file'];

        foreach ($this->getFunctions($options['constants']) as $function) {
            list($name, $line, $args) = $function;

            if (isset($options['lineOffset'])) {
                $line += $options['lineOffset'];
            }

            if (!isset($functions[$name])) {
                continue;
            }

            $domain = $context = $original = $plural = null;

            switch ($functions[$name]) {
                case 'noop':
                case 'gettext':
                    if (!isset($args[0])) {
                        continue 2;
                    }

                    $original = $args[0];
                    break;

                case 'ngettext':
                    if (!isset($args[1])) {
                        continue 2;
                    }

                    list($original, $plural) = $args;
                    break;

                case 'pgettext':
                    if (!isset($args[1])) {
                        continue 2;
                    }

                    list($context, $original) = $args;
                    break;

                case 'dgettext':
                    if (!isset($args[1])) {
                        continue 2;
                    }

                    list($domain, $original) = $args;
                    break;

                case 'dpgettext':
                    if (!isset($args[2])) {
                        continue 2;
                    }

                    list($domain, $context, $original) = $args;
                    break;

                case 'npgettext':
                    if (!isset($args[2])) {
                        continue 2;
                    }

                    list($context, $original, $plural) = $args;
                    break;

                case 'dnpgettext':
                    if (!isset($args[3])) {
                        continue 2;
                    }

                    list($domain, $context, $original, $plural) = $args;
                    break;

                case 'dngettext':
                    if (!isset($args[2])) {
                        continue 2;
                    }

                    list($domain, $original, $plural) = $args;
                    break;

                default:
                    throw new Exception(sprintf('Not valid function %s', $functions[$name]));
            }

            if ((string)$original === '') {
                continue;
            }

            $isDefaultDomain = $domain === null;
            $isMatchingDomain = $domain === $translations->getDomain();

            if (!empty($options['domainOnly']) && $isDefaultDomain) {
                // If we want to find translations for a specific domain, skip default domain messages
                continue;
            }

            if (!$isDefaultDomain && !$isMatchingDomain) {
                continue;
            }

            $translation = $translations->insert($context, $original, $plural);
            $translation->addReference($file, $line);

            if (isset($function[3])) {
                foreach ($function[3] as $extractedComment) {
                    $translation->addExtractedComment($extractedComment);
                }
            }
        }
    }
}
