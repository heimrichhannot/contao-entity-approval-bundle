<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\EventSubscriber;

use Contao\Model;
use HeimrichHannot\EntityApprovalBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovalBundle\Dto\NotificationCenterOptionsDto;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkflowEventSubscriber implements EventSubscriberInterface
{
    const ENTITY_APPROVAL_WORKFLOW_NAME = 'antity_approval';

    protected TranslatorInterface $translator;
    protected LoggerInterface     $logger;
    protected WorkflowInterface   $entityApprovalStateMachine;
    protected array               $bundleConfig;
    protected UserUtil            $userUtil;
    protected NotificationManager $notificationManager;

    public function __construct(
        LoggerInterface $logger,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        UserUtil $userUtil,
        WorkflowInterface $entityApprovalStateMachine,
        array $bundleConfig
    ) {
        $this->translator = $translator;
        $this->logger = $logger;
        $this->entityApprovalStateMachine = $entityApprovalStateMachine;
        $this->bundleConfig = $bundleConfig;
        $this->userUtil = $userUtil;
        $this->notificationManager = $notificationManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.entity_approval.entered.wait_for_initial_auditor' => ['onWorkflowEnteredInitialPlace'],
        ];
    }

    public function onWorkflowEnteredInitialPlace(EnteredEvent $event)
    {
        if ($event->getWorkflow()->getName() !== static::ENTITY_APPROVAL_WORKFLOW_NAME) {
            return;
        }

        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model::getTable();

        //select initial auditor and send notification
        $mode = $this->bundleConfig[$table]['initial_auditor_mode'];
        $groups = explode(',', $this->bundleConfig[$table]['initial_auditor_groups']);

        $auditors = $this->userUtil->findActiveByGroups($groups);

        $options = new NotificationCenterOptionsDto();
        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->state = array_search(1, $event->getMarking()->getPlaces(), true);
        $options->transition = $event->getTransition()->getName();
        $options->table = $table;
        $options->entityId = $model->id;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $options->recipients = [$auditors[array_rand($auditors)]];
        } else {
            $options->recipients = $auditors;
        }

        $this->notificationManager->sendMail($options);
    }
}
