<?php

namespace MB\Bitrix\Traits;

/**
 * @method static remember(string $key, callable $callback, ?int $ttl = null)
 */
trait RememberCachable
{
    /**
     * @var array Хранилище кэша
     */
    protected static array $cache = [];

    /**
     * Сохраняет значение в кэш
     *
     * @param string $key Ключ кэша
     * @param mixed $value Значение для сохранения
     * @param int|null $ttl Время жизни в секундах (null = бесконечно)
     * @return self
     */
    public function setCache(string $key, $value, ?int $ttl = null): static
    {
        static::$cache[$key] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null
        ];

        return $this;
    }

    /**
     * Получает значение из кэша
     *
     * @param string $key Ключ кэша
     * @param mixed $default Значение по умолчанию, если ключ не найден или истек
     * @return mixed
     */
    public function getCache(string $key, $default = null)
    {
        if (!$this->hasCache($key)) {
            return $default;
        }

        return static::$cache[$key]['value'];
    }

    /**
     * Проверяет наличие ключа в кэше и его актуальность
     *
     * @param string $key Ключ кэша
     * @return bool
     */
    public function hasCache(string $key): bool
    {
        if (!isset(static::$cache[$key])) {
            return false;
        }

        $expires = static::$cache[$key]['expires'];

        if ($expires !== null && $expires < time()) {
            $this->removeCache($key);
            return false;
        }

        return true;
    }

    /**
     * Удаляет значение из кэша
     *
     * @param string $key Ключ кэша
     * @return self
     */
    public function removeCache(string $key): self
    {
        unset(static::$cache[$key]);
        return $this;
    }

    /**
     * Очищает весь кэш
     *
     * @return self
     */
    public function clearCache(): self
    {
        static::$cache = [];
        return $this;
    }

    /**
     * Получает значение из кэша или вычисляет его через callback
     *
     * @param string $key Ключ кэша
     * @param callable $callback Функция для вычисления значения, если его нет в кэше
     * @param int|null $ttl Время жизни в секундах
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->hasCache($key)) {
            return $this->getCache($key);
        }

        $value = $callback();
        $this->setCache($key, $value, $ttl);

        return $value;
    }

    /**
     * Получает все ключи кэша
     *
     * @return array
     */
    public function getCacheKeys(): array
    {
        return array_keys(static::$cache);
    }

    /**
     * Получает статистику кэша
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        $stats = [
            'total_items' => count(static::$cache),
            'items' => []
        ];

        foreach (static::$cache as $key => $item) {
            $stats['items'][$key] = [
                'expires' => $item['expires'],
                'expires_in' => $item['expires'] !== null
                    ? max(0, $item['expires'] - time())
                    : null,
                'type' => gettype($item['value'])
            ];
        }

        return $stats;
    }

    /**
     * Сохраняет множество значений в кэш
     *
     * @param array $items Ассоциативный массив [key => value]
     * @param int|null $ttl Время жизни в секундах
     * @return self
     */
    public function setMultipleCache(array $items, ?int $ttl = null): self
    {
        foreach ($items as $key => $value) {
            $this->setCache($key, $value, $ttl);
        }

        return $this;
    }

    /**
     * Получает множество значений из кэша
     *
     * @param array $keys Массив ключей
     * @return array
     */
    public function getMultipleCache(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if ($this->hasCache($key)) {
                $result[$key] = $this->getCache($key);
            }
        }

        return $result;
    }

    /**
     * Удаляет множество значений из кэша
     *
     * @param array $keys Массив ключей
     * @return self
     */
    public function removeMultipleCache(array $keys): self
    {
        foreach ($keys as $key) {
            $this->removeCache($key);
        }

        return $this;
    }

    /**
     * Инкрементирует числовое значение в кэше
     *
     * @param string $key Ключ кэша
     * @param int $step Шаг инкремента
     * @return int Новое значение
     * @throws \InvalidArgumentException
     */
    public function incrementCache(string $key, int $step = 1): int
    {
        if (!$this->hasCache($key)) {
            $this->setCache($key, 0);
        }

        $value = $this->getCache($key);

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Cache value for key '{$key}' is not numeric");
        }

        $newValue = $value + $step;
        $this->setCache($key, $newValue, static::$cache[$key]['expires']);

        return $newValue;
    }

    /**
     * Декрементирует числовое значение в кэше
     *
     * @param string $key Ключ кэша
     * @param int $step Шаг декремента
     * @return int Новое значение
     * @throws \InvalidArgumentException
     */
    public function decrementCache(string $key, int $step = 1): int
    {
        return $this->incrementCache($key, -$step);
    }

    public static function __callStatic($name, $arguments)
    {
        if ($name === 'remember') {
            (new static())->remember(...$arguments);
        }
    }
}