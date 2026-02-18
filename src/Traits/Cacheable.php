<?php

namespace MB\Bitrix\Traits;

/**
 * Статический кэш по ключу с префиксом класса.
 */
trait Cacheable
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    public static function getFromCache(string $key, $default = null)
    {
        $cacheKey = static::getCacheKey($key);
        return static::$cache[$cacheKey] ?? $default;
    }

    public static function setToCache(string $key, $value): void
    {
        $cacheKey = static::getCacheKey($key);
        static::$cache[$cacheKey] = $value;
    }

    public static function hasInCache(string $key): bool
    {
        $cacheKey = static::getCacheKey($key);
        return isset(static::$cache[$cacheKey]);
    }

    public static function removeFromCache(string $key): bool
    {
        $cacheKey = static::getCacheKey($key);
        if (isset(static::$cache[$cacheKey])) {
            unset(static::$cache[$cacheKey]);
            return true;
        }
        return false;
    }

    public static function clearCache(): void
    {
        $classPrefix = static::getCacheClassPrefix();
        foreach (array_keys(static::$cache) as $key) {
            if (strpos($key, $classPrefix) === 0) {
                unset(static::$cache[$key]);
            }
        }
    }

    /** @return array<string, mixed> */
    public static function getAllCache(): array
    {
        $result = [];
        $classPrefix = static::getCacheClassPrefix();
        foreach (static::$cache as $key => $value) {
            if (strpos($key, $classPrefix) === 0) {
                $cleanKey = substr($key, strlen($classPrefix));
                $result[$cleanKey] = $value;
            }
        }
        return $result;
    }

    /** @param array<string, mixed> $values */
    public static function setMultipleToCache(array $values): void
    {
        foreach ($values as $key => $value) {
            static::setToCache($key, $value);
        }
    }

    /**
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public static function getMultipleFromCache(array $keys, $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = static::getFromCache($key, $default);
        }
        return $result;
    }

    protected static function getCacheKey(string $key): string
    {
        return static::getCacheClassPrefix() . $key;
    }

    protected static function getCacheClassPrefix(): string
    {
        return static::class . '::';
    }
}
