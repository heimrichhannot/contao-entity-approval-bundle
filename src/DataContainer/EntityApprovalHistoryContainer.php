<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;

class EntityApprovalHistoryContainer
{
    /**
     * @Callback(table="tl_entity_approval_history", target="config.onload")
     */
    public function onLoadEntityApprovalHistory(DataContainer $dc)
    {
    }
}
