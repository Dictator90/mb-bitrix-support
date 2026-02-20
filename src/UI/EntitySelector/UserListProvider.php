<?php

namespace MB\Bitrix\UI\EntitySelector;

use Bitrix\Intranet\Integration\Mail\EmailUser;
use Bitrix\Main\Application;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\EO_User;
use Bitrix\Main\EO_User_Collection;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Query\Filter;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Search\Content;
use Bitrix\Main\UserTable;
use Bitrix\UI\EntitySelector\BaseProvider;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\Item;
use Bitrix\UI\EntitySelector\SearchQuery;
use Bitrix\UI\EntitySelector\Tab;

class UserListProvider extends BaseProvider
{
    public const ENTITY_ID = 'user-list';
    protected const MAX_USERS_IN_RECENT_TAB = 50;

    public function __construct(array $options = [])
    {
        parent::__construct();
        $this->prepareOptions($options);
    }

    protected function prepareOptions(array $options = []): void
    {
        if (!$options['selected']) {
            $this->options['selected'] = [];
        } elseif (!is_array($options['selected'])) {
            $this->options['selected'] = [$options['selected']];
        }

        if (isset($options['nameTemplate']) && is_string($options['nameTemplate'])) {
            preg_match_all(
                '/#NAME#|#LAST_NAME#|#SECOND_NAME#|#NAME_SHORT#|#SECOND_NAME_SHORT#|\s|,/',
                urldecode($options['nameTemplate']),
                $matches
            );

            $this->options['nameTemplate'] = implode('', $matches[0]);
        } else {
            $this->options['nameTemplate'] = \CSite::getNameFormat(false);
        }

        $this->options['analyticsSource'] = 'userProvider';
        if (isset($options['onlyWithEmail']) && is_bool($options['onlyWithEmail'])) {
            $this->options['onlyWithEmail'] = $options['onlyWithEmail'];
        }

        $this->options['showInvitationFooter'] = true;
        if (isset($options['showInvitationFooter']) && is_bool($options['showInvitationFooter'])) {
            $this->options['showInvitationFooter'] = $options['showInvitationFooter'];
        }

        $this->options['emailUsers'] = false;
        if (isset($options['emailUsers']) && is_bool($options['emailUsers'])) {
            $this->options['emailUsers'] = $options['emailUsers'];
        }

        $this->options['myEmailUsers'] = true;
        if (isset($options['myEmailUsers']) && is_bool($options['myEmailUsers'])) {
            $this->options['myEmailUsers'] = $options['myEmailUsers'];
        }

        if (isset($options['emailUsersOnly']) && is_bool($options['emailUsersOnly'])) {
            $this->options['emailUsersOnly'] = $options['emailUsersOnly'];
        }

        // User Whitelist
        if (isset($options['userId'])) {
            $ids = static::prepareUserIds($options['userId']);
            if (!empty($ids)) {
                $this->options['userId'] = $ids;
            }
        }

        // User Blacklist
        if (isset($options['!userId'])) {
            $ids = static::prepareUserIds($options['!userId']);
            if (!empty($ids)) {
                $this->options['!userId'] = $ids;
            }
        }

        if (isset($options['selectFields']) && is_array($options['selectFields'])) {
            $selectFields = [];
            $allowedFields = static::getAllowedFields();
            foreach ($options['selectFields'] as $field) {
                if (is_string($field) && array_key_exists($field, $allowedFields)) {
                    $selectFields[] = $field;
                }
            }

            $this->options['selectFields'] = array_unique($selectFields);
        }

        $this->options['fillDialog'] = true;
        if (isset($options['fillDialog']) && is_bool($options['fillDialog'])) {
            $this->options['fillDialog'] = $options['fillDialog'];
        }
    }

    public function isAvailable(): bool
    {
        if (!$GLOBALS['USER']->isAuthorized()) {
            return false;
        }

        return true;
    }

    public function shouldFillDialog(): bool
    {
        return $this->getOption('fillDialog', true);
    }

    public function getItems(array $ids): array
    {
        if (!$this->shouldFillDialog()) {
            return [];
        }

        return $this->getUserItems([
            'userId' => $ids
        ]);
    }

