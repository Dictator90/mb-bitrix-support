<?php

namespace MB\Bitrix\UI\Control\Field;

use MB\Bitrix\Filesystem\Filesystem;
use MB\Container\Container;
use MB\Filesystem\Finder\PhpClassFinder;
use MB\Support\Str;
use MB\Bitrix\Contracts\UI\Renderable;
use MB\Bitrix\UI\Base\Field\AbstractBaseField;
use ReflectionClass;

/**
 * @method CalendarField createCalendar(string $name)
 * @method CurrencySelectorField createCurrencySelector(string $name)
 * @method DialogSelectorField createDialogSelector(string $name)
 * @method DropDownField createDropDown(string $name, array $options)
 * @method FileInputField createFileInput(string $name)
 * @method ImageInputField createImageInput(string $name)
 * @method HiddenField createHidden(string $name)
 * @method HtmlEditorField createHtmlEditor(string $name)
 * @method IblockElementSelectorField createIblockElementSelector(string $name, $iblockId)
 * @method IblockPropertySelectorField createIblockPropertySelector(string $name, $iblockId)
 * @method IblockSelectorField createIblockSelector(string $name)
 * @method UserField createUser(string $name)
 * @method UserSelectorField createUserSelector(string $name)
 * @method NonEditableField createNonEditable(string $name)
 * @method NoneEditableUserField createNoneEditableUser(string $name)
 * @method TextField createText(string $name)
 * @method NumberField createNumber(string $name)
 * @method PasswordField createPassword(string $name)
 * @method PhoneField createPhone(string $name)
 * @method SwitcherField createSwitcher(string $name)
 * @method StringField createString(string|int|null $value)
 */
class FieldFactory
{
    /**
     * @var Renderable[]
     */
    protected array $classList;

    public function __construct(protected Container $container)
    {
        $this->classList = Filesystem::classFinder()->extends(__DIR__, AbstractBaseField::class);
    }
    
    public function create(string $type, string $name, ...$args): ?AbstractBaseField
    {
        return $this->createByType($type, $name, ...$args);
    }
    
    public function __call(string $name, array $arguments)
    {
        if (Str::startsWith($name, 'create')) {
            $type = Str::snake(Str::substr($name, 6));
            return $this->create($type, ...$arguments);
        }

        return null;
    }

    /**
     * @param string $type
     * @param string $name
     * @param ...$args
     * @return Renderable|null
     */
    protected function createByType(string $type, string $name, ...$args): ?AbstractBaseField
    {
        $className = $this->resolveClassName($type);
        if ($className && class_exists($className)) {
            return new $className($name, ...$args);
        }

        return null;
    }

    protected function resolveClassName(string $type): ?string
    {
        foreach ($this->classList as $class) {
            $reflection = new ReflectionClass($class);

            if (
                !$reflection->isAbstract()
                && (
                    $reflection->getShortName() === Str::studly($type)
                    || $reflection->getShortName() === Str::studly($type) . 'Field'
                )
            ) {
                return $class;
            }
        }

        return null;
    }
}