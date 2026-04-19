<?php

declare(strict_types=1);

namespace MB\Bitrix\Database;

/**
 * Фабрика конфигурации и короткий вход для {@see SqlLiteConnection} (подключение к пулу Bitrix или прямое создание).
 */
final class SqlLite
{
    /**
     * Конфигурация для {@see \Bitrix\Main\Data\ConnectionPool} / {@code .settings.php}: {@code className}, {@code database} и т.д.
     *
     * @param array<string, mixed> $extra Дополнительные ключи (options, initCommand, … как у {@see \Bitrix\Main\DB\Connection}).
     * @return array<string, mixed>
     */
    public static function configuration(string $database = ':memory:', array $extra = []): array
    {
        return array_merge([
            'className' => SqlLiteConnection::class,
            'database' => $database,
            'host' => '',
            'login' => '',
            'password' => '',
        ], $extra);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function connect(string $database = ':memory:', array $extra = []): SqlLiteConnection
    {
        return new SqlLiteConnection(self::configuration($database, $extra));
    }
}