    public function getSelectedItems(array $ids): array
    {
        return $this->getUserItems([
            'userId' => $ids,
            'ignoreUserWhitelist' => true,
            'activeUsers' => null
        ]);
    }

    public function fillDialog(Dialog $dialog): void
    {
        if (!$this->shouldFillDialog()) {
            return;
        }

        $dialog->addTab(
            new Tab([
                "id" => static::ENTITY_ID,
                "title" => 'Пользователи',
                'icon' => [
                    'default' => 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2050%2050%22%20fill%3D%22currentColor%22%3E%3Cpath%20fill%3D%22%23ABB1B8%22%20fill-rule%3D%22evenodd%22%20d%3D%22M21.645%2011.713c-1.054-1.67%207.832-3.057%208.422%202.054a15.6%2015.6%200%200%201%200%204.647s1.328-.152.442%202.372c0%200-.488%201.816-1.238%201.408%200%200%20.122%202.296-1.058%202.685%200%200%20.084%201.223.084%201.306l.986.147s-.03%201.02.167%201.13c.9.58%201.886%201.021%202.923%201.305%203.062.777%204.616%202.11%204.616%203.278l.823%204.189c-3.544%201.485-7.657%202.373-12.055%202.466H24.22c-4.389-.093-8.493-.977-12.03-2.456.161-1.159.371-2.47.588-3.315.466-1.816%203.087-3.165%205.498-4.202%201.248-.537%201.518-.86%202.774-1.409.07-.334.098-.676.084-1.017l1.068-.127s.14.255-.085-1.245c0%200-1.2-.311-1.256-2.7%200%200-.902.3-.956-1.147-.039-.98-.808-1.832.299-2.537l-.564-1.502s-.592-5.8%202.005-5.33%22%2F%3E%3C%2Fsvg%3E',
                    'selected' => 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2050%2050%22%20fill%3D%22currentColor%22%3E%3Cpath%20fill%3D%22white%22%20fill-rule%3D%22evenodd%22%20d%3D%22M21.645%2011.713c-1.054-1.67%207.832-3.057%208.422%202.054a15.6%2015.6%200%200%201%200%204.647s1.328-.152.442%202.372c0%200-.488%201.816-1.238%201.408%200%200%20.122%202.296-1.058%202.685%200%200%20.084%201.223.084%201.306l.986.147s-.03%201.02.167%201.13c.9.58%201.886%201.021%202.923%201.305%203.062.777%204.616%202.11%204.616%203.278l.823%204.189c-3.544%201.485-7.657%202.373-12.055%202.466H24.22c-4.389-.093-8.493-.977-12.03-2.456.161-1.159.371-2.47.588-3.315.466-1.816%203.087-3.165%205.498-4.202%201.248-.537%201.518-.86%202.774-1.409.07-.334.098-.676.084-1.017l1.068-.127s.14.255-.085-1.245c0%200-1.2-.311-1.256-2.7%200%200-.902.3-.956-1.147-.039-.98-.808-1.832.299-2.537l-.564-1.502s-.592-5.8%202.005-5.33%22%2F%3E%3C%2Fsvg%3E'
                ]
            ])
        );

        $preloadedUsers = $this->getPreloadedUsersCollection();

        if ($preloadedUsers->count() < self::MAX_USERS_IN_RECENT_TAB) {
            // Turn off the user search
            $entity = $dialog->getEntity(static::ENTITY_ID);
            if ($entity) {
                $entity->setDynamicSearch(false);
            }
        }

        $recentUsers = new EO_User_Collection();

        // Recent Items
        $recentItems = $dialog->getRecentItems()->getEntityItems(static::ENTITY_ID);
        $recentIds = array_map('intval', array_keys($recentItems));
        $this->fillRecentUsers($recentUsers, $recentIds, $preloadedUsers);

        // Global Recent Items
        if ($recentUsers->count() < self::MAX_USERS_IN_RECENT_TAB) {
            $recentGlobalItems = $dialog->getGlobalRecentItems()->getEntityItems(static::ENTITY_ID);
            $recentGlobalIds = [];

            if (!empty($recentGlobalItems)) {
                $recentGlobalIds = array_map('intval', array_keys($recentGlobalItems));
                $recentGlobalIds = array_values(array_diff($recentGlobalIds, $recentUsers->getIdList()));
                $recentGlobalIds = array_slice(
                    $recentGlobalIds,
                    0,
                    self::MAX_USERS_IN_RECENT_TAB - $recentUsers->count()
                );
            }

            $this->fillRecentUsers($recentUsers, $recentGlobalIds, $preloadedUsers);
        }

        // The rest of preloaded users
        foreach ($preloadedUsers as $preloadedUser) {
            $recentUsers->add($preloadedUser);
        }

        $dialog->addRecentItems($this->makeUserItems($recentUsers));

//        $dialog->addTab(new Tab([
//            'id' => self::ENTITY_ID,
//            'title' => 'scopes',
//            'stub' => true
//        ]));
    }

