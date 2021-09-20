<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;

class WorkflowEventSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents()
    {
        return [
//            LeaveEvent::class => ['onWorkflowLeave'],
//            GuardEvent::class => ['onWorkflowGuard'],
//            'workflow.entity_approvement.guard.wait_for_initial_auditor' => ['onWorkflowGuard'],
//            'workflow.entity_approvement.guard.in_progress' => ['onWorkflowGuard'],
//            'workflow.entity_approvement.guard.changes_requested' => ['onWorkflowGuard'],
//            'workflow.entity_approvement.guard.approved' => ['onWorkflowGuard'],
//            'workflow.entity_approvement.guard.rejected' => ['onWorkflowGuard'],
        ];
    }

    public function onWorkflowLeave(LeaveEvent $event)
    {
        $sub = $event->getSubject();
        $test = 'r';
    }

    public function onWorkflowGuard(GuardEvent $event)
    {
        $sub = $event->getSubject();
        $test = 't';
    }
}
