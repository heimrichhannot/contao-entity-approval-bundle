<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovalBundle\DependencyInjection\Configuration;

$lang = &$GLOBALS['TL_LANG']['tl_page'];

/*
 * Legends
 */
$lang['entity_approval_legend'] = 'Freigabe von Entitäten';

$lang['activateEntityApproval'][0] = 'Freigabe von Entitäten ändern';
$lang['activateEntityApproval'][1] = 'Wählen sie diese Option, wenn Sie die definierten Freigabeworkflows speziell für diese Seite anpassen möchten.';

$lang['entityApproval']['entityName'][0] = 'Entität';
$lang['entityApproval']['entityName'][1] = 'Wählen Sie hier die Entität aus, für die der Freigabeworkflow angepasst werden soll.';
$lang['entityApproval']['auditorGroups'][0] = 'Prüfer';
$lang['entityApproval']['auditorGroups'][1] = 'Wählen Sie hier die Benutzergruppen aus, die als Prüfer für diese Entität dienen sollen.';
$lang['entityApproval']['initialAuditorGroups'][0] = 'Erste Prüfer';
$lang['entityApproval']['initialAuditorGroups'][1] = 'Wählen Sie hier die Benutzergruppen aus, die als erste Prüfer die weiteren Prüfer zuordnen sollen.';
$lang['entityApproval']['initialAuditorMode'][0] = 'Modus';
$lang['entityApproval']['initialAuditorMode'][1] = 'Wählen Sie hier wie die Erstprüfer ausgewählt werden sollen.';

/*
 * Reference
 */
$references = [
    Configuration::AUDITOR_MODE_ALL => 'Alle',
    Configuration::AUDITOR_MODE_RANDOM => 'Zufällig',
];

$lang['reference'] = array_merge(is_array($lang['reference']) ? $lang['reference'] : [], $references);
