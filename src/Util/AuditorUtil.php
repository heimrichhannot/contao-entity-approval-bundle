<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Util;

use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Model\Collection;

class AuditorUtil
{
    protected array        $bundleConfig;
    protected DatabaseUtil $databaseUtil;
    protected UserUtil     $userUtil;

    public function __construct(DatabaseUtil $databaseUtil, UserUtil $userUtil, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->databaseUtil = $databaseUtil;
        $this->userUtil = $userUtil;
    }

    public function getEntityAuditorsByTable(string $id, string $table): array
    {
        $options = [];

        foreach ($this->bundleConfig[$table]['auditor_levels'] as $level) {
            if (!empty($users = $this->getAuditorFromGroups($table, $level['name']))) {
                //check if one of this ids already has history entry with status approved
                $history = $this->databaseUtil->findResultsBy(
                    'tl_entity_approval_history',
                    ['tl_entity_approval_history.pid=?', 'tl_entity_approval_history.ptable=?', 'tl_entity_approval_history.state=?', 'tl_entity_approval_history.auditor in (?)'],
                    [$id, $table, EntityApprovalContainer::APPROVAL_STATE_APPROVED, implode(',', $users)]);

                if (null === $history) {
                    continue;
                }

                foreach ($users as $userId) {
                    $user = $this->databaseUtil->findResultByPk('tl_user', $userId);
                    $options[$user->id] = $level['name'].' - '.$user->email;
                }
            }
        }

        return $options;
    }

    public function getAuditorFromGroups(string $table, string $levelName): array
    {
        $key = false;

        foreach ($this->bundleConfig[$table]['auditor_levels'] as $levelKey => $level) {
            if (false === array_search($levelName, $level)) {
                continue;
            }
            $key = $levelKey;

            break;
        }

        if (false === $key) {
            return [];
        }

        $groups = $this->bundleConfig[$table]['auditor_levels'][$key]['groups'];
        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return [];
        }

        $auditors = $users->fetchAll();

        return array_column($auditors, 'id');
    }
}