    protected function getPreloadedUsersCollection(): EO_User_Collection
    {
        return $this->getUserCollection([
            'order' => ['ID' => 'asc'],
            'limit' => self::MAX_USERS_IN_RECENT_TAB
        ]);
    }

    private function fillRecentUsers(
        EO_User_Collection $recentUsers,
        array $recentIds,
        EO_User_Collection $preloadedUsers
    ): void {
        if (count($recentIds) < 1) {
            return;
        }

        $ids = array_values(array_diff($recentIds, $preloadedUsers->getIdList()));
        if (!empty($ids)) {
            $users = $this->getUserCollection(['userId' => $ids]);
            foreach ($users as $user) {
                $preloadedUsers->add($user);
            }
        }

        foreach ($recentIds as $recentId) {
            $user = $preloadedUsers->getByPrimary($recentId);
            if ($user) {
                $recentUsers->add($user);
            }
        }
    }

    public function doSearch(SearchQuery $searchQuery, Dialog $dialog): void
    {
        $atom = '=_0-9a-z+~\'!\$&*^`|\\#%/?{}-';
        $isEmailLike = (bool)preg_match('#^[' . $atom . ']+(\\.[' . $atom . ']+)*@#i', $searchQuery->getQuery());
        $limit = 100;

        if ($isEmailLike) {
            $items = $this->getUserItems([
                'searchByEmail' => $searchQuery->getQuery(),
                'myEmailUsers' => false,
                'limit' => $limit
            ]);
        } else {
            $items = $this->getUserItems([
                'searchQuery' => $searchQuery->getQuery(),
                'limit' => $limit
            ]);
        }

        $limitExceeded = $limit <= count($items);
        if ($limitExceeded) {
            $searchQuery->setCacheable(false);
        }

        $dialog->addItems($items);
    }

    public function handleBeforeItemSave(Item $item): void
    {
        if ($item->getEntityType() === 'email') {
            $user = UserTable::getById($item->getId())->fetchObject();
            if ($user && $user->getExternalAuthId() === 'email' && Loader::includeModule('intranet')) {
                EmailUser::invite($user->getId());
            }
        }
    }

    public function getUserCollection(array $options = []): EO_User_Collection
    {
        $dialogOptions = $this->getOptions();
        $options = array_merge($dialogOptions, $options);

        $ignoreUserWhitelist = isset($options['ignoreUserWhitelist']) && $options['ignoreUserWhitelist'] === true;
        if (!empty($dialogOptions['userId']) && !$ignoreUserWhitelist) {
            $options['userId'] = $dialogOptions['userId'];
        }

        return static::getUsers($options);
    }

    public function getUserItems(array $options = []): array
    {
        return $this->makeUserItems($this->getUserCollection($options), $options);
    }

    public function makeUserItems(EO_User_Collection $users, array $options = []): array
    {
        return self::makeItems($users, array_merge($this->getOptions(), $options));
    }

    public static function isIntranetUser(int $userId = null): bool
    {
        return self::hasUserRole($userId, 'intranet');
    }

    public static function isExtranetUser(int $userId = null): bool
    {
        return self::hasUserRole($userId, 'extranet');
    }

    public static function getCurrentUserId(): int
    {
        return is_object($GLOBALS['USER']) ? (int)$GLOBALS['USER']->getId() : 0;
    }

