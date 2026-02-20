<?php
namespace MB\Core\Settings\Controller;

use Bitrix\Main\Error;
use Bitrix\Main\UI\Extension;
use MB\Core\Settings\Options\Base;
use MB\Core\UI\Control;
use MB\Core\UI\Reference\Form;

//use MB\Core\UI\Entity\Form;

class Common extends \Bitrix\Main\Engine\Controller
{
    public function getSettingsAction(string $optionsClass)
    {
        if (!class_exists($optionsClass) || !$optionsClass instanceof Base) {
            $this->addError(new Error("{$optionsClass} not exist or not instance of " . Base::class));
        }

        /**
         * @var Base $optionsClass
         * @var Form\Base $form
         */

        $formClass = $optionsClass::getFormClass();
        $form = new $formClass($optionsClass::getModuleId());
        if ($form->getJsExtensions()) {
            Extension::load($form->getJsExtensions());
        }
        $tabset = new Control\TabSet\BitrixTabSet($optionsClass::getMap());
        $form->setTabSet($tabset);

        return $form->toJson();
    }
}
