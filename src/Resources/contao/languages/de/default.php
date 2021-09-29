<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;

$GLOBALS['TL_LANG']['MSC']['approval_auditor'][0] = 'Zuständigkeit';
$GLOBALS['TL_LANG']['MSC']['approval_auditor'][1] = 'Wählen Sie hier die freigebende';
$GLOBALS['TL_LANG']['MSC']['approval_state'][0] = 'Status';
$GLOBALS['TL_LANG']['MSC']['approval_state'][1] = 'Wählen Sie hier den Status';
$GLOBALS['TL_LANG']['MSC']['approval_notes'][0] = 'Anmerkungen';
$GLOBALS['TL_LANG']['MSC']['approval_notes'][1] = 'Teilen Sie hier Ihre Anmerkungen mit';
$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'][0] = 'Den Author benachrichtigen';
$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'][1] = 'Wählen Sie das aus, wenn der Author über Statusänderungen informiert werden soll.';

$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_WAIT_FOR_INITIAL_AUDITOR] = 'Warten auf Zuordnung';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_IN_PROGRESS] = 'In Arbeit';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_CHANGES_REQUESTED] = 'Änderungen angefordert';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_APPROVED] = 'Freigegeben';
$GLOBALS['TL_LANG']['MSC']['approval_state'][EntityApprovalContainer::APPROVAL_STATE_REJECTED] = 'Abgelehnt';
