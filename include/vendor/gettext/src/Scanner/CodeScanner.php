<?php
declare(strict_types = 1);

namespace Gettext\Scanner;

use Exception;
use Gettext\Translation;
use Gettext\Translations;

/**
 * Base class with common functions to scan files with code and get gettext translations.
 */
abstract class CodeScanner extends Scanner
{
    protected $ignoreInvalidFunctions = false;

    protected $addReferences = true;

    protected $commentsPrefixes = [];

    protected $functions = [];

    /**
     * @param array $functions [fnName => handler]
     */
    public function setFunctions(array $functions): self
    {
        $this->functions = $functions;

        return $this;
    }

    /**
     * @return array [fnName => handler]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function ignoreInvalidFunctions($ignore = true): self
    {
        $this->ignoreInvalidFunctions = $ignore;

        return $this;
    }

    public function addReferences($enabled = true): self
    {
        $this->addReferences = $enabled;

        return $this;
    }

    public function extractCommentsStartingWith(string ...$prefixes): self
    {
        $this->commentsPrefixes = $prefixes;

        return $this;
    }

    public function scanString(string $string, string $filename): void
    {
        $functionsScanner = $this->getFunctionsScanner();
        $functions = $functionsScanner->scan($string, $filename);

        foreach ($functions as $function) {
            $this->handleFunction($function);
        }
    }

    abstract public function getFunctionsScanner(): FunctionsScannerInterface;

    protected function handleFunction(ParsedFunction $function)
    {
        $handler = $this->getFunctionHandler($function);

        if (is_null($handler)) {
            return;
        }

        $translation = call_user_func($handler, $function);

        if ($translation && $this->addReferences) {
            $translation->getReferences()->add($function->getFilename(), $function->getLine());
        }
    }

    protected function getFunctionHandler(ParsedFunction $function): ?callable
    {
        $name = $function->getName();
        $handler = $this->functions[$name] ?? null;

        return is_null($handler) ? null : [$this, $handler];
    }

    protected function addComments(ParsedFunction $function, ?Translation $translation): ?Translation
    {
        if (empty($this->commentsPrefixes) || empty($translation)) {
            return $translation;
        }

        foreach ($function->getComments() as $comment) {
            if ($this->checkComment($comment)) {
                $translation->getExtractedComments()->add($comment);
            }
        }

        return $translation;
    }

    protected function addFlags(ParsedFunction $function, ?Translation $translation): ?Translation
    {
        if (empty($translation)) {
            return $translation;
        }

        foreach ($function->getFlags() as $flag) {
            $translation->getFlags()->add($flag);
        }

        return $translation;
    }

    protected function checkFunction(ParsedFunction $function, int $minLength): bool
    {
        if ($function->countArguments() < $minLength) {
            if ($this->ignoreInvalidFunctions) {
                return false;
            }

            throw new Exception(
                sprintf(
                    'Invalid gettext function in %s:%d. At least %d arguments are required',
                    $function->getFilename(),
                    $function->getLine(),
                    $minLength
                )
            );
        }

        $arguments = array_slice($function->getArguments(), 0, $minLength);

        if (in_array(null, $arguments, true)) {
            if ($this->ignoreInvalidFunctions) {
                return false;
            }

            throw new Exception(
                sprintf(
                    'Invalid gettext function in %s:%d. Some required arguments are not valid',
                    $function->getFilename(),
                    $function->getLine()
                )
            );
        }

        return true;
    }

    protected function checkComment(string $comment): bool
    {
        foreach ($this->commentsPrefixes as $prefix) {
            if ($prefix === '' || strpos($comment, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
