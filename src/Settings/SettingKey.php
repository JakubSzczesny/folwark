<?php

declare(strict_types=1);

namespace App\Settings;

final class SettingKey
{
    public const string KSEF_TOKEN = 'ksef.token';
    public const string KSEF_NIP = 'ksef.nip';
    public const string KSEF_ENVIRONMENT = 'ksef.environment';
    public const string KSEF_SYNC_INTERVAL = 'ksef.sync_interval_minutes';
    public const string KSEF_AUTO_FETCH_XML = 'ksef.auto_fetch_xml';
    public const string KSEF_AUTO_ASSIGN = 'ksef.auto_assign';
    public const string KSEF_SYNC_FROM = 'ksef.sync_from';
    public const string KSEF_OVERLAP_MINUTES = 'ksef.overlap_minutes';
    public const string KSEF_CURSORS = 'ksef.cursors';
    public const string KSEF_BLOCKED_UNTIL = 'ksef.blocked_until';

    public const string WHITE_LIST_ENABLED = 'white_list.enabled';

    public const string ORDERS_LEAD_DAYS = 'orders.lead_days';
    public const string ORDERS_MIN_ORDERS = 'orders.min_orders';
    public const string ORDERS_MERGE_DAYS = 'orders.merge_days';
    public const string ORDERS_REGULAR_THRESHOLD = 'orders.regular_threshold';
    public const string ORDERS_MODERATE_THRESHOLD = 'orders.moderate_threshold';
    public const string ORDERS_ORDERED_HOLD_DAYS = 'orders.ordered_hold_days';

    public const string NOTIFY_PAYMENT_DAYS = 'notify.payment_days';
    public const string NOTIFY_INSURANCE_DAYS = 'notify.insurance_days';
    public const string NOTIFY_INSPECTION_DAYS = 'notify.inspection_days';
    public const string NOTIFY_SERVICE_DAYS = 'notify.service_days';
    public const string NOTIFY_CONTRACT_DAYS = 'notify.contract_days';
    public const string NOTIFY_KSEF_REPEAT_HOURS = 'notify.ksef_repeat_hours';
    public const string NOTIFY_DIGEST_EMAIL = 'notify.digest_email';
    public const string ATTENTION_DAYS = 'notify.attention_days';

    public const string WAREHOUSE_CREATE_PZ = 'warehouse.create_pz';
    public const string WAREHOUSE_NUMBER_PATTERN = 'warehouse.number_pattern';

    public const string BSPAY_ENABLED = 'bspay.enabled';
    public const string BSPAY_BANK_URL = 'bspay.bank_url';
    public const string BSPAY_TITLE = 'bspay.title';
    public const string BSPAY_SPLIT_PAYMENT = 'bspay.split_payment';

    public const array DEFAULTS = [
        self::KSEF_TOKEN => null,
        self::KSEF_NIP => null,
        self::KSEF_ENVIRONMENT => 'test',
        self::KSEF_SYNC_INTERVAL => 30,
        self::KSEF_AUTO_FETCH_XML => true,
        self::KSEF_AUTO_ASSIGN => true,
        self::KSEF_SYNC_FROM => '2026-02-01',
        self::KSEF_OVERLAP_MINUTES => 15,
        self::KSEF_CURSORS => [],
        self::KSEF_BLOCKED_UNTIL => null,
        self::WHITE_LIST_ENABLED => false,
        self::ORDERS_LEAD_DAYS => 7,
        self::ORDERS_MIN_ORDERS => 3,
        self::ORDERS_MERGE_DAYS => 3,
        self::ORDERS_REGULAR_THRESHOLD => 0.25,
        self::ORDERS_MODERATE_THRESHOLD => 0.5,
        self::ORDERS_ORDERED_HOLD_DAYS => 30,
        self::NOTIFY_PAYMENT_DAYS => 3,
        self::NOTIFY_INSURANCE_DAYS => [30, 14, 3],
        self::NOTIFY_INSPECTION_DAYS => [30, 7],
        self::NOTIFY_SERVICE_DAYS => 14,
        self::NOTIFY_CONTRACT_DAYS => 30,
        self::NOTIFY_KSEF_REPEAT_HOURS => 24,
        self::NOTIFY_DIGEST_EMAIL => false,
        self::ATTENTION_DAYS => 30,
        self::WAREHOUSE_CREATE_PZ => false,
        self::WAREHOUSE_NUMBER_PATTERN => '{TYP}/{RRRR}/{MM}/{NNNN}',
        self::BSPAY_ENABLED => false,
        self::BSPAY_BANK_URL => 'https://i24.bsbydgoszcz.pl/',
        self::BSPAY_TITLE => 'Faktura {numer}',
        self::BSPAY_SPLIT_PAYMENT => true,
    ];

    /** Klucze zapisywane przez system, a nie przez użytkownika; ich zmian nie logujemy. */
    public const array INTERNAL = [self::KSEF_CURSORS, self::KSEF_BLOCKED_UNTIL];
}
