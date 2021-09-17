<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\EntityApprovementBundle\Manager\EntityApprovementWorkflowManager;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class EntityApprovementContainer
{
    const APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR = 'wait_for_initial_auditor';
    const APPROVEMENT_STATE_IN_PROGRESS = 'in_progress';
    const APPROVEMENT_STATE_CHANGES_REQUESTED = 'changes_requested';
    const APPROVEMENT_STATE_APPROVED = 'approved';
    const APPROVEMENT_STATE_REJECTED = 'rejected';

    const APPROVEMENT_STATES = [
        self::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR,
        self::APPROVEMENT_STATE_IN_PROGRESS,
        self::APPROVEMENT_STATE_CHANGES_REQUESTED,
        self::APPROVEMENT_STATE_APPROVED,
        self::APPROVEMENT_STATE_REJECTED,
    ];

    protected array                            $bundleConfig;
    protected DatabaseUtil                     $databaseUtil;
    protected EntityApprovementWorkflowManager $workflowManager;
    protected ModelUtil $modelUtil;

    public function __construct(
        DatabaseUtil $databaseUtil,
        ModelUtil $modelUtil,
        EntityApprovementWorkflowManager $workflowManager,
        array $bundleConfig
    ) {
        $this->bundleConfig = $bundleConfig;
        $this->databaseUtil = $databaseUtil;
        $this->workflowManager = $workflowManager;
        $this->modelUtil = $modelUtil;
    }

    public function getAuditors(?DataContainer $dc): array
    {
        $options = [];

        $groups = explode(',', $this->bundleConfig[$dc->table]['auditor_groups']);

        $activeGroups = $this->databaseUtil->findResultsBy('tl_user_group', ['tl_user_group.disable!=?'], ['1'])->fetchAllAssoc();

        foreach ($activeGroups as $group) {
            if (\in_array($group['id'], $groups)) {
                $options[$group['id']] = $group['name'];
            }
        }

        return $options;
    }

    public function startWorkflow(string $table, int $insertId, array $fields, DataContainer $dc): void
    {
        $model = $this->modelUtil->findModelInstanceByPk($table, $insertId);
        $this->workflowManager->startWorkflow($model);
    }

    public function applyWorkflowState(DataContainer $dc): void
    {
        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
        $this->workflowManager->workflowStateChange($model);
    }

    public function onAuditorsSave($value, DataContainer $dc)
    {
        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
        $this->workflowManager->workflowAuditorChange($value, $model);

        return $value;
    }
}
