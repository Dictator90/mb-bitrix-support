<?php
namespace MB\Bitrix\Page;

use Bitrix\Main\Application;
use Bitrix\Main\Page;
use MB\Bitrix\Traits\SingletonTrait;

class Asset
{
    use SingletonTrait;

    protected Page\Asset $bxAsset;

    private function __construct()
    {
        $this->bxAsset = Page\Asset::getInstance();
    }

    /**
     * @param string $path
     * @param string $location
     * @param $mode
     * @return bool
     */
    public function addJsModule(string $path, string $location = Page\AssetLocation::AFTER_JS_KERNEL, $mode = null)
    {
        return $this->addJsAdvanced($path, ['type' => 'module'], $location, $mode);
    }

    /**
     * Add Async Js asset.
     *
     * @param string $str Added path.
     * @param string $location Where string wheel be showed.
     * @param null $mode Composite mode.
     * @return boolean
     */
    public function addJsAsync(string $path, string $location = Page\AssetLocation::AFTER_JS_KERNEL, $mode = null): bool
    {
        return $this->addJsAdvanced($path, ['async' => null], $location, $mode);
    }

    /**
     * Add Defer Js asset.
     * Postpones script execution until the HTML is fully loaded.
     *
     * @param string $path
     * @param string $location
     * @param $mode
     * @return bool
     */
    public function addJsDefer(string $path, string $location = Page\AssetLocation::AFTER_JS_KERNEL, $mode = null): bool
    {
        return $this->addJsAdvanced($path, ['defer' => null], $location, $mode);
    }

    /**
     * @param string $content
     * @param $location
     * @param $mode
     * @return bool
     */
    public function addJson(string $content, $location = Page\AssetLocation::BODY_END, $mode = null)
    {
        return $this->addString("<script type=\"application/json\">$content</script>", true, $location, $mode);
    }

    /**
     * @param string $path
     * @param array|null $attr
     * @param string $location
     * @param $mode
     * @return bool
     */
    public function addJsAdvanced(string $path, array|null $attr = null, string $location = Page\AssetLocation::AFTER_JS_KERNEL, $mode = null)
    {
        if (trim($path) == '') {
            return false;
        }

        if (!$attr) {
            return $this->addJs($path);
        } else {
            $attr = $this->convertAttrToString($attr);
        }

        return $this->addString("<script src=\"{$this->checkPath($path)}\" $attr></script>", true, $location, $mode);
    }

    /**
     * Add link preload.
     * When you need the resource in a few seconds
     *
     * <link rel= "preload" href="flower.avif" as="image" type="image/avif" />
     * @param string $path
     * @return bool
     */
    public function addLinkPreload(string $path, ?string $as = null, ?string $type = null, ?string $crossorigin = null): bool
    {
        return $this->addLink($path, 'preload', ['as' => $as, 'type' => $type, 'crossorigin' => $crossorigin]);
    }

    /**
     * Add link prefetch.
     * When you need the resource on the next page
     *
     * <link rel="prefetch" href="/style.css" as="style" />
     *
     * @param string $path
     * @param string|null $as
     * @param string|null $type
     * @param string|null $crossorigin
     * @return bool
     */
    public function addLinkPrefetch(string $path, ?string $as = null, ?string $type = null, ?string $crossorigin = null): bool
    {
        return $this->addLink($path, 'prefetch', ['as' => $as, 'type' => $type, 'crossorigin' => $crossorigin]);
    }

    /**
     * Add link preconnect.
     * When you know that you will need a resource soon, but you don't know its full URL yet.
     *
     * <link rel= "preconnect" href="https://api.my-app.com" />
     *
     * @param string $path
     * @return bool
     */
    public function addLinkPreconnect(string $path)
    {
        return $this->addLink($path, 'preconnect');
    }

    /**
     * @param string $path
     * @param string $rel
     * @param array|null $exAttr
     * @return bool
     */
    public function addLink(string $path, string $rel, array|null $exAttr = null)
    {
        if (trim($path) == '' || trim($rel) == '') {
            return false;
        }

        if ($exAttr !== null) {
            $exAttr = $this->convertAttrToString($exAttr);
        }


        return $this->addString("<link rel=\"$rel\" href=\"{$this->checkPath($path)}\" $exAttr />", true, Page\AssetLocation::BEFORE_CSS);
    }

    /**
     * @param array $params
     * @param $position
     * @return bool
     */
    public function addMeta(array $params, $position = Page\AssetLocation::BEFORE_CSS): bool
    {
        $params = $this->convertAttrToString($params);
        return $this->addString("<meta $params />", true, $position);
    }

    public function addJs(string $path, $additional = false)
    {
        return $this->bxAsset->addJs($this->checkPath($path), $additional);
    }

    public function addCss(string $path, $additional = false)
    {
        return $this->bxAsset->addCss($this->checkPath($path), $additional);
    }

    public function addString($str, $unique = true, $location = Page\AssetLocation::AFTER_JS_KERNEL, $mode = null)
    {
        return $this->bxAsset->addString($str, $unique, $location, $mode);
    }

    /**
     * @param array $params
     * @return string
     */
    protected function convertAttrToString(array $params)
    {
        return implode(' ', array_map(
            fn($k, $v) => $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"',
            array_keys($params),
            $params
        ));
    }

    protected function checkPath(string $rel_path)
    {
        $extenalLink = false;
        $path = $rel_path;

        if (
            str_starts_with($rel_path, "http://")
            || str_starts_with($rel_path, "https://")
            || str_starts_with($rel_path, "//")
        ) {
            $extenalLink = true;
        }

        if (!$extenalLink) {
            $path = Includer::checkPath($path);
        }

        return $path;

    }
}