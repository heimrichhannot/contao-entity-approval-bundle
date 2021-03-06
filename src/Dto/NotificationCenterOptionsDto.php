<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Dto;

class NotificationCenterOptionsDto
{
    public string $state = '';
    public string $transition = '';
    public string $author = '';
    public string $table = '';
    public string $entityId = '';
    public string $type = '';
    public string $auditor = '';
    public string $auditorNew = '';
    public array $recipients = [];
}
