<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['huh_entity_approvement'] = [
    'huh_entity_approvement_auditor_changed' => [
        'recipients' => [
            'recipient_email',
        ],
        'attachement_tokens' => [
            'huhApprovement_*',
            'approvement_entity',
        ],
    ],
    'huh_entity_approvement_state_changed' => [
        'recipients' => [
            'recipient_email',
        ],
        'attachement_tokens' => [
            'huhApprovement_*',
            'approvement_entity',
        ],
    ],
];
