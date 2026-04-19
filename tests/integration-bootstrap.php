<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$bitrixRoot = $projectRoot . '/vendor/avshatalov48/bitrix-core-business';

require $projectRoot . '/vendor/autoload.php';

if (! is_dir($bitrixRoot)) {
    fwrite(STDERR, "Bitrix core package is not installed.\n");
    exit(1);
}

if (shouldBootstrapBitrixProlog()) {
    bootstrapBitrixProlog($projectRoot, $bitrixRoot);

    return;
}

bootstrapBitrixMainClasses($bitrixRoot);

if (! function_exists('module')) {
    function module(string $id): object
    {
        return new class {
            public function getLang(string $code, ?array $replaces = null, ?string $fallback = null, ?string $lang = null): string
            {
                return $fallback ?? $code;
            }
        };
    }
}

function shouldBootstrapBitrixProlog(): bool
{
    $flag = getenv('BITRIX_INTEGRATION_USE_PROLOG');

    return $flag === '1' || strtolower((string)$flag) === 'true';
}

function bootstrapBitrixMainClasses(string $bitrixRoot): void
{
    // Real D7 types used by integration smoke tests, without full site bootstrap.
    require_once $bitrixRoot . '/modules/main/lib/localization/localizablemessageinterface.php';
    require_once $bitrixRoot . '/modules/main/lib/type/dictionary.php';
    require_once $bitrixRoot . '/modules/main/lib/error.php';
    require_once $bitrixRoot . '/modules/main/lib/errorcollection.php';
    require_once $bitrixRoot . '/modules/main/lib/db/sqlexpression.php';
    require_once $bitrixRoot . '/modules/main/lib/result.php';
}

