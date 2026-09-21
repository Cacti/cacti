<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Text;

use Jfcherng\Diff\Exception\UnsupportedFunctionException;
use Jfcherng\Diff\Renderer\AbstractRenderer;
use Jfcherng\Diff\Renderer\RendererConstant;
use Jfcherng\Utility\CliColor;

/**
 * Base renderer for rendering text-based diffs.
 */
abstract class AbstractText extends AbstractRenderer
{
    /**
     * @var bool is this renderer pure text?
     */
    public const IS_TEXT_RENDERER = true;

    /**
     * @var string the diff output representing there is no EOL at EOF in the GNU diff tool
     */
    public const GNU_OUTPUT_NO_EOL_AT_EOF = '\ No newline at end of file';

    /**
     * @var bool controls whether cliColoredString() is enabled or not
     */
    protected $isCliColorEnabled = false;

    public function setOptions(array $options): AbstractRenderer
    {
        parent::setOptions($options);

        // determine $this->isCliColorEnabled
        if ($this->options['cliColorization'] === RendererConstant::CLI_COLOR_ENABLE) {
            $this->isCliColorEnabled = true;
        } elseif ($this->options['cliColorization'] === RendererConstant::CLI_COLOR_DISABLE) {
            $this->isCliColorEnabled = false;
        } else {
            $this->isCliColorEnabled = \PHP_SAPI === 'cli' && $this->hasColorSupport(\STDOUT);
        }

        return $this;
    }

    public function getResultForIdenticalsDefault(): string
    {
        return '';
    }

    protected function renderArrayWorker(array $differArray): string
    {
        throw new UnsupportedFunctionException(__METHOD__);

        return ''; // make IDE not complain
    }

    /**
     * Colorize the string for CLI output.
     *
     * @param string      $str    the string
     * @param null|string $symbol the symbol
     *
     * @return string the (maybe) colorized string
     */
    protected function cliColoredString(string $str, ?string $symbol): string
    {
        static $symbolToStyles = [
            '@' => ['f_purple', 'bold'], // header
            '-' => ['f_red', 'bold'], // deleted
            '+' => ['f_green', 'bold'], // inserted
            '!' => ['f_yellow', 'bold'], // replaced
        ];

        $styles = $symbolToStyles[$symbol] ?? [];

        if (!$this->isCliColorEnabled || empty($styles)) {
            return $str;
        }

        return CliColor::color($str, $styles);
    }

    /**
     * Returns true if the stream supports colorization.
     *
     * Colorization is disabled if not supported by the stream:
     *
     * This is tricky on Windows, because Cygwin, Msys2 etc emulate pseudo
     * terminals via named pipes, so we can only check the environment.
     *
     * Reference: Composer\XdebugHandler\Process::supportsColor
     * https://github.com/composer/xdebug-handler
     *
     * @see https://github.com/symfony/console/blob/647c51ff073300a432a4a504e29323cf0d5e0571/Output/StreamOutput.php#L81-L124
     *
     * @param resource $stream
     *
     * @return bool true if the stream supports colorization, false otherwise
     *
     * @suppress PhanUndeclaredFunction
     */
    protected function hasColorSupport($stream): bool
    {
        // Follow https://no-color.org/
        if (isset($_SERVER['NO_COLOR']) || false !== getenv('NO_COLOR')) {
            return false;
        }

        if ('Hyper' === getenv('TERM_PROGRAM')) {
            return true;
        }

        if (\DIRECTORY_SEPARATOR === '\\') {
            return (\function_exists('sapi_windows_vt100_support')
                && @sapi_windows_vt100_support($stream))
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        if (\function_exists('stream_isatty')) {
            return @stream_isatty($stream);
        }

        if (\function_exists('posix_isatty')) {
            return @posix_isatty($stream);
        }

        $stat = @fstat($stream);

        // Check if formatted mode is S_IFCHR
        return $stat ? 0020000 === ($stat['mode'] & 0170000) : false;
    }
}
