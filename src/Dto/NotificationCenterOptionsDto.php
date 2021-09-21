<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Dto;

class NotificationCenterOptionsDto
{
    public string $state;
    public string $author;
    public string $table;
    public string $entityId;
    public string $type;
    public array $auditors = [];
    public array $auditorFormer = [];
    public array $auditorNew = [];
    public array $recipients = [];
}
