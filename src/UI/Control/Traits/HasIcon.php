<?php

namespace MB\Bitrix\UI\Control\Traits;

use MB\Bitrix\UI\Constants;

trait HasIcon
{
    public const CONT_ICON_BEFORE_CLASS = 'ui-ctl-before-icon';
    public const ICON_BEFORE_CLASS = 'ui-ctl-before';

    public const CONT_ICON_AFTER_CLASS = 'ui-ctl-after-icon';
    public const ICON_AFTER_CLASS = 'ui-ctl-after';

    public const ICON_FORWARD = 'ui-ctl-icon-forward';
    public const ICON_CHANGE = 'ui-ctl-icon-change';
    public const ICON_DOTS = 'ui-ctl-icon-dots';
    public const ICON_CALENDAR = 'ui-ctl-icon-calendar';
    public const ICON_CALENDAR_DOT = 'ui-ctl-icon-calendar-dot';
    public const ICON_CLOCK = 'ui-ctl-icon-clock';
    public const ICON_SEARCH = 'ui-ctl-icon-search';
    public const ICON_PHONE = 'ui-ctl-icon-phone';
    public const ICON_MAIL = 'ui-ctl-icon-mail';
    public const ICON_CHAIN = 'ui-ctl-icon-chain';
    public const ICON_UNCHAIN = 'ui-ctl-icon-unchain';
    public const ICON_ARROW_DOWN = 'ui-ctl-icon-arrow-down';
    public const ICON_CLOSE_SPECIAL = 'ui-ctl-icon-close-special';
    public const ICON_LOCATION = 'ui-ctl-icon-location';
    public const ICON_ANGLE = 'ui-ctl-icon-angle';
    public const ICON_CONTACT = 'ui-ctl-icon-crm-contact';
    public const ICON_LEAD = 'ui-ctl-icon-crm-lead';
    public const ICON_DEAL = 'ui-ctl-icon-crm-deal';
    public const ICON_COMPANY = 'ui-ctl-icon-crm-company';
    public const ICON_LOADER = 'ui-ctl-icon-loader';
    public const ICON_CLEAR = 'ui-ctl-icon-clear';
    public const ICON_EYE_OPENED = 'ui-ctl-icon-opened-eye';
    public const ICON_EYE_CROSSED = 'ui-ctl-icon-crossed-eye';

    protected $iconBefore = null;
    protected $iconAfter = null;

    public function setIconBefore(string $iconCss)
    {
        $this->iconBefore = $iconCss;
        return $this;
    }

    public function setIconAfter(string $iconCss)
    {
        $this->iconAfter = $iconCss;
        return $this;
    }

    public function getIconBefore()
    {
        return $this->iconBefore;
    }

    public function getIconAfter()
    {
        return $this->iconAfter;
    }

    public function hasIconBefore()
    {
        return !!$this->iconBefore;
    }

    public function hasIconAfter()
    {
        return !!$this->iconAfter;
    }

    protected function getIconBeforeHtml()
    {
        $class = self::ICON_BEFORE_CLASS;
        return <<<DOC
            <div class="{$class} {$this->getIconBefore()}"></div>
DOC;
    }

    protected function getIconAfterHtml()
    {
        $class = self::ICON_AFTER_CLASS;
        return <<<DOC
            <div class="{$class} {$this->getIconAfter()}"></div>
DOC;
    }

    protected function getContainerClass()
    {
        $cssArray = [];
        if ($this->hasIconBefore()) {
            $cssArray[] = self::CONT_ICON_BEFORE_CLASS;
        }

        if ($this->hasIconAfter()) {
            $cssArray[] = self::CONT_ICON_AFTER_CLASS;
        }

        return implode(' ', $cssArray);
    }

    protected function getIconsHtml()
    {
        $result = null;

        $result .= ($this->hasIconBefore() ? $this->getIconBeforeHtml() : '');
        $result .= ($this->hasIconAfter() ? $this->getIconAfterHtml() : '');

        return $result;
    }
}