    private static function hasUserRole(?int $userId, string $role): bool
    {
        static $roles = [
            'intranet' => [],
            'extranet' => []
        ];

        if (!isset($roles[$role]) || !ModuleManager::isModuleInstalled('intranet')) {
            return false;
        }

        if (is_null($userId)) {
            $userId = self::getCurrentUserId();
            if ($userId <= 0) {
                return false;
            }
        }

        if (
            $userId === self::getCurrentUserId()
            && \CSocNetUser::isCurrentUserModuleAdmin()
        ) {
            return true;
        }

        if (isset($roles[$role][$userId])) {
            return $roles[$role][$userId];
        }

        $cacheId = 'UserRole:' . $role;
        $cachePath = '/external_user_info/' . substr(md5($userId), -2) . '/' . $userId . '/';
        $cache = Application::getInstance()->getCache();
        $ttl = 2592000; // 1 month

        if ($cache->initCache($ttl, $cacheId, $cachePath)) {
            $roles[$role][$userId] = (bool)$cache->getVars();
        } else {
            $cache->startDataCache();

            $taggedCache = Application::getInstance()->getTaggedCache();
            $taggedCache->startTagCache($cachePath);
            $taggedCache->registerTag('USER_NAME_' . $userId);
            $taggedCache->endTagCache();

            $filter = [
                '=ID' => $userId,
                '=IS_REAL_USER' => true
            ];

            if ($role === 'intranet') {
                $filter['!UF_DEPARTMENT'] = false;
            } else {
                if ($role === 'extranet') {
                    $filter['UF_DEPARTMENT'] = false;
                }
            }

            $roles[$role][$userId] =
                UserTable::getList(['select' => ['ID'], 'filter' => $filter])
                    ->fetchCollection()->count() === 1;

            $cache->endDataCache($roles[$role][$userId]);
        }

        return $roles[$role][$userId];
    }

    public static function getAllowedFields(): array
    {
        static $fields = null;

        if ($fields !== null) {
            return $fields;
        }

        $fields = [
            'lastName' => 'LAST_NAME',
            'name' => 'NAME',
            'secondName' => 'SECOND_NAME',
            'login' => 'LOGIN',
            'email' => 'EMAIL',
            'title' => 'TITLE',
            'position',
            'WORK_POSITION',
            'lastLogin' => 'LAST_LOGIN',
            'dateRegister' => 'DATE_REGISTER',
            'lastActivityDate' => 'LAST_ACTIVITY_DATE',
            'online' => 'IS_ONLINE',
            'profession' => 'PERSONAL_PROFESSION',
            'www' => 'PERSONAL_WWW',
            'birthday' => 'PERSONAL_BIRTHDAY',
            'icq' => 'PERSONAL_ICQ',
            'phone' => 'PERSONAL_PHONE',
            'fax' => 'PERSONAL_FAX',
            'mobile' => 'PERSONAL_MOBILE',
            'pager' => 'PERSONAL_PAGER',
            'street' => 'PERSONAL_STREET',
            'city' => 'PERSONAL_CITY',
            'state' => 'PERSONAL_STATE',
            'zip' => 'PERSONAL_ZIP',
            'mailbox' => 'PERSONAL_MAILBOX',
            'country' => 'PERSONAL_COUNTRY',
            'timeZoneOffset' => 'TIME_ZONE_OFFSET',
            'company' => 'WORK_COMPANY',
            'workPhone' => 'WORK_PHONE',
            'workDepartment' => 'WORK_DEPARTMENT',
            'workPosition' => 'WORK_POSITION',
            'workCity' => 'WORK_CITY',
            'workCountry' => 'WORK_COUNTRY',
            'workStreet' => 'WORK_STREET',
            'workState' => 'WORK_STATE',
            'workZip' => 'WORK_ZIP',
            'workMailbox' => 'WORK_MAILBOX',
        ];

        foreach ($fields as $id => $dbName) {
            if (mb_strpos($dbName, 'PERSONAL_') === 0) {
                $fields['personal' . ucfirst($id)] = $dbName;
            }

            $fields[$dbName] = $dbName;
        }

        return $fields;
    }

    public static function getUsers(array $options = []): EO_User_Collection
    {
        $query = static::getQuery($options);
        $result = $query->exec();

        return $result->fetchCollection();
    }

