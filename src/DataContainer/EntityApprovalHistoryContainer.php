<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use HeimrichHannot\EntityApprovalBundle\Util\AuditorUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;

class EntityApprovalHistoryContainer
{
    protected array                   $bundleConfig;
    protected DatabaseUtil            $databaseUtil;
    protected UserUtil                $userUtil;
    protected EntityApprovalContainer $entityApprovalContainer;
    protected AuditorUtil             $auditorUtil;

    public function __construct(
        AuditorUtil $auditorUtil,
        DatabaseUtil $databaseUtil,
        EntityApprovalContainer $entityApprovalContainer,
        UserUtil $userUtil,
        array $bundleConfig
    ) {
        $this->bundleConfig = $bundleConfig;
        $this->databaseUtil = $databaseUtil;
        $this->userUtil = $userUtil;
        $this->entityApprovalContainer = $entityApprovalContainer;
        $this->auditorUtil = $auditorUtil;
    }

    /**
     * @Callback(table="tl_entity_approval_history", target="config.onload")
     */
    public function onLoadEntityApprovalHistory(DataContainer $dc)
    {
    }

    /**
     * @Callback(table="tl_entity_approval_history", target="fields.auditor.options")
     */
    public function onAuditorOptionsCallback(DataContainer $dc): array
    {
        $activeRecord = $dc->activeRecord;

        return $this->auditorUtil->getEntityAuditorsByTable($activeRecord->pid, $activeRecord->ptable);
    }
}
