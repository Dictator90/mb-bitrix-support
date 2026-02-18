<?php

namespace MB\Bitrix\Logger;

use Bitrix\Main\Application;
use Bitrix\Main\Diag;
use DateTime;
use MB\Bitrix\Support\Facades\Filesystem as Fs;

/**
 * Логирование в файл (по умолчанию /local/logs/{$relativeFileName})
 */
class FileLogger extends Diag\FileLogger
{
    public const LOG_FOLDER = '/local/logs/';
    public const LOG_FILE_MAX_SIZE = 1048576;
    public const LOG_FILE_ENTRY_TEMPLATE = '{date} {level} {message}' . PHP_EOL . '{context}' . PHP_EOL . PHP_EOL;
    public const LOG_FILE_DATETIME_TEMPLATE = 'Y-m-d H:i:s';

    /**
     * @param string $relativeFileName относительный путь к файлу лога, например "agents/catalogUpdate.log"
     * @param string $moduleId идентификатор модуля (для совместимости, не используется при передаче $logFolder/$logFileMaxSize)
     * @param bool $autoDate добавлять дату в путь при отсутствии расширения
     * @param string|null $logFolder каталог логов (относительно document root); при null — LOG_FOLDER
     * @param int|null $logFileMaxSize макс. размер файла в байтах; при null — LOG_FILE_MAX_SIZE
     */
    public function __construct(
        string $relativeFileName,
        bool $autoDate = true,
        ?string $logFolder = null,
        ?int $logFileMaxSize = null
    ) {
        $logFolder = $logFolder ?? self::LOG_FOLDER;
        $logFileMaxSize = $logFileMaxSize ?? self::LOG_FILE_MAX_SIZE;

        $hasExtension = pathinfo($relativeFileName, PATHINFO_EXTENSION) !== '';
        if ($autoDate && !$hasExtension) {
            $fileNamePath = rtrim($relativeFileName, '/') . '/' . date('Y-m-d') . '.log';
        } else {
            $fileNamePath = $relativeFileName;
        }

        $relativeDir = rtrim($logFolder . dirname($fileNamePath), '/');
        Fs::makeDirectory($relativeDir, 0755, true);

        $absolutePath = Application::getDocumentRoot() . $logFolder . $fileNamePath;

        parent::__construct($absolutePath, $logFileMaxSize);
    }

    public function getSiteLogFileName(): string
    {
        return str_replace(Application::getDocumentRoot(), '', $this->getAbsoluteLogFileName());
    }

    public function getAbsoluteLogFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param string $level константа Psr\Log\LogLevel или MB\Bitrix\Logger\LogLevel
     * @param string $message текст сообщения
     */
    protected function logMessage(string $level, string $message): void
    {
        $message = $this->prepareFileEntry($level, $message, $this->context ?? []);
        parent::logMessage($level, $message);
    }

    /**
     * @param array|null $context
     */
    protected function prepareFileEntry(string $level, string $message, ?array $context = null): string
    {
        return strtr(self::LOG_FILE_ENTRY_TEMPLATE, [
            '{date}' => $this->getDate(),
            '{level}' => strtoupper($level),
            '{message}' => $message,
            '{context}' => $this->contextStringify($context ?? [])
        ]);
    }

    protected function getDate(): string
    {
        return (new DateTime())->format(self::LOG_FILE_DATETIME_TEMPLATE);
    }

    /**
     * @param array $context
     */
    protected function contextStringify(array $context = []): string
    {
        if (empty($context)) {
            return '';
        }
        return var_export($context, true);
    }
}

