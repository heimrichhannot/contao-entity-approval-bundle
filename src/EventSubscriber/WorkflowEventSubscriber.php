<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkflowEventSubscriber implements EventSubscriberInterface
{
    protected TranslatorInterface $translator;
    protected LoggerInterface     $logger;

    public function __construct(LoggerInterface $logger, TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.entity_approvement.guard' => ['onWorkflowGuard'],
        ];
    }

    public function onWorkflowGuard(GuardEvent $event)
    {
        $workflow = $event->getWorkflow();
        $transition = $event->getTransition();
    }
}
