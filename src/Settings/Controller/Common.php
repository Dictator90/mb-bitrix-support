<?php
namespace MB\Bitrix\Settings\Controller;

use Bitrix\Main\Error;
use Bitrix\Main\UI\Extension;
use MB\Bitrix\Settings\Options\Base;
use MB\Bitrix\UI\Control\TabSet\BitrixTabSet;
use MB\Bitrix\UI\Control\Form\Bitrix as FormBitrix;

class Common extends \Bitrix\Main\Engine\Controller
{
    public function getSettingsAction(string $optionsClass)
    {
        if (! class_exists($optionsClass) || ! is_subclass_of($optionsClass, Base::class)) {
            $this->addError(new Error("{$optionsClass} not exist or not instance of " . Base::class));

            return null;
        }

        /**
         * @var class-string<Base> $optionsClass
         * @var FormBitrix $form
         */

        $formClass = $optionsClass::getFormClass();
        $formId = str_replace('\\', '_', $optionsClass);
        $form = new $formClass($formId, false);
        if ($form->getJsExtensions()) {
            Extension::load($form->getJsExtensions());
        }
        $tabset = new BitrixTabSet($optionsClass::getMap());
        $form->setTabSet($tabset);

        return $form->toJson();
    }
}
