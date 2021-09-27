<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mvo\ContaoGroupWidget\Entity\AbstractGroupElementEntity;

/**
 * @ORM\Entity()
 * @ORM\Table(name="tl_entity_approvement_config_element")
 */
class EntityApprovementConfigElement extends AbstractGroupElementEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=EntityApprovementConfig::class, inversedBy="elements")
     */
    protected $parent;

    /**
     * @ORM\Column(name="entity", type="string", length=64, options={"default": ""})
     */
    protected string $entity = '';

    /**
     * @ORM\Column(name="initial_auditor_groups", type="string", length=32, options={"default": ""})
     */
    protected string $initialAuditorGroups = '';

    /**
     * @ORM\Column(name="initial_auditor_mode", type="string", length=8, options={"default": ""})
     */
    protected string $initialAuditorMode = '';

    /**
     * @ORM\Column(name="auditor_groups", type="string", length=64, options={"default": ""})
     */
    protected string $auditorGroups = '';

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getInitialAuditorGroups(): string
    {
        return $this->initialAuditorGroups;
    }

    public function setInitialAuditorGroups(string $initialAuditorGroups): void
    {
        $this->initialAuditorGroups = $initialAuditorGroups;
    }

    public function getInitialAuditorMode(): string
    {
        return $this->initialAuditorMode;
    }

    public function setInitialAuditorMode(string $initialAuditorMode): void
    {
        $this->initialAuditorMode = $initialAuditorMode;
    }

    public function getAuditorGroups(): string
    {
        return $this->auditorGroups;
    }

    public function setAuditorGroups(string $auditorGroups): void
    {
        $this->auditorGroups = $auditorGroups;
    }
}
