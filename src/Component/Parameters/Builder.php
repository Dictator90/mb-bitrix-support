<?php

namespace MB\Bitrix\Component\Parameters;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\UserFieldTable;
use MB\Bitrix\Contracts\Component\ConditionInterface;
use MB\Bitrix\Contracts\Component\LangProviderInterface;
use MB\Support\Collection;

/**
 * Построитель PARAMETERS и GROUPS для component.php.
 * Условия: array [[key, operator, value], ...] или callable(array $values): bool или ConditionInterface.
 */
class Builder
{
    use UseIblock;
    use UseForm;
    use UseAgreement;

    protected ?Collection $params = null;
    protected ?Collection $groups = null;
    protected ?Collection $values = null;

    protected $request;

    /** @var LangProviderInterface|null */
    protected $langProvider = null;

    /**
     * @param array{array} $array ['PARAMETERS' => [], 'GROUPS' => []]
     * @param array $values текущие значения параметров
     */
    public function __construct(array $array = [], array $values = [])
    {
        Loader::includeModule('iblock');
        Loader::includeModule('form');

        $this->params = new Collection($array['PARAMETERS'] ?? []);
        $this->groups = new Collection($array['GROUPS'] ?? []);
        $this->values = new Collection($values);
        $this->request = Context::getCurrent()->getRequest();
    }

    public function setLangProvider(?LangProviderInterface $langProvider): static
    {
        $this->langProvider = $langProvider;
        return $this;
    }

    protected function message(string $code, ?array $replace = null): string
    {
        if ($this->langProvider !== null) {
            return $this->langProvider->getLang($code, $replace);
        }
        return $code;
    }

    /**
     * @param string $name
     * @param array $params
     * @param array|callable|ConditionInterface|null $conditions array of [key, operator, value] or callable(array $values): bool or ConditionInterface
     */
    public function addParam($name, $params, $conditions = null): static
    {
        if ($conditions !== null && (is_array($conditions) || is_callable($conditions) || $conditions instanceof ConditionInterface)) {
            $this->addParamByCondition($name, $params, $conditions);
        } else {
            $this->params->offsetSet($name, $params);
        }
        return $this;
    }

    /**
     * @param array|callable(array):bool|ConditionInterface $conditions
     */
    public function addParamByCondition(string $name, array $params, array|callable|ConditionInterface $conditions): static
    {
        $pass = false;
        if (is_callable($conditions)) {
            $pass = $conditions($this->values->all());
        } elseif ($conditions instanceof ConditionInterface) {
            $pass = $conditions->evaluate($this->values->all());
        } elseif (is_array($conditions)) {
            foreach ($conditions as $condition) {
                if (is_string($condition[0] ?? null) && ($targetParams = $this->params->get($condition[0]))) {
                    if ($condition[0] !== 'SEF_MODE') {
                        $targetParams['REFRESH'] = 'Y';
                        $this->params->offsetSet($condition[0], $targetParams);
                    }
                }
            }
            $pass = $this->evaluateArrayConditions($conditions);
        }

        if ($pass) {
            $this->params->offsetSet($name, $params);
        } else {
            if ($this->values->get($name) !== null) {
                $this->values->offsetSet($name, $params['DEFAULT'] ?? null);
            }
        }
        return $this;
    }

