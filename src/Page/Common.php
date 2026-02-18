<?php
namespace MB\Core\Page;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Page;
use Bitrix\Main\Request;
use Bitrix\Main\SiteTable;
use MB\Core\Support\Traits\SingletonTrait;

class Common
{
    use SingletonTrait;

    protected Request $request;
    private \Bitrix\Main\EO_Site|null $site;

    static public function current(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getInstance()
    {
        return self::current();
    }

    private function __construct()
    {
        $this->request = Context::getCurrent()->getRequest();
        $this->site = Context::getCurrent()->getSiteObject();
    }

    public function isMainPage(): bool
    {
        return $this->getPage() == $this->getMainPage();
    }

    public function isAdminPage(): bool
    {
        return $this->request->isAdminSection();
    }

    public function getMainPage()
    {
        return $this->site?->getDir() ?: '/';
    }

    public function getPage($withoutIndex = true): string
    {
        $page = $this->request->getRequestedPage();
        if ($withoutIndex) {
            if (($i = mb_strpos($page, '/index.php')) !== false) {
                $page = mb_substr($page, 0, $i) . '/';
            }
        }

        return $page;
    }
}