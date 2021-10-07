<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\EventSubscriber;

use Contao\BackendUser;
use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\EntityApprovalBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovalBundle\Dto\NotificationCenterOptionsDto;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;
use HeimrichHannot\EntityApprovalBundle\Model\EntityApprovalHistoryModel;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Model\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkflowEventSubscriber implements EventSubscriberInterface
{
    const ENTITY_APPROVAL_WORKFLOW_NAME = 'entity_approval';

    protected TranslatorInterface $translator;
    protected LoggerInterface     $logger;
    protected WorkflowInterface   $entityApprovalStateMachine;
    protected array               $bundleConfig;
    protected UserUtil            $userUtil;
    protected NotificationManager $notificationManager;
    protected ModelUtil           $modelUtil;
    protected DatabaseUtil        $databaseUtil;

    public function __construct(
        DatabaseUtil $databaseUtil,
        LoggerInterface $logger,
        ModelUtil $modelUtil,
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
        $this->modelUtil = $modelUtil;
        $this->databaseUtil = $databaseUtil;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.entity_approval.entered.created' => ['onWorkflowEnteredInitialPlace'],
//            'workflow.entity_approval.completed.submit' => ['onWorkflowTransitionSubmit'],
//            'workflow.entity_approval.completed.assign_new_auditor' => ['onWorkflowTransitionAssignAuditor'],
//            'workflow.entity_approval.completed.request_change' => ['onWorkflowTransitionRequestChange'],
//            'workflow.entity_approval.completed.approve' => ['onWorkflowTransitionApprove'],
//            'workflow.entity_approval.completed.reject' => ['onWorkflowTransitionReject']
        ];
    }

    public function onWorkflowEnteredInitialPlace(EnteredEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $model->huh_aproval_auditor = $model->huh_approval_auditor ?: $this->getInitialAuditor($event);

        $backendUser = BackendUser::getInstance();
        $this->createHistoryEntry(
            $model->id,
            $model->getTable(),
            $event->getTransition()->getName(),
            array_search(1, $event->getMarking()->getPlaces(), true),
            $model->huh_approval_auditor,
            $model->huh_approval_notes,
            $model->huh_approval_inform_author,
            $backendUser->id
        );

        $model->huh_approval_notes = '';
        $model->huh_approval_inform_author = '';
        $model->huh_approval_transition = '';
        $model->huh_approval_confirm_continue = '';
        $model->save();
    }

    public function onWorkflowTransitionSubmit(CompletedEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $key = array_search('initial', array_column($this->bundleConfig[$table]['auditor_levels'], 'name'));

        if (empty($groups = $this->bundleConfig[$table]['auditor_levels'][$key]['groups'])) {
            return;
        }

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return;
        }

        $auditors = $users->fetchAll();

        //select initial auditor and send notification
        $mode = $this->bundleConfig[$table]['auditor_levels'][$key]['mode'];
        $options = new NotificationCenterOptionsDto();

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $random = array_rand($auditors);
            $options->recipients = $auditors[$random]['email'];
            $auditor = $auditors[$random]['id'];
        } else {
            $options->recipients = implode(',', array_column($auditors, 'email'));
            $auditor = implode(',', array_column($auditors, 'id'));
        }

        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->state = array_search(1, $event->getMarking()->getPlaces(), true);
        $options->transition = $event->getTransition()->getName();
        $options->table = $table;
        $options->entityId = $model->id;
        $options->author = $model->{$this->bundleConfig[$table]['author_email_field']};

        $this->notificationManager->sendMail($options);

        $model->huh_approval_state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        $model->huh_approval_auditor_initial = $options->recipients;
        $model->save();

        $backendUser = BackendUser::getInstance();

        $this->createHistoryEntry(
            $model->id,
            $table,
            EntityApprovalContainer::APPROVAL_TRANSITION_SUBMIT,
            EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT,
            $auditor,
            '',
            false,
            $backendUser->id
        );
    }

    public function onWorkflowTransitionAssignAuditor(CompletedEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $informAuthor = $model->huh_approval_inform_author;
        $table = $model->getTable();

        if (!empty($model->huh_approval_auditor)) {
            if (null !== ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $model->huh_approval_auditor))) {
                $options = new NotificationCenterOptionsDto();
                $options->table = $table;
                $options->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                $options->auditor = $auditor->id;
                $options->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $options->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;
                $options->recipients = $auditor->email;
                $this->notificationManager->sendMail($options);
            }
        }

        $backendUser = BackendUser::getInstance();

        $this->createHistoryEntry(
            $model->id,
            $table,
            EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR,
            EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT,
            $model->huh_approval_auditor,
            $model->huh_approval_notes,
            $informAuthor,
            $backendUser->id
        );

        if ((bool) $informAuthor) {
            $this->informAuthor(
                $table,
                $auditor->id,
                $model->{$this->bundleConfig[$table]['author_email_field']},
                EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT
            );
        }

        $model->huh_approval_notes = '';
        $model->huh_approval_state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        $model->huh_approval_transition = '';
        $model->huh_approval_inform_author = '';
        $model->huh_approval_confirm_continue = '';
        $model->save();
    }

    public function onWorkflowTransitionRequestChange(CompletedEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $informAuthor = (bool) $model->huh_approval_inform_author;
        $table = $model->getTable();

        if (null === ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $model->huh_approval_auditor))) {
            return;
        }

        if (!empty($model->huh_approval_auditor)) {
            $options = new NotificationCenterOptionsDto();
            $options->table = $table;
            $options->author = $model->{$this->bundleConfig[$table]['author_email_field']};
            $options->auditor = $auditor->id;
            $options->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
            $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
            $options->recipients = $auditor->email;
            $this->notificationManager->sendMail($options);
        }

        $backendUser = BackendUser::getInstance();

        $this->createHistoryEntry(
            $model->id,
            $table,
            EntityApprovalContainer::APPROVAL_TRANSITION_REQUEST_CHANGE,
            EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT,
            $model->huh_approval_auditor,
            $model->huh_approval_notes,
            $informAuthor,
            $backendUser->id
        );

        if ($informAuthor) {
            $this->informAuthor(
                $table,
                $auditor->id,
                $model->{$this->bundleConfig[$table]['author_email_field']},
                EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT
            );
        }

        $model->huh_approval_state = EntityApprovalContainer::APPROVAL_STATE_CREATED;
        $model->huh_approval_inform_author = '';
        $model->huh_approval_confirm_continue = '';
        $model->huh_approval_notes = '';
        $model->huh_approval_transition = '';
        $model->save();
    }

    public function onWorkflowTransitionApprove(EnteredEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();
        $informAuthor = (bool) $model->huh_approval_inform_author;
        $state = array_search(1, $event->getMarking()->getPlaces(), true);

        $backendUser = BackendUser::getInstance();
        $author = $model->{$this->bundleConfig[$table]['author_email_field']};

        // create history
        $this->createHistoryEntry(
            $model->id,
            $table,
            $event->getTransition()->getName(),
            $state,
            $model->huh_approval_auditor,
            $model->huh_approval_notes,
            $informAuthor,
            $backendUser->id
        );

        // check if all auditors approved
        $history = $this->databaseUtil->findResultsBy(
            'tl_entity_approval_history',
            ['tl_entity_approval_history.pid=?', 'tl_entity_approval_history.ptable=?'],
            [$model->id, $table]
        )->fetchAllAssoc();

        $approvalHistoryAuditors = array_column($history, 'auditor');

        // get users per approval level
        $levelApproval = [];
        $levelUsers = [];

        foreach ($this->bundleConfig[$table]['auditor_levels'] as $level) {
            /** @var Collection $users */
            if (null === ($users = $this->userUtil->findActiveByGroups($level['groups']))) {
                continue;
            }
            $levelUsers[$level['name']] = array_column($users->fetchAll(), 'id');

            if (array_intersect($levelUsers[$level['name']], $approvalHistoryAuditors)) {
                $levelApproval[$level['name']] = true;

                continue;
            }
            $levelApproval[$level['name']] = false;
        }

        // check if unapproved levels
        if (\in_array(false, $levelApproval, true)) {
            // select next level
            $levelName = array_search(false, $levelApproval);
            $newAuditors = $this->getAuditorFromGroups($table, $levelName);

            if (!empty($model->huh_approval_auditor)) {
                $options = new NotificationCenterOptionsDto();
                $options->table = $table;
                $options->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                $options->auditor = $model->huh_approval_auditor;
                $options->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
                $options->recipients = $this->collectAuditorEmails($newAuditors);
                $this->notificationManager->sendMail($options);
            }

            $model->huh_approval_auditor = $newAuditors;
            $model->huh_approval_state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        } else {
            $model->huh_approval_state = EntityApprovalContainer::APPROVAL_STATE_APPROVED;
        }

        $model->huh_approval_inform_author = '';
        $model->huh_approval_confirm_continue = '';
        $model->huh_approval_notes = '';
        $model->huh_approval_transition = '';
        $model->save();

        // send mail to author
        if ((bool) $model->huh_approval_inform_author) {
            $this->informAuthor($table, $model->huh_approval_auditor, $author, $state);
        }
    }

    public function onWorkflowTransitionReject(EnteredEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();
        $informAuthor = (bool) $model->huh_approval_inform_author;
        $state = array_search(1, $event->getMarking()->getPlaces(), true);

        $backendUser = BackendUser::getInstance();
        $author = $model->{$this->bundleConfig[$table]['author_email_field']};

        // create history
        $this->createHistoryEntry(
            $model->id,
            $table,
            $event->getTransition()->getName(),
            $state,
            $model->huh_approval_auditor,
            $model->huh_approval_notes,
            $informAuthor,
            $backendUser->id
        );

        // reject entity
        $model->huh_approval_state = $state;
        $model->huh_approval_inform_author = '';
        $model->huh_approval_confirm_continue = '';
        $model->huh_approval_notes = '';
        $model->huh_approval_transition = '';
        $model->save();

        // send mail to all auditors
        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $model->{$this->bundleConfig[$table]['author_email_field']};
        $options->auditor = $model->huhApproval_auditor ?: '';
        $options->state = $state;
        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->entityId = $model->id;
        $options->transition = $event->getTransition()->getName();
        $options->recipients = $this->collectAllAuditorEmails($table);
        $this->notificationManager->sendMail($options);

        // send mail to author
        if ((bool) $model->huh_approval_inform_author) {
            $this->informAuthor($table, $model->huh_approval_auditor, $author, $state);
        }
    }

    private function getInitialAuditor(EnteredEvent $event): string
    {
        $model = $event->getSubject();
        $table = $model->getTable();

        $groups = explode(',', $this->bundleConfig[$table]['auditor_levels'][0]);

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return '';
        }

        $auditors = $users->fetchAll();
        $mode = $this->bundleConfig[$table]['auditor_levels'][0]['mode'];
        $options = new NotificationCenterOptionsDto();

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $random = array_rand($auditors);
            $options->recipients = $auditors[$random]['email'];
            $auditor = serialize(array_column($auditors, 'id')[$random]);
        } else {
            $options->recipients = implode(',', array_column($auditors, 'email'));
            $auditor = serialize(array_column($auditors, 'id'));
        }

        $entityAuthor = $model->{$this->bundleConfig[$table]['author_email_field']};
        $state = array_search(1, $event->getMarking()->getPlaces(), true);

        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->state = $state;
        $options->transition = $event->getTransition()->getName();
        $options->table = $table;
        $options->entityId = $model->id;
        $options->author = $entityAuthor;
        $this->notificationManager->sendMail($options);

        $this->informAuthor($table, $auditor, $entityAuthor, $state);

        return $auditor;
    }

    private function createHistoryEntry(
        int $entityId,
        string $table,
        string $transition,
        string $state,
        string $auditor,
        string $notes,
        bool $informAuthor,
        string $author,
        string $authorType = 'user'
    ): void {
        $historyModel = new EntityApprovalHistoryModel();
        $historyModel->pid = $entityId;
        $historyModel->ptable = $table;
        $historyModel->dateAdded = time();
        $historyModel->transition = $transition;
        $historyModel->state = $state;
        $historyModel->auditor = $auditor;
        $historyModel->notes = $notes;
        $historyModel->author = $author;
        $historyModel->authorType = $authorType;
        $historyModel->informAuthor = $informAuthor;
        $historyModel->save();
    }

    private function informAuthor(string $table, string $auditor, string $author, string $state): void
    {
        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $author;
        $options->auditor = $auditor;
        $options->state = $state;
        $options->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;
        $options->recipients = $author;
        $this->notificationManager->sendMail($options);
    }

    private function getAuditorFromGroups(string $table, string $levelName): string
    {
        $groups = $this->bundleConfig[$table]['auditor_levels'][$levelName]['groups'];
        $mode = $this->bundleConfig[$table]['auditor_levels'][$levelName]['mode'];

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return '';
        }

        $auditors = $users->fetchAll();

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $random = array_rand($auditors);
            $auditor = serialize(array_column($auditors, 'id')[$random]);
        } else {
            $auditor = serialize(array_column($auditors, 'id'));
        }

        return $auditor;
    }

    private function collectAllAuditorEmails(string $table): string
    {
        $groups = array_values(array_column($this->bundleConfig[$table]['auditor_levels'], 'groups'));

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return '';
        }

        $auditors = $users->fetchAll();

        return implode(',', array_column($auditors, 'email'));
    }

    private function collectAuditorEmails(string $users): string
    {
        /** @var Collection $users */
        if (null === ($users = $this->databaseUtil->findResultByPk('tl_user', StringUtil::deserialize($users, true)))) {
            return '';
        }

        $auditors = $users->fetchAll();

        return implode(',', array_column($auditors, 'email'));
    }
}