    /**
     * Evaluate conditions in format [[key, operator, value], ...]
     */
    protected function evaluateArrayConditions(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (!is_array($condition) || !isset($condition[0], $condition[1])) {
                return false;
            }
            $key = $condition[0];
            $operator = $condition[1];
            $expected = $condition[2] ?? null;
            $targetParams = $this->params->get($key);
            $actual = $this->values->get($key, $targetParams['DEFAULT'] ?? null);

            if (!$this->compare($actual, $operator, $expected)) {
                return false;
            }
        }
        return true;
    }

    protected function compare($actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case '=':
            case '==':
                return $actual == $expected;
            case '!=':
            case '<>':
                return $actual != $expected;
            case '>':
                return $actual > $expected;
            case '>=':
                return $actual >= $expected;
            case '<':
                return $actual < $expected;
            case '<=':
                return $actual <= $expected;
            default:
                return false;
        }
    }

    public function addGroup(string $name, array $params): static
    {
        $this->groups->offsetSet($name, $params);
        return $this;
    }

    public function getParam(string $name, $default = null)
    {
        return $this->params->get($name, $default);
    }

    public function getGroud(string $name, $default = null)
    {
        return $this->groups->get($name, $default);
    }

    public function getValue(string $name, $default = null)
    {
        return $this->values->get($name, $default);
    }

    public function addIblockParams(): static
    {
        $this->addListParam('IBLOCK_TYPE', [
            'PARENT' => 'BASE',
            'NAME' => $this->message('MB_CORE_COMP_PARAM_IBLOCK_TYPE'),
            'VALUES' => self::getIblockTypes(),
            'REFRESH' => 'Y',
            'SORT' => 100,
        ]);

        $this->addListParam('IBLOCK_ID', [
            'PARENT' => 'BASE',
            'NAME' => $this->message('MB_CORE_COMP_PARAM_IBLOCK_ID'),
            'VALUES' => self::getIblocks($this->values->get('IBLOCK_TYPE')),
            'REFRESH' => 'Y',
            'SORT' => 110,
        ], [['IBLOCK_TYPE', '!=', '0']]);

        return $this;
    }

    public function addIblockElementFields(): static
    {
        $this->addParam('FIELD_CODE', \CIBlockParameters::GetFieldCode($this->message('MB_CORE_COMP_PARAM_FIELD_CODE'), 'DATA_SOURCE'));
        return $this;
    }

    public function add404Settings(bool $bStatus = true, bool $bPage = true): static
    {
        $settingsGroup = '404_SETTINGS';
        $this->addGroup($settingsGroup, ['NAME' => $this->message('IB_COMPLIB_PARAMETER_GROUP_404_SETTINGS')]);

        if ($bStatus) {
            $this->addCheckboxParam('SET_STATUS_404', [
                'PARENT' => $settingsGroup,
                'NAME' => $this->message('IB_COMPLIB_PARAMETER_SET_STATUS_404'),
                'DEFAULT' => 'N',
            ]);
        }

        if ($bPage) {
            $this->addParam('SHOW_404', [
                'PARENT' => $settingsGroup,
                'NAME' => $this->message('IB_COMPLIB_PARAMETER_SHOW_404'),
                'TYPE' => 'CHECKBOX',
                'DEFAULT' => 'N',
                'REFRESH' => 'Y',
            ]);
        }

        if ($bPage) {
            $this->addStringParam('FILE_404', [
                'PARENT' => $settingsGroup,
                'NAME' => $this->message('IB_COMPLIB_PARAMETER_FILE_404'),
                'DEFAULT' => '',
            ], [['SHOW_404', '=', 'Y']]);
        }

        $this->addStringParam('MESSAGE_404', [
            'PARENT' => $settingsGroup,
            'NAME' => $this->message('IB_COMPLIB_PARAMETER_MESSAGE_404'),
            'DEFAULT' => '',
        ], [['SHOW_404', '!=', 'Y']]);

        return $this;
    }

    public function addIblockElementProperties(): static
    {
        $iblockId = $this->values->get('IBLOCK_ID');
        if ($iblockId) {
            $values = self::getIblockProperties($iblockId);
            $this->addListParam('PROPERTY_CODE', [
                'PARENT' => 'DATA_SOURCE',
                'NAME' => $this->message('MB_CORE_COMP_PARAM_PROPS_CODE'),
                'MULTIPLE' => 'Y',
                'VALUES' => $values,
            ]);
        }
        return $this;
    }

    public function addPagerSettings($pagerTitle = null, bool $bDescNumbering = true, bool $bShowAllParam = false): static
    {
        $arHiddenTemplates = ['js' => true];
        $pagerSettingsGroup = 'PAGER_SETTINGS';
        $this->addGroup($pagerSettingsGroup, ['NAME' => $this->message('T_IBLOCK_DESC_PAGER_SETTINGS')]);

        $arTemplateInfo = \CComponentUtil::GetTemplatesList('bitrix:main.pagenavigation');
        if (empty($arTemplateInfo)) {
            $this->addParam('PAGER_TEMPLATE', [
                'PARENT' => $pagerSettingsGroup,
                'NAME' => $this->message('T_IBLOCK_DESC_PAGER_TEMPLATE'),
                'TYPE' => Type::STRING,
                'DEFAULT' => '',
            ]);
        } else {
            $arTemplateInfo = collect($arTemplateInfo)->sortBy([['TEMPLATE', 'asc'], ['NAME', 'asc']])->all();
            $arTemplateList = [];
            $arSiteTemplateList = ['.default' => $this->message('T_IBLOCK_DESC_PAGER_TEMPLATE_SITE_DEFAULT')];
            $arTemplateID = [];
            foreach ($arTemplateInfo as &$template) {
                if ('' != $template['TEMPLATE'] && '.default' != $template['TEMPLATE']) {
                    $arTemplateID[] = $template['TEMPLATE'];
                }
                if (!isset($template['TITLE'])) {
                    $template['TITLE'] = $template['NAME'];
                }
            }
            unset($template);

            if (!empty($arTemplateID)) {
                $rsSiteTemplates = \CSiteTemplate::GetList([], ['ID' => $arTemplateID], []);
                while ($arSitetemplate = $rsSiteTemplates->Fetch()) {
                    $arSiteTemplateList[$arSitetemplate['ID']] = $arSitetemplate['NAME'];
                }
            }

            foreach ($arTemplateInfo as &$template) {
                if (isset($arHiddenTemplates[$template['NAME']])) {
                    continue;
                }
                $strDescr = $template['TITLE'] . ' (' . ('' != $template['TEMPLATE'] && isset($arSiteTemplateList[$template['TEMPLATE']]) && $arSiteTemplateList[$template['TEMPLATE']] !== '' ? $arSiteTemplateList[$template['TEMPLATE']] : $this->message('T_IBLOCK_DESC_PAGER_TEMPLATE_SYSTEM')) . ')';
                $arTemplateList[$template['NAME']] = $strDescr;
            }
            unset($template);

            $this->addParam('PAGER_TEMPLATE', [
                'PARENT' => $pagerSettingsGroup,
                'NAME' => $this->message('T_IBLOCK_DESC_PAGER_TEMPLATE_EXT'),
                'TYPE' => Type::LIST,
                'VALUES' => $arTemplateList,
                'DEFAULT' => '.default',
                'ADDITIONAL_VALUES' => 'Y',
            ]);
        }

        $this->addCheckboxParam('DISPLAY_TOP_PAGER', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('T_IBLOCK_DESC_TOP_PAGER'),
            'DEFAULT' => 'N',
        ]);
        $this->addCheckboxParam('DISPLAY_BOTTOM_PAGER', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('T_IBLOCK_DESC_BOTTOM_PAGER'),
            'DEFAULT' => 'Y',
        ]);
        $this->addStringParam('PAGER_NAV_NAME', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('MB_CORE_COMP_PAGER_NAV_NAME'),
            'DEFAULT' => 'nav',
        ]);
        $this->addCheckboxParam('PAGER_SHOW_ALWAYS', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('T_IBLOCK_DESC_PAGER_SHOW_ALWAYS'),
            'DEFAULT' => 'N',
        ]);

        if ($bDescNumbering) {
            $this->addCheckboxParam('PAGER_DESC_NUMBERING', [
                'PARENT' => $pagerSettingsGroup,
                'NAME' => $this->message('T_IBLOCK_DESC_PAGER_DESC_NUMBERING'),
                'DEFAULT' => 'N',
            ]);
            $this->addStringParam('PAGER_DESC_NUMBERING_CACHE_TIME', [
                'PARENT' => $pagerSettingsGroup,
                'NAME' => $this->message('T_IBLOCK_DESC_PAGER_DESC_NUMBERING_CACHE_TIME'),
                'DEFAULT' => '36000',
            ]);
        }

        if ($bShowAllParam) {
            $this->addCheckboxParam('PAGER_SHOW_ALL', [
                'PARENT' => $pagerSettingsGroup,
                'NAME' => $this->message('T_IBLOCK_DESC_SHOW_ALL'),
                'DEFAULT' => 'N',
            ]);
        }

        $this->addCheckboxParam('SHOW_COUNT', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('MB_CORE_COMP_PAGER_SHOW_COUNT'),
            'DEFAULT' => 'N',
        ]);

        $this->addCheckboxParam('PAGER_SEF_MODE', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('MB_EL_IBLOCK_PAGER_SEF_MODE'),
            'HIDDEN' => 'Y',
            'DEFAULT' => 'N',
        ], [['SEF_MODE', '=', 'N']]);

        $this->addCheckboxParam('PAGER_SEF_MODE', [
            'PARENT' => $pagerSettingsGroup,
            'NAME' => $this->message('MB_EL_IBLOCK_PAGER_SEF_MODE'),
            'DEFAULT' => 'N',
        ], [['SEF_MODE', '=', 'Y']]);

        return $this;
    }

    public function addUserFields(string $entityId): static
    {
        $userFieldsValues = [];
        $rows = UserFieldTable::query()
            ->setSelect(['FIELD_NAME', 'LABELS'])
            ->where('ENTITY_ID', $entityId)
            ->registerRuntimeField(UserFieldTable::getLabelsReference())
            ->fetchCollection();

        foreach ($rows as $row) {
            $label = $row->get('LABELS')->get('EDIT_FORM_LABEL');
            $userFieldsValues[$row->getFieldName()] = $label . ' [' . $row->getFieldName() . ']';
        }

        $this->addParam('USER_FIELD_ID', [
            'PARENT' => 'BASE',
            'NAME' => 'Пользовательские свойства',
            'TYPE' => 'LIST',
            'VALUES' => $userFieldsValues,
        ]);
        return $this;
    }

    public function addSefMode(array $templates = [], array $variablesAliases = []): static
    {
        $this->addParam('SEF_MODE', $templates);

        if ($variablesAliases !== []) {
            $this->addParam('VARIABLE_ALIASES', $variablesAliases, [['SEF_MODE', '=', 'Y']]);
        }
        return $this;
    }

    public function addForms(): static
    {
        $this->addListParam('FORM_ID', [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => $this->message('MB_CORE_COMP_PARAM_FORM_ID'),
            'MULTIPLE' => 'N',
            'VALUES' => self::getFormList(),
            'REFRESH' => 'Y',
        ]);
        return $this;
    }

    public function addUserConsent(): static
    {
        $this->addGroup('USERCONSENT', [
            'NAME' => $this->message('MB_CORE_COMP_GROUP_USERCONSENT'),
            'SORT' => 300,
        ]);

        $this->addCheckboxParam('USE_USERCONSENT', [
            'NAME' => $this->message('MB_CORE_COMP_PARAMS_USE_USERCONSENT'),
            'PARENT' => 'USERCONSENT',
            'REFRESH' => 'Y',
        ]);

        $agreementList = self::getAgreementList();
        $this->addListParam('AGREEMENT', [
            'PARENT' => 'USERCONSENT',
            'NAME' => $this->message('MB_CORE_COMP_PARAMS_AGREEMENT'),
            'MULTIPLE' => 'Y',
            'VALUES' => $agreementList,
            'REFRESH' => 'Y',
        ], [['USE_USERCONSENT', '=', 'Y']]);

        $paramPrefix = 'AGREEMENT_';
        foreach ($this->getValue('AGREEMENT', []) as $agreement) {
            if ((int) $agreement === 0) {
                continue;
            }
            $group = $paramPrefix . 'GROUP_' . $agreement;
            $this->addGroup($group, [
                'NAME' => $this->message('MB_CORE_COMP_PARAMS_AGREEMENT_GROUP', ['#NAME#' => $agreementList[$agreement] ?? $agreement]),
                'SORT' => 300,
            ]);
            $this->addCheckboxParam($paramPrefix . 'CHECKED_' . $agreement, [
                'NAME' => $this->message('MB_CORE_COMP_PARAMS_AGREEMENT_CHECHED_LABEL'),
                'PARENT' => $group,
            ], [['USE_USERCONSENT', '=', 'Y']]);
            $this->addStringParam($paramPrefix . 'SORT_' . $agreement, [
                'NAME' => $this->message('MB_CORE_COMP_PARAMS_AGREEMENT_SORT'),
                'PARENT' => $group,
                'DEFAULT' => 100,
            ], [['USE_USERCONSENT', '=', 'Y']]);
            $this->addCheckboxParam($paramPrefix . 'CUSTOM_' . $agreement, [
                'NAME' => $this->message('MB_CORE_COMP_PARAMS_AGREEMENT_CUSTOM'),
                'PARENT' => $group,
                'REFRESH' => 'Y',
            ], [['USE_USERCONSENT', '=', 'Y']]);
            $this->addStringParam($paramPrefix . 'LABEL_' . $agreement, [
                'NAME' => $this->message('MB_CORE_COMP_PARAMS_AGREEMENT_CUSTOM_LABEL'),
                'PARENT' => $group,
                'ROWS' => 3,
                'COLS' => 50,
            ], [['USE_USERCONSENT', '=', 'Y'], [$paramPrefix . 'CUSTOM_' . $agreement, '=', 'Y']]);
        }

        return $this;
    }

    public function addCache(): static
    {
        $this->addParam('CACHE_TIME', ['DEFAULT' => 3600]);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'GROUPS' => $this->groups->toArray(),
            'PARAMETERS' => $this->params->toArray(),
        ];
    }

    public function getComponentParamsArray(): array
    {
        return $this->toArray();
    }

    public function getCurrentValuesArray(): array
    {
        return $this->values->toArray();
    }

    /** @param array|callable|ConditionInterface|null $conditions */
    public function addStringParam(string $name, array $params = [], $conditions = null): static
    {
        $params['TYPE'] = Type::STRING;
        $this->addParam($name, $params, $conditions);
        return $this;
    }

    /** @param array|callable|ConditionInterface|null $conditions */
    public function addCheckboxParam(string $name, array $params = [], $conditions = null): static
    {
        $params['TYPE'] = Type::CHECKBOX;
        $params['DEFAULT'] = $params['DEFAULT'] ?? 'N';
        $this->addParam($name, $params, $conditions);
        return $this;
    }

    /** @param array|callable|ConditionInterface|null $conditions */
    public function addListParam(string $name, array $params = [], $conditions = null): static
    {
        $params['TYPE'] = Type::LIST;
        if (empty($params['VALUES']) && $conditions === null) {
            throw new \InvalidArgumentException('param `VALUES` must be filled array etc. [\'VALUE\' => \'DESCRIPTION\']');
        }
        $this->addParam($name, $params, $conditions);
        return $this;
    }

    /** @param array|callable|ConditionInterface|null $conditions */
    public function addFileParam(string $name, array $params = [], $conditions = null): void
    {
        $params['TYPE'] = Type::FILE;
        $this->addParam($name, $params, $conditions);
    }

    /** @param array|callable|ConditionInterface|null $conditions */
    public function addTemplatesParam(string $name, array $params = [], $conditions = null): static
    {
        $params['TYPE'] = Type::TEMPLATES;
        $this->addParam($name, $params, $conditions);
        return $this;
    }

    /** @param array|callable|ConditionInterface|null $conditions */
    public function addCustomParam(string $name, array $params = [], $conditions = null): static
    {
        $params['TYPE'] = Type::CUSTOM;
        if (empty($params['JS_FILE']) || empty($params['JS_EVENT'])) {
            throw new \InvalidArgumentException('param `JS_FILE` & `JS_EVENT` cant be empty');
        }
        $this->addParam($name, $params, $conditions);
        return $this;
    }
}
