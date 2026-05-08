<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use Closure;
use InvalidArgumentException;
use Pest\Mutate\Exceptions\StreamWrapperException;
use RuntimeException;

class StreamWrapper
{
    private const PROTOCOL = 'file';

    /**
     * @var resource
     */
    public $context;

    /**
     * @var resource
     */
    private mixed $resource;

    private static string $path;

    private static string $override = '';

    public static function start(string $path, string $override): void
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException('Original file does not exist: '.$path);
        }

        if (! file_exists($override)) {
            throw new InvalidArgumentException('Override file does not exist: '.$override);
        }

        self::$path = $path;
        self::$override = $override;

        self::enable();
    }

    public static function enable(): void
    {
        if (! isset(self::$path)) {
            throw new StreamWrapperException('StreamWrapper not started');
        }

        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    public static function disable(): void
    {
        stream_wrapper_restore(self::PROTOCOL);
    }

    public function stream_open(string $path, string $mode, int $options): bool
    {
        if (! in_array($path, [self::$path, realpath(self::$path)], true)) {
            $resource = $this->withOriginalWrapper(
                fn () => fopen($path, $mode, (bool) $options)
            );

            if (is_bool($resource)) {
                return false;
            }

            $this->resource = $resource;

            return true;
        }

        $resource = $this->withOriginalWrapper(
            fn () => fopen(self::$override, $mode, (bool) $options)
        );

        if (is_bool($resource)) {
            return false;
        }

        $this->resource = $resource;

        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->resource);

        return true;
    }

    public function dir_opendir(string $path): bool
    {
        $resource = $this->withOriginalWrapper(
            fn () => opendir($path)
        );

        if (is_bool($resource)) {
            return false;
        }

        $this->resource = $resource;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->resource);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->resource);

        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        self::disable();

        try {
            $return = mkdir($path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE));
        } finally {
            self::enable();
        }

        return $return;
    }

    public function rename(string $path_from, string $path_to): bool
    {
        self::disable();

        try {
            $return = rename($path_from, $path_to);
        } finally {
            self::enable();
        }

        return $return;
    }

    public function rmdir(string $path): bool
    {
        return $this->withOriginalWrapper(
            fn (): bool => rmdir($path)
        );
    }

    /**
     * @return resource
     */
    public function stream_cast(): mixed
    {
        return $this->resource;
    }

    public function stream_close(): bool
    {
        return fclose($this->resource);
    }

    public function stream_eof(): bool
    {
        return feof($this->resource);
    }

    public function stream_flush(): bool
    {
        return fflush($this->resource);
    }

    public function stream_lock(int $operation): bool
    {
        return flock($this->resource, LOCK_SH);
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return $this->withOriginalWrapper(function () use ($value, $path, $option): bool {
            switch ($option) {
                case STREAM_META_TOUCH:
                    if (empty($value)) { // @phpstan-ignore-line
                        return touch($path);
                    }

                    return touch($path, $value[0], $value[1]); // @phpstan-ignore-line
                case STREAM_META_OWNER_NAME:
                case STREAM_META_OWNER:
                    return chown($path, $value); // @phpstan-ignore-line
                case STREAM_META_GROUP_NAME:
                case STREAM_META_GROUP:
                    return chgrp($path, $value); // @phpstan-ignore-line
                case STREAM_META_ACCESS:
                    return chmod($path, $value); // @phpstan-ignore-line
                default:
                    throw new RuntimeException('Unknown stream_metadata option');
            }
        });
    }

    /**
     * @param  int<1, max>  $count
     */
    public function stream_read(int $count): string|false
    {
        return fread($this->resource, $count);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return fseek($this->resource, $offset, $whence) === 0;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        match ($option) {
            STREAM_OPTION_BLOCKING => stream_set_blocking($this->resource, (bool) $arg1),
            STREAM_OPTION_READ_TIMEOUT => stream_set_timeout($this->resource, $arg1, $arg2),
            STREAM_OPTION_WRITE_BUFFER => stream_set_write_buffer($this->resource, $arg1),
            STREAM_OPTION_READ_BUFFER => stream_set_read_buffer($this->resource, $arg1),
            default => false,
        };

        return false;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->resource);
    }

    public function stream_tell(): int|false
    {
        return ftell($this->resource);
    }

    /**
     * @param  int<0, max>  $new_size
     */
    public function stream_truncate(int $new_size): bool
    {
        return ftruncate($this->resource, $new_size);
    }

    public function stream_write(string $data): int|false
    {
        return fwrite($this->resource, $data);
    }

    public function unlink(string $path): bool
    {
        return $this->withOriginalWrapper(fn (): bool => unlink($path)
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|bool
     */
    public function url_stat(string $path, int $flags): array|bool
    {
        return $this->withOriginalWrapper(function () use ($path, $flags): false|array {
            if (is_readable($path) === false) {
                return false;
            }

            if (($flags & STREAM_URL_STAT_LINK) !== 0) {
                return lstat($path);
            }

            return stat($path);
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withOriginalWrapper(callable $callback): mixed
    {
        self::disable();

        $result = $callback();

        self::enable();

        return $result;
    }
}
