<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mvo\ContaoGroupWidget\Entity\AbstractGroupEntity;

/**
 * @ORM\Entity()
 * @ORM\Table(name="tl_entity_approvement_config")
 */
class EntityApprovementConfig extends AbstractGroupEntity
{
    /**
     * @ORM\OneToMany(targetEntity=EntityApprovementConfigElement::class, mappedBy="parent", orphanRemoval=true)
     */
    protected $elements;
}
