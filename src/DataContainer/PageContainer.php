<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
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
        return $this->dcChoice->getChoices();
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
        $options = [];

        return $options;
    }
}
