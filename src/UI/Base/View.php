<?php

namespace MB\Bitrix\UI\Base;

/**
 * Base view for CSS-backed UI. Handles CSS generation and output only.
 * For form collections use FormContainer.
 */
abstract class View
{
    /**
     * <code>
     *     array (
     *         '.css-style' => array (
     *             'margin' => '0 12px 0 0',
     *             'color' => 'red'
     *         ),
     *         ...
     *     )
     * </code>
     *
     * @return array<string, array<string, string>>
     */
    abstract public function getCss(): array;

    protected ?array $css = null;

    public function __construct()
    {
    }

    public function showCss($withTag = true): void
    {
        if (!$this->css) {
            $this->css = static::getCss();
        }

        if ($withTag) {
            echo '<style>';
        }
        echo $this->cssArrayToString();
        if ($withTag) {
            echo '</style>';
        }
    }

    public function getDefaultCss(): array
    {
        return [];
    }

    protected function cssArrayToString()
    {
        $result = '';
        $cssArray = array_merge($this->getDefaultCss(), $this->css);

        foreach ($cssArray as $name => $values) {
            $result .= $name . '{';
            foreach ($values as $key => $value) {
                $result .= "$key:$value;";
            }
            $result .= '}' . PHP_EOL;
        }

        return $result;
    }
}
