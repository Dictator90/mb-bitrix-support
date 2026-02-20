<?php

namespace MB\Bitrix\UI\Traits;

trait HasSiteId
{
    protected false|string $siteId = false;

    public function setSiteId(string $siteId): static
    {
        $this->siteId = $siteId;
        return $this;
    }

    public function getSiteId(): false|string
    {
        return $this->siteId;
    }
}
