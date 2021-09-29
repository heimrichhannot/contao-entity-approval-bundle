<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovalBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'][Configuration::ROOT_ID] = [
    NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED => [
        'recipients' => [
            'recipient_email',
        ],
        'attachement_tokens' => [
            'huhApproval_*',
            'approval_entity',
        ],
    ],
    NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED => [
        'recipients' => [
            'recipient_email',
        ],
        'attachement_tokens' => [
            'huhApproval_*',
            'approval_entity',
        ],
    ],
];
