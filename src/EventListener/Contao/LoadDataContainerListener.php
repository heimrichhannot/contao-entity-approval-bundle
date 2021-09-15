<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\EventListener\Contao;

class LoadDataContainerListener
{
    /**
     * @var array
     */
    private $config;

    public function __construct(array $bundleConfig)
    {
        $this->config = $bundleConfig;
    }

    public function __invoke(string $table): void
    {
        if (\in_array($table, array_keys($this->config['entity_name']))) {
            $this->updateDc($table);
        }
    }

    private function updateDc($table): void
    {
    }
}