function bootstrapBitrixProlog(string $projectRoot, string $bitrixRoot): void
{
    $runtimeRoot = $projectRoot . '/tests/.runtime/bitrix-docroot';
    $bitrixLink = $runtimeRoot . '/bitrix';
    $localDir = $runtimeRoot . '/local';
    $phpInterfaceDir = $localDir . '/php_interface';
    $runtimeDirs = [
        $runtimeRoot . '/upload',
        $runtimeRoot . '/bitrix/cache',
        $runtimeRoot . '/bitrix/managed_cache',
        $runtimeRoot . '/bitrix/stack_cache',
        $runtimeRoot . '/bitrix/tmp',
        $runtimeRoot . '/sqlite',
    ];
    $prologBefore = $bitrixLink . '/modules/main/include/prolog_before.php';

    foreach ([$runtimeRoot, $localDir, $phpInterfaceDir, ...$runtimeDirs] as $dir) {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    if (! file_exists($bitrixLink)) {
        if (! @symlink($bitrixRoot, $bitrixLink)) {
            if (DIRECTORY_SEPARATOR === '\\') {
                $target = str_replace('/', '\\', $bitrixRoot);
                $link = str_replace('/', '\\', $bitrixLink);
                exec(sprintf('cmd /c mklink /J "%s" "%s"', $link, $target), $output, $exitCode);
                if ($exitCode !== 0 && ! is_dir($bitrixLink)) {
                    fwrite(STDERR, "Unable to create Bitrix junction for integration bootstrap.\n");
                    exit(1);
                }
            } else {
                fwrite(STDERR, "Unable to create Bitrix symlink for integration bootstrap.\n");
                exit(1);
            }
        }
    }

    $dbHost = getenv('BITRIX_DB_HOST') ?: 'localhost';
    $dbName = getenv('BITRIX_DB_NAME') ?: 'bitrix';
    $dbLogin = getenv('BITRIX_DB_LOGIN') ?: 'root';
    $dbPassword = getenv('BITRIX_DB_PASSWORD') ?: '';

    $useSqlite = getenv('BITRIX_USE_SQLITE');
    $sqliteEnabled = $useSqlite === '1' || strtolower((string) $useSqlite) === 'true';
    $sqlitePath = getenv('BITRIX_SQLITE_PATH') ?: ($runtimeRoot . '/sqlite/bitrix.sqlite');

    $settings = [
        'utf_mode' => [
            'value' => true,
            'readonly' => true,
        ],
        'cache' => [
            'value' => [
                'type' => 'files',
            ],
            'readonly' => false,
        ],
        'cache_flags' => [
            'value' => [
                'config_options' => 3600,
                'site_domain' => 3600,
            ],
            'readonly' => false,
        ],
        'cookies' => [
            'value' => [
                'secure' => false,
                'http_only' => true,
            ],
            'readonly' => false,
        ],
        'exception_handling' => [
            'value' => [
                'debug' => true,
                'handled_errors_types' => E_ALL & ~E_DEPRECATED,
                'exception_errors_types' => E_ALL & ~E_DEPRECATED,
                'ignore_silence' => false,
                'assertion_throws_exception' => true,
                'assertion_error_type' => E_USER_ERROR,
                'log' => [
                    'settings' => [
                        'file' => '/bitrix-error.log',
                        'log_size' => 1000000,
                    ],
                ],
            ],
            'readonly' => false,
        ],
        'connections' => [
            'value' => [
                'default' => $sqliteEnabled
                    ? [
                        'className' => '\\MB\\Bitrix\\Database\\SqlLiteConnection',
                        'host' => '',
                        'database' => $sqlitePath,
                        'login' => '',
                        'password' => '',
                        'options' => 2,
                    ]
                    : [
                        'className' => '\\Bitrix\\Main\\DB\\MysqliConnection',
                        'host' => $dbHost,
                        'database' => $dbName,
                        'login' => $dbLogin,
                        'password' => $dbPassword,
                        'options' => 2,
                    ],
            ],
            'readonly' => true,
        ],
        'composer' => [
            'value' => [
                'config_path' => $projectRoot . '/composer.json',
            ],
            'readonly' => true,
        ],
    ];

    file_put_contents(
        $localDir . '/.settings.php',
        "<?php\nreturn " . var_export($settings, true) . ";\n"
    );

    if ($sqliteEnabled) {
        file_put_contents(
            $phpInterfaceDir . '/dbconn.php',
            <<<PHP
<?php
\$DBType = 'sqlite';
\$DBDebug = false;
\$DBDebugToFile = false;
\$DBHost = '';
\$DBName = '{$sqlitePath}';
\$DBLogin = '';
\$DBPassword = '';
PHP
        );
    } else {
        file_put_contents(
            $phpInterfaceDir . '/dbconn.php',
            <<<PHP
<?php
\$DBType = 'mysql';
\$DBDebug = false;
\$DBDebugToFile = false;
\$DBHost = '{$dbHost}';
\$DBName = '{$dbName}';
\$DBLogin = '{$dbLogin}';
\$DBPassword = '{$dbPassword}';
PHP
        );
    }

    $_SERVER['DOCUMENT_ROOT'] = $runtimeRoot;
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['SCRIPT_NAME'] = '/bitrix/modules/main/include/prolog_before.php';
    $_SERVER['SCRIPT_FILENAME'] = $prologBefore;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTPS'] = 'off';

    $DOCUMENT_ROOT = $runtimeRoot;
    putenv('DOCUMENT_ROOT=' . $runtimeRoot);

    if (! defined('NO_KEEP_STATISTIC')) {
        define('NO_KEEP_STATISTIC', true);
    }

    if (! defined('NOT_CHECK_PERMISSIONS')) {
        define('NOT_CHECK_PERMISSIONS', true);
    }

    if (! defined('BX_COMPRESSION_DISABLED')) {
        define('BX_COMPRESSION_DISABLED', true);
    }

    if (! defined('LANGUAGE_ID')) {
        define('LANGUAGE_ID', 'ru');
    }

    error_reporting(E_ALL & ~E_DEPRECATED);

    require_once $prologBefore;
}
