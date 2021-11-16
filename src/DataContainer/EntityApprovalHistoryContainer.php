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
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;

class EntityApprovalHistoryContainer
{
    protected array                   $bundleConfig;
    protected DatabaseUtil            $databaseUtil;
    protected UserUtil                $userUtil;
    protected EntityApprovalContainer $entityApprovalContainer;
    protected AuditorUtil             $auditorUtil;
    protected ModelUtil               $modelUtil;

    public function __construct(
        AuditorUtil $auditorUtil,
        DatabaseUtil $databaseUtil,
        EntityApprovalContainer $entityApprovalContainer,
        ModelUtil $modelUtil,
        UserUtil $userUtil,
        array $bundleConfig
    ) {
        $this->bundleConfig = $bundleConfig;
        $this->databaseUtil = $databaseUtil;
        $this->userUtil = $userUtil;
        $this->entityApprovalContainer = $entityApprovalContainer;
        $this->auditorUtil = $auditorUtil;
        $this->modelUtil = $modelUtil;
    }

    /**
     * @Callback(table="tl_entity_approval_history", target="config.onload")
     */
    public function onLoadEntityApprovalHistory(DataContainer $dc)
    {
        $test = '';
    }

    /**
     * @Callback(table="tl_entity_approval_history", target="fields.auditor.options")
     */
    public function onAuditorOptionsCallback(DataContainer $dc): array
    {
        $activeRecord = $dc->activeRecord;

        return $this->auditorUtil->getEntityAuditorsByTable($activeRecord->pid, $activeRecord->ptable);
    }

    /**
     * @Callback(table="tl_entity_approval_history", target="list.label.label")
     */
    public function prepareListLabels(array $row, string $label, DataContainer $dc, array $columns): array
    {
        $labels = $columns;

        if (null !== ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $columns[3]))) {
            $labels[3] = $auditor->firstname.' '.$auditor->lastname.' '.$auditor->email;
        }

        return $labels;
    }
}
