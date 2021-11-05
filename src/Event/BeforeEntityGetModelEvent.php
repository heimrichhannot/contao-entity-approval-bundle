<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class BeforeEntityGetModelEvent extends Event
{
    public const NAME = 'huh.entity_approval.before_entity_get_model_event';

    protected string $table;
    protected string $entityId;

    public function __construct(
        string $table,
        string $entityId
    ) {
        $this->table = $table;
        $this->entityId = $entityId;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): void
    {
        $this->table = $table;
    }
}
