<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkflowEventSubscriber implements EventSubscriberInterface
{
    const BLOCKING_CODE_IS_NULL = '0';
    const BLOCKING_CODE_NOT_ALLOWED = '1';

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

        if (null === $transition || null === $transition->getName()) {
            $message = sprintf(
                $this->translator->trans('huh.entity_approvement.bocking.transition_is_null'),
                array_search(1, $event->getMarking()->getPlaces()),
                implode(',', $transition->getTos())
            );
            $blocker = new TransitionBlocker($message, static::BLOCKING_CODE_IS_NULL);
            $event->addTransitionBlocker($blocker);
            $event->setBlocked(true);

            return;
        }

        if (!\in_array($transition, $workflow->getDefinition()->getTransitions())) {
            $message = sprintf(
                $this->translator->trans('huh.entity_approvement.bocking.transition_not_allowed'),
                array_search(1, $event->getMarking()->getPlaces()),
                implode(',', $transition->getTos())
            );

            $blocker = new TransitionBlocker($message, static::BLOCKING_CODE_NOT_ALLOWED);
            $event->addTransitionBlocker($blocker);
            $event->setBlocked(true);

            return;
//            throw new TransitionException($event->getSubject(), $transition->getName(), $workflow, $message);
        }
    }
}
