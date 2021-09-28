<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
use Contao\UserGroupModel;
use Doctrine\ORM\EntityManagerInterface;
use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;
use HeimrichHannot\UtilsBundle\Choice\DataContainerChoice;

class PageContainer
{
    protected DataContainerChoice    $dcChoice;
    protected array                  $bundleConfig;
    protected EntityManagerInterface $entityManager;

    public function __construct(
        DataContainerChoice $dcChoice,
        EntityManagerInterface $entityManager,
        array $bundleConfig)
    {
        $this->dcChoice = $dcChoice;
        $this->bundleConfig = $bundleConfig;
        $this->entityManager = $entityManager;
    }

    public function onSaveEntityApprovement($value, DataContainer $dc)
    {
        return $value;
    }

    public function getEntityApprovementConfigs(DataContainer $dc): array
    {
        $options = [];

        if (!empty($this->bundleConfig)) {
            $options = array_keys($this->bundleConfig);
        }

        return $options;
    }

    public function getAllEntities(DataContainer $dc): array
    {
        $tables = $this->dcChoice->getChoices();
        $configured = array_keys($this->bundleConfig);

        return array_values(array_intersect($tables, $configured));
    }

    public function getAuditorGroups(DataContainer $dc): array
    {
        $options = [];

        $userGroups = UserGroupModel::findAll();

        /** @var UserGroupModel $group */
        foreach ($userGroups as $group) {
            if (empty($group->name)) {
                continue;
            }
            $options[$group->id] = $group->name;
        }

        return $options;
    }

    public function getInitialAuditorModes(DataContainer $dc): array
    {
        return \is_array(Configuration::AUDITOR_MODES) ? Configuration::AUDITOR_MODES : [];
    }
}
