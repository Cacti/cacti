<?php

declare(strict_types=1);

namespace Pest\Mutate\Cache;

use DateInterval;
use DateTime;
use Exception;
use Psr\SimpleCache\CacheInterface;

class FileStore implements CacheInterface
{
    private const CACHE_FOLDER_NAME = 'pest-mutate-cache';

    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? (sys_get_temp_dir().DIRECTORY_SEPARATOR.self::CACHE_FOLDER_NAME); // @pest-mutate-ignore

        if (! is_dir($this->directory)) { // @pest-mutate-ignore
            mkdir($this->directory, recursive: true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getPayload($key) ?? $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $payload = serialize($value);

        $expire = $this->expiration($ttl);

        $content = $expire.$payload;

        $result = file_put_contents($this->filePathFromKey($key), $content);

        return $result !== false; // @pest-mutate-ignore FalseToTrue
    }

    public function delete(string $key): bool
    {
        return unlink($this->filePathFromKey($key));
    }

    public function clear(): bool
    {
        foreach ((array) glob($this->directory.DIRECTORY_SEPARATOR.'*') as $fileName) {
            // @pest-mutate-ignore
            if ($fileName === false) {
                continue;
            }
            if (! str_starts_with(basename($fileName), 'cache-')) {
                continue;
            }
            // @pest-mutate-ignore
            unlink($fileName);
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @param  iterable<string, mixed>  $values
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool // @phpstan-ignore-line
    {
        $result = true;

        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param  iterable<string>  $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $result = true;

        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                $result = false;
            }
        }

        return $result;
    }

    public function has(string $key): bool
    {
        return file_exists($this->filePathFromKey($key));
    }

    private function filePathFromKey(string $key): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'cache-'.hash('xxh3', $key); // @pest-mutate-ignore
    }

    private function expiration(DateInterval|int|null $seconds): int
    {
        if ($seconds instanceof DateInterval) {
            return (new DateTime)->add($seconds)->getTimestamp();
        }

        $seconds ??= 0;

        if ($seconds === 0) {
            return 9_999_999_999; // @pest-mutate-ignore
        }

        return time() + $seconds;
    }

    private function getPayload(string $key): mixed
    {
        if (! file_exists($this->filePathFromKey($key))) {
            return $this->emptyPayload();
        }

        $content = file_get_contents($this->filePathFromKey($key));

        if ($content === false) { // @pest-mutate-ignore
            return $this->emptyPayload();
        }

        try {
            $expire = (int) substr(
                $content, 0, 10
            );
        } catch (Exception) { // @phpstan-ignore-line
            $this->delete($key);

            return $this->emptyPayload();
        }

        if (time() >= $expire) {
            $this->delete($key);

            return $this->emptyPayload();
        }

        try {
            $data = unserialize(substr($content, 10));
        } catch (Exception) { // @phpstan-ignore-line
            $this->delete($key);

            return $this->emptyPayload();
        }

        return $data;
    }

    protected function emptyPayload(): mixed
    {
        return null;
    }

    public function directory(): string
    {
        return $this->directory;
    }
}
