<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;

$GLOBALS['TL_LANG']['MSC']['approval_auditor'][0] = 'Prüfer';
$GLOBALS['TL_LANG']['MSC']['approval_auditor'][1] = 'Wählen Sie hier die freigebende';
$GLOBALS['TL_LANG']['MSC']['approval_state'][0] = 'Status';
$GLOBALS['TL_LANG']['MSC']['approval_state'][1] = 'Wählen Sie hier den Status';
$GLOBALS['TL_LANG']['MSC']['approval_notes'][0] = 'Anmerkungen';
$GLOBALS['TL_LANG']['MSC']['approval_notes'][1] = 'Teilen Sie hier Ihre Anmerkungen mit';
$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'][0] = 'Den Author benachrichtigen';
$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'][1] = 'Wählen Sie das aus, wenn der Author über Statusänderungen informiert werden soll.';
$GLOBALS['TL_LANG']['MSC']['approval_confirmContinue'][0] = '<span style="color: #c33">VORTFAHREN: Ich habe die Prüfung der Änderungen vorgenommen und möchte die gewählte Aktion annehmen. ACHTUNG: Nach dem speichern des Datensatzes werden E-Mails verschickt.</span>';
$GLOBALS['TL_LANG']['MSC']['approval_transition'][0] = 'Aktion';
$GLOBALS['TL_LANG']['MSC']['approval_transition'][1] = 'Wählen Sie hier bitte die Aktion aus, die Sie nach Ihrer Prüfung für die Änderungen vornehmen möchten.';

$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_CREATED] = 'Erstellt';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT] = 'In Prüfung';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_APPROVED] = 'Freigegeben';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_REJECTED] = 'Abgelehnt';

$GLOBALS['TL_LANG']['MSC']['reference'][EntityApprovalContainer::APPROVAL_TRANSITION_SUBMIT] = 'Übermittlung';
$GLOBALS['TL_LANG']['MSC']['reference'][EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR] = 'Auditor zuordnen';
$GLOBALS['TL_LANG']['MSC']['reference'][EntityApprovalContainer::APPROVAL_TRANSITION_REQUEST_CHANGE] = 'Änderungen anfragen';
$GLOBALS['TL_LANG']['MSC']['reference'][EntityApprovalContainer::APPROVAL_TRANSITION_APPROVE] = 'Akzeptieren';
$GLOBALS['TL_LANG']['MSC']['reference'][EntityApprovalContainer::APPROVAL_TRANSITION_REJECT] = 'Ablehnen';
