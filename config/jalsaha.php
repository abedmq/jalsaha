<?php

return [
    'webhook_secret' => env('CLOUDFLARE_WEBHOOK_SECRET'),
    'lease_seconds' => 600,
    'release_after_delivery' => 60,
    'recent_code_seconds' => 60,
    'permissions' => [
        'domains.manage' => 'إدارة الدومينات',
        'services.manage' => 'إدارة الخدمات',
        'mailboxes.manage' => 'إنشاء وتعديل الإيميلات',
        'subscriptions.manage' => 'إدارة الاشتراكات',
        'mail.read' => 'قراءة الرسائل',
        'codes.view' => 'عرض الأكواد والربط',
        'links.manage' => 'إدارة روابط العملاء',
        'users.manage' => 'إدارة المستخدمين',
    ],
];
