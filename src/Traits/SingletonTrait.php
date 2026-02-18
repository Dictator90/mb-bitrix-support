<?php

namespace MB\Bitrix\Traits;

/**
 * Трейт для реализации паттерна Singleton
 *
 * @package MB\Core\Support\Traits
 */

trait SingletonTrait
{
    /**
     * @var static|null Единственный экземпляр класса
     */
    private static ?self $instance = null;

    /**
     * Защищенный конструктор для предотвращения создания через new
     */
    private function __construct()
    {
        // Инициализация при необходимости
    }

    /**
     * Запрещаем клонирование
     */
    private function __clone()
    {
        // Предотвращаем клонирование
    }

    /**
     * Запрещаем десериализацию
     */
    public function __wakeup()
    {
        throw new \RuntimeException("Cannot unserialize singleton");
    }

    /**
     * Получение единственного экземпляра
     *
     * @return static
     */
    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Сброс инстанса (для тестирования)
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}