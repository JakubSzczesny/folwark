<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\UserRole;

final class Permission
{
    public const string INVOICE_VIEW = 'INVOICE_VIEW';
    public const string INVOICE_MANAGE = 'INVOICE_MANAGE';
    public const string AMOUNTS_VIEW = 'AMOUNTS_VIEW';
    public const string ANALYTICS_VIEW = 'ANALYTICS_VIEW';
    public const string CONTRACTOR_VIEW = 'CONTRACTOR_VIEW';
    public const string CONTRACTOR_EDIT = 'CONTRACTOR_EDIT';
    public const string CONTRACT_VIEW = 'CONTRACT_VIEW';
    public const string CONTRACT_EDIT = 'CONTRACT_EDIT';
    public const string MACHINE_VIEW = 'MACHINE_VIEW';
    public const string MACHINE_EDIT = 'MACHINE_EDIT';
    public const string MACHINE_MANAGE = 'MACHINE_MANAGE';
    public const string ORDER_VIEW = 'ORDER_VIEW';
    public const string ORDER_MARK = 'ORDER_MARK';
    public const string ORDER_MANAGE = 'ORDER_MANAGE';
    public const string PRODUCT_VIEW = 'PRODUCT_VIEW';
    public const string PRODUCT_EDIT = 'PRODUCT_EDIT';
    public const string DOCUMENT_VIEW = 'DOCUMENT_VIEW';
    public const string DOCUMENT_EDIT = 'DOCUMENT_EDIT';
    public const string EXPORT = 'EXPORT';
    public const string WEIGHBRIDGE_CHECK = 'WEIGHBRIDGE_CHECK';
    public const string WEIGHBRIDGE_MANAGE = 'WEIGHBRIDGE_MANAGE';
    public const string SETTINGS = 'SETTINGS';

    /**
     * Minimalna rola wymagana dla uprawnienia. Właściciel dziedziczy role
     * pracownika i księgowej (security.role_hierarchy).
     */
    public const array REQUIRED_ROLE = [
        self::INVOICE_VIEW => UserRole::Accountant,
        self::INVOICE_MANAGE => UserRole::Owner,
        self::AMOUNTS_VIEW => UserRole::Accountant,
        self::ANALYTICS_VIEW => UserRole::Owner,
        self::CONTRACTOR_VIEW => UserRole::Accountant,
        self::CONTRACTOR_EDIT => UserRole::Owner,
        self::CONTRACT_VIEW => UserRole::Accountant,
        self::CONTRACT_EDIT => UserRole::Owner,
        self::MACHINE_VIEW => UserRole::Worker,
        self::MACHINE_EDIT => UserRole::Worker,
        self::MACHINE_MANAGE => UserRole::Owner,
        self::ORDER_VIEW => UserRole::Worker,
        self::ORDER_MARK => UserRole::Worker,
        self::ORDER_MANAGE => UserRole::Owner,
        self::PRODUCT_VIEW => null,
        self::PRODUCT_EDIT => UserRole::Worker,
        self::DOCUMENT_VIEW => UserRole::Accountant,
        self::DOCUMENT_EDIT => UserRole::Owner,
        self::EXPORT => UserRole::Accountant,
        self::WEIGHBRIDGE_CHECK => UserRole::Worker,
        self::WEIGHBRIDGE_MANAGE => UserRole::Owner,
        self::SETTINGS => UserRole::Owner,
    ];
}