    protected static function getQuery(array $options = []): Query
    {
        $selectFields = [
            'ID',
            'ACTIVE',
            'LAST_NAME',
            'NAME',
            'SECOND_NAME',
            'LOGIN',
            'EMAIL',
            'TITLE',
            'PERSONAL_GENDER',
            'PERSONAL_PHOTO',
            'WORK_POSITION',
            'CONFIRM_CODE',
            'EXTERNAL_AUTH_ID'
        ];

        if (isset($options['selectFields']) && is_array($options['selectFields'])) {
            $allowedFields = static::getAllowedFields();
            foreach ($options['selectFields'] as $field) {
                if (is_string($field) && array_key_exists($field, $allowedFields)) {
                    $selectFields[] = $allowedFields[$field];
                }
            }
        }

        $query = UserTable::query();
        $query->setSelect(array_unique($selectFields));

        $activeUsers = array_key_exists('activeUsers', $options) ? $options['activeUsers'] : true;
        if (is_bool($activeUsers)) {
            $query->where('ACTIVE', $activeUsers ? 'Y' : 'N');
        }

        if (isset($options['onlyWithEmail']) && is_bool(isset($options['onlyWithEmail']))) {
            $query->addFilter(($options['onlyWithEmail'] ? '!' : '') . 'EMAIL', false);
        }

        if (isset($options['invitedUsers']) && is_bool(isset($options['invitedUsers']))) {
            $query->addFilter(($options['invitedUsers'] ? '!' : '') . 'CONFIRM_CODE', false);
        }

        if (!empty($options['searchQuery']) && is_string($options['searchQuery'])) {
            $query->registerRuntimeField(
                new Reference(
                    'USER_INDEX',
                    \Bitrix\Main\UserIndexTable::class,
                    Join::on('this.ID', 'ref.USER_ID'),
                    ['join_type' => 'INNER']
                )
            );

            $query->whereMatch(
                'USER_INDEX.SEARCH_USER_CONTENT',
                Filter\Helper::matchAgainstWildcard(
                    Content::prepareStringToken($options['searchQuery']),
                    '*',
                    1
                )
            );
        } else {
            if (!empty($options['searchByEmail']) && is_string($options['searchByEmail'])) {
                $query->whereLike('EMAIL', $options['searchByEmail'] . '%');
            }
        }

        $currentUserId = (
        !empty($options['currentUserId']) && is_int($options['currentUserId'])
            ? $options['currentUserId']
            : $GLOBALS['USER']->getId()
        );


        $query->addFilter('!=EXTERNAL_AUTH_ID', UserTable::getExternalUserTypes());


        $userIds = self::prepareUserIds($options['userId'] ?? []);
        $notUserIds = self::prepareUserIds($options['!userId'] ?? []);

        // User Whitelist
        if (!empty($userIds)) {
            $query->whereIn('ID', $userIds);
        }

        // User Blacklist
        if (!empty($notUserIds)) {
            $query->whereNotIn('ID', $notUserIds);
        }

        if (
            empty($options['order'])
            &&
            ($usersCount = count($userIds)) > 1
        ) {
            $helper = Application::getConnection()->getSqlHelper();
            $expression = $helper->getOrderByIntField('%s', $userIds, false);
            $field = new ExpressionField(
                'ID_SEQUENCE',
                $expression,
                array_fill(0, $usersCount, 'ID')
            );
            $query
                ->registerRuntimeField($field)
                ->setOrder($field->getName());

        } elseif (!empty($options['order']) && is_array($options['order'])) {
            $query->setOrder($options['order']);
        } else {
            $query->setOrder(['LAST_NAME' => 'asc']);
        }

        if (isset($options['limit']) && is_int($options['limit'])) {
            $query->setLimit($options['limit']);
        } elseif (empty($userIds)) // no limit if we filter users by ids
        {
            $query->setLimit(100);
        }

        return $query;
    }

    private static function prepareUserIds($items): array
    {
        $ids = [];
        if (is_array($items) && !empty($items)) {
            foreach ($items as $id) {
                if ((int)$id > 0) {
                    $ids[] = (int)$id;
                }
            }

            $ids = array_unique($ids);
        } else {
            if (!is_array($items) && (int)$items > 0) {
                $ids = [(int)$items];
            }
        }

        return $ids;
    }

