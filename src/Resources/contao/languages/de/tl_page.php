<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;

$lang = &$GLOBALS['TL_LANG']['tl_page'];

$lang['entityApprovement']['entityName'][0] = 'Entität';
$lang['entityApprovement']['entityName'][1] = 'Wählen Sie hier die Entität aus, für die der Freigabeworkflow angepasst werden soll.';
$lang['entityApprovement']['auditorGroups'][0] = 'Prüfer';
$lang['entityApprovement']['auditorGroups'][1] = 'Wählen Sie hier die Benutzergruppen aus, die als Prüfer für diese Entität dienen sollen.';
$lang['entityApprovement']['initialAuditorGroups'][0] = 'Erste Prüfer';
$lang['entityApprovement']['initialAuditorGroups'][1] = 'Wählen Sie hier die Benutzergruppen aus, die als erste Prüfer die weiteren Prüfer zuordnen sollen.';
$lang['entityApprovement']['initialAuditorMode'][0] = 'Modus';
$lang['entityApprovement']['initialAuditorMode'][1] = 'Wählen Sie hier wie die Erstprüfer ausgewählt werden sollen.';

/*
 * Reference
 */
$references = [
    Configuration::AUDITOR_MODE_ALL => 'Alle',
    Configuration::AUDITOR_MODE_RANDOM => 'Zufällig',
];

$lang['reference'] = array_merge(is_array($lang['reference']) ? $lang['reference'] : [], $references);
