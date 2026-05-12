<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\File;

interface Uploader
{
    /**
     * @param array<string,mixed> $fileData
     */
    public function save(array &$fileData): bool;

    /**
     * @param array<string,mixed> $file
     */
    public function delete(array $file): void;
}