    public static function getUser(int $userId, array $options = []): ?EO_User
    {
        $options['userId'] = $userId;
        $users = static::getUsers($options);

        return $users->count() ? $users->getAll()[0] : null;
    }

    public static function makeItems(EO_User_Collection $users, array $options = []): array
    {
        $result = [];
        foreach ($users as $user) {
            $result[] = static::makeItem($user, $options);
        }

        return $result;
    }

    public static function makeItem(EO_User $user, array $options = []): Item
    {
        $customData = [];
        foreach (['name', 'lastName', 'secondName', 'email', 'login'] as $field) {
            if (!empty($user->{'get' . $field}())) {
                $customData[$field] = $user->{'get' . $field}();
            }
        }

        if (!empty($user->getPersonalGender())) {
            $customData['gender'] = $user->getPersonalGender();
        }

        if (!empty($user->getWorkPosition())) {
            $customData['position'] = $user->getWorkPosition();
        }

        $userType = self::getUserType($user);

        if ($user->getConfirmCode() && in_array($userType, ['employee', 'integrator'])) {
            $customData['invited'] = true;
        }

        if (isset($options['selectFields']) && is_array($options['selectFields'])) {
            $userData = $user->collectValues();
            $allowedFields = static::getAllowedFields();
            foreach ($options['selectFields'] as $field) {
                if (!is_string($field)) {
                    continue;
                }

                $dbName = $allowedFields[$field] ?? null;
                $value = $userData[$dbName] ?? null;
                if (!empty($value)) {
                    if ($field === 'country' || $field === 'workCountry') {
                        $value = \Bitrix\Main\UserUtils::getCountryValue(['VALUE' => $value]);
                    }

                    $customData[$field] = $value;
                }
            }
        }

        if (isset($options['showLogin']) && $options['showLogin'] === false) {
            unset($customData['login']);
        }

        if (isset($options['showEmail']) && $options['showEmail'] === false) {
            unset($customData['email']);
        }

        return new Item([
            'id' => $user->getId(),
            'entityId' => static::ENTITY_ID,
            'entityType' => $userType,
            'title' => self::formatUserName($user, $options),
            'subtitle' => '',
            'avatar' => self::makeUserAvatar($user),
            'customData' => $customData,
            'selected' => in_array($user->getId(), $options['selected']),
            'tabs' => static::getTabsNames(),
        ]);

    }

    protected static function getTabsNames(): array
    {
        return [static::ENTITY_ID];
    }

    public static function getUserType(EO_User $user): string
    {
        $type = null;
        if (!$user->getActive()) {
            $type = 'inactive';
        } else {
            if ($user->getExternalAuthId() === 'email') {
                $type = 'email';
            } else {
                if ($user->getExternalAuthId() === 'replica') {
                    $type = 'network';
                } else {
                    if (!in_array($user->getExternalAuthId(), UserTable::getExternalUserTypes())) {
                        $type = 'user';
                    } else {
                        $type = 'unknown';
                    }
                }
            }
        }

        return $type;
    }

    public static function formatUserName(EO_User $user, array $options = []): string
    {
        return \CUser::formatName(
            !empty($options['nameTemplate']) ? $options['nameTemplate'] : \CSite::getNameFormat(false),
            [
                'NAME' => $user->getName(),
                'LAST_NAME' => $user->getLastName(),
                'SECOND_NAME' => $user->getSecondName(),
                'LOGIN' => $user->getLogin(),
                'EMAIL' => $user->getEmail(),
                'TITLE' => $user->getTitle(),
            ],
            true,
            false
        );
    }

    public static function makeUserAvatar(EO_User $user): ?string
    {
        if (empty($user->getPersonalPhoto())) {
            return null;
        }

        $avatar = \CFile::resizeImageGet(
            $user->getPersonalPhoto(),
            ['width' => 100, 'height' => 100],
            BX_RESIZE_IMAGE_EXACT,
            false
        );

        return !empty($avatar['src']) ? $avatar['src'] : null;
    }

    public static function getUserUrl(?int $userId = null): string
    {
        return '';
    }

}
