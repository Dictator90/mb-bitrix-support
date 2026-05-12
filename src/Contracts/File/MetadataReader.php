<?php

declare(strict_types=1);

namespace MB\Bitrix\Contracts\File;

interface MetadataReader
{
    public function contentType(string $path): string;

    public function isImage(string $filename, string $mimeType = ''): bool;

    public function formatSize(int $size, int $precision = 2): string;

    /**
     * @param array<string,mixed> $fileData
     */
    public function hash(array $fileData): string;

    /**
     * @return array{width:int,height:int}|null
     */
    public function imageInfo(string $filePath): ?array;
}

