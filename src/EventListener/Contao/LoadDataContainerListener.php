<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\EventListener\Contao;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use HeimrichHannot\EntityApprovementBundle\Manager\DcaManager;

/**
 * @Hook("loadDataContainer")
 */
class LoadDataContainerListener
{
    /**
     * @var DcaManager
     */
    protected $manager;
    /**
     * @var array
     */
    private $config;

    public function __construct(DcaManager $manager, array $bundleConfig)
    {
        $this->manager = $manager;
        $this->config = $bundleConfig;
    }

    public function __invoke(string $table): void
    {
        if ('tl_page' === $table) {
            $this->manager->addApprovementConfigToPage();
        }

        if (\in_array($table, array_keys($this->config))) {
            $this->manager->addApprovementToDca($table);
        }
    }
}
