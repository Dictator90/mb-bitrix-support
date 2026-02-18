<?php

namespace MB\Bitrix\Traits;

/**
 * Трейт для создания фабрики Singleton объектов
 *
 * @package MB\Core\Support\Traits
 *
 */

trait SingletonFabricTrait
{
    /**
     * @var array<string, object> Хранилище экземпляров
     */
    private static array $instances = [];

    /**
     * Получение экземпляра по ключу
     *
     * @param string $key Уникальный ключ экземпляра
     * @param callable|null $factory Фабричная функция для создания
     * @return static
     */
    public static function getInstance(string $key = 'default', ?callable $factory = null): static
    {
        if (!isset(self::$instances[$key])) {
            if ($factory) {
                self::$instances[$key] = $factory();
            } else {
                self::$instances[$key] = new static();
            }
        }

        return self::$instances[$key];
    }

    /**
     * Регистрация готового экземпляра
     *
     * @param string $key Ключ
     * @param object $instance Экземпляр
     * @return void
     */
    public static function registerInstance(string $key, object $instance): void
    {
        if (!($instance instanceof static)) {
            throw new \InvalidArgumentException(sprintf(
                'Instance must be of type %s, %s given',
                static::class,
                get_class($instance)
            ));
        }

        self::$instances[$key] = $instance;
    }

    /**
     * Проверка существования экземпляра
     *
     * @param string $key Ключ
     * @return bool
     */
    public static function hasInstance(string $key): bool
    {
        return isset(self::$instances[$key]);
    }

    /**
     * Удаление экземпляра
     *
     * @param string $key Ключ
     * @return void
     */
    public static function removeInstance(string $key): void
    {
        unset(self::$instances[$key]);
    }

    /**
     * Очистка всех экземпляров
     *
     * @return void
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }

    /**
     * Получение всех зарегистрированных экземпляров
     *
     * @return array
     */
    public static function getAllInstances(): array
    {
        return self::$instances;
    }
}
