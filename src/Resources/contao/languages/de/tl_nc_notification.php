<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovementBundle\Manager\EntityApprovementWorkflowManager;

$arrLang = &$GLOBALS['TL_LANG']['tl_nc_notification'];

$arrLang['type'][Configuration::ROOT_ID] = 'Freigabe';
$arrLang['type'][EntityApprovementWorkflowManager::NOTIFICATION_TYPE_STATE_CHANGED][0] = 'Statusänderung';
$arrLang['type'][EntityApprovementWorkflowManager::NOTIFICATION_TYPE_STATE_CHANGED][1] = 'Dieser Benachrichtigungstyp wird nach der Statusänderung der Entität verschickt.';
$arrLang['type'][EntityApprovementWorkflowManager::NOTIFICATION_TYPE_AUDITOR_CHANGED][0] = ' Prüferveränderung';
$arrLang['type'][EntityApprovementWorkflowManager::NOTIFICATION_TYPE_AUDITOR_CHANGED][1] = 'Dieser Benachrichtigungstyp wird nach der Prüferänderung der Entität verschickt.';
