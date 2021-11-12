<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovalBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;

$lang = &$GLOBALS['TL_LANG']['tl_nc_notification'];

$lang['type'][Configuration::ROOT_ID] = 'Freigabe';

$lang['type'][NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED][0] = 'Statusänderung';
$lang['type'][NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED][1] = 'Dieser Benachrichtigungstyp wird zur Bekanntgabe von Statusänderungen verwendet.';
$lang['type'][NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED][0] = 'Auditoränderung';
$lang['type'][NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED][1] = 'Dieser Benachrichtigungstyp wird zur Bekanntgabe von Auditoränderungen verwendet.';
