<?php

namespace MB\Bitrix\UI\Admin;

enum MenuIcon: string
{
    case BX_FAVORITE = 'fav_menu_icon';
    case BX_IBLOCK = 'iblock_menu_icon_iblocks';
    case BX_IBLOCK_TYPE = 'iblock_menu_icon_types';
    case BX_IBLOCK_SECTION = 'iblock_menu_icon_sections';
    case BX_HIGHLOAD = 'highloadblock_menu_icon';
    case BX_FILEMAN = 'fileman_menu_icon';
    case BX_CLOUD_STORAGE = 'clouds_menu_icon';
    case BX_SMILE = 'smile_menu_icon';
    case BX_RATING = 'rating_menu_icon';
    case BX_SUPPORT = 'support_menu_icon';
    case BX_SOCNET = 'sonet_menu_icon';
    case BX_CHATTING = 'forum_menu_icon';
    case BX_BIZPROC = 'bizproc_menu_icon';
    case BX_BLOG = 'blog_menu_icon';
    case BX_MAIL = 'mail_menu_icon';
    case BX_SALE_SETTINGS = 'sale_menu_icon';
    case BX_SALE_ORDER = 'sale_menu_icon_orders';
    case BX_SALE_BUYER = 'sale_menu_icon_buyers';
    case BX_SALE_STORE = 'sale_menu_icon_store';
    case BX_SALE_BIGDATA = 'sale_menu_icon_bigdata';
    case BX_SALE_MARKETPLACE_1 = 'sale_menu_icon_marketplace';
    case BX_SALE_MARKETPLACE_2 = 'update_marketplace';
    case BX_CRM = 'sale_menu_icon_crm';
    case BX_UPDATE_PARTNER = 'update_menu_icon_partner';
    case BX_UPDATE = 'update_menu_icon';
    case BX_USER = 'user_menu_icon';
    case BX_SEARCH = 'search_menu_icon';
    case BX_SECURITY = 'security_menu_icon';
    case BX_CURRENCY = 'currency_menu_icon';
    case BX_LDAP = 'ldap_menu_icon';
    case BX_EARTH = 'translate_menu_icon';
    case BX_CLUSTER = 'cluster_menu_icon';
    case BX_SETTINGS = 'sys_menu_icon';
    case BX_UTIL = 'util_menu_icon';
    case BX_PERFORM = 'perfmon_menu_icon';
    case BX_WORKFLOW = 'workflow_menu_icon';
    case BX_DOWNLOAD = 'update_marketplace_modules';
}
