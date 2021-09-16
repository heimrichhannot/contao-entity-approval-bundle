<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;

class EntityApprovementContainer
{
    const APPROVEMENT_STATE_IN_PROGRESS = 'in_progress';
    const APPROVEMENT_STATE_CHANGES_REQUESTED = 'changes_requested';
    const APPROVEMENT_STATE_APPROVED = 'approved';
    const APPROVEMENT_STATE_REJECTED = 'rejected';

    const APPROVEMENT_STATES = [
        self::APPROVEMENT_STATE_IN_PROGRESS,
        self::APPROVEMENT_STATE_CHANGES_REQUESTED,
        self::APPROVEMENT_STATE_APPROVED,
        self::APPROVEMENT_STATE_REJECTED,
    ];
    /**
     * @var array
     */
    protected $bundleConfig;
    /**
     * @var DatabaseUtil
     */
    protected $databaseUtil;

    public function __construct(DatabaseUtil $databaseUtil, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->databaseUtil = $databaseUtil;
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
}
