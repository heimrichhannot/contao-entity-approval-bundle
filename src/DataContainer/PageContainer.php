<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;
use HeimrichHannot\UtilsBundle\Choice\DataContainerChoice;
use HeimrichHannot\UtilsBundle\Choice\FieldChoice;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;

class PageContainer
{
    protected DcaUtil             $dcaUtil;
    protected DataContainerChoice $dcChoice;
    protected FieldChoice         $fieldChoice;
    protected array               $bundleConfig;

    public function __construct(DcaUtil $dcaUtil, DataContainerChoice $dcChoice, FieldChoice $fieldChoice, array $bundleConfig)
    {
        $this->dcaUtil = $dcaUtil;
        $this->dcChoice = $dcChoice;
        $this->fieldChoice = $fieldChoice;
        $this->bundleConfig = $bundleConfig;
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

        return array_intersect($tables, $configured);
    }

    public function getAllFields(DataContainer $dc): array
    {
        return [];

        return $this->fieldChoice->getChoices();
    }

    public function getInitialAuditorGroups(DataContainer $dc): array
    {
        $options = [];

        return $options;
    }

    public function getAuditorGroups(DataContainer $dc): array
    {
        $options = [];

        return $options;
    }

    public function getInitialAuditorModes(DataContainer $dc): array
    {
        return \is_array(Configuration::AUDITOR_MODES) ? Configuration::AUDITOR_MODES : [];
    }
}
