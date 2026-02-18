<?php
namespace MB\Bitrix\Contracts\Module;

use MB\Bitrix\Contracts\Config;

interface Entity
{
    public function getId(): string;
    public function getPath(): ?string;
    public function getLocalPath(): ?string;
    public function getLibPath(): ?string;
    public function getNamespace(): string;
    public function getConfig(string $siteId = ''): ?Config\Entity;
}