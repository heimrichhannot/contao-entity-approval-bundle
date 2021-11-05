<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\EventSubscriber;

use Contao\BackendUser;
use Contao\Model;
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

    protected TranslatorInterface     $translator;
    protected LoggerInterface         $logger;
    protected WorkflowInterface       $entityApprovalStateMachine;
    protected array                   $bundleConfig;
    protected UserUtil                $userUtil;
    protected NotificationManager     $notificationManager;
    protected ModelUtil               $modelUtil;
    protected DatabaseUtil            $databaseUtil;
    protected EntityApprovalContainer $entityApprovalContainer;

    public function __construct(
        DatabaseUtil $databaseUtil,
        EntityApprovalContainer $entityApprovalContainer,
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
        $this->entityApprovalContainer = $entityApprovalContainer;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.entity_approval.entered.created' => ['onWorkflowEnteredInitialPlace'],
            'workflow.entity_approval.completed.submit' => ['onWorkflowTransitionSubmit'],
            'workflow.entity_approval.completed.assign_new_auditor' => ['onWorkflowTransitionAssignAuditor'],
            'workflow.entity_approval.completed.request_change' => ['onWorkflowTransitionRequestChange'],
            'workflow.entity_approval.completed.approve' => ['onWorkflowTransitionApprove'],
            'workflow.entity_approval.completed.reject' => ['onWorkflowTransitionReject'],
        ];
    }

    public function onWorkflowEnteredInitialPlace(EnteredEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $model->huh_aproval_auditor = $model->huh_approval_auditor ?: $this->getInitialAuditor($event);

        $backendUser = BackendUser::getInstance();

        $historyModel = new EntityApprovalHistoryModel();
        $historyModel->pid = $model->id;
        $historyModel->ptable = $model->getTable();
        $historyModel->transition = $event->getTransition()->getName();
        $historyModel->state = array_search(1, $event->getMarking()->getPlaces(), true);
        $historyModel->auditor = $model->huh_approval_auditor;
        $historyModel->notes = $model->huh_approval_notes;
        $historyModel->author = $backendUser->id;
        $historyModel->informAuthor = $model->huh_approval_inform_author;
        $this->saveHistoryEntry($historyModel);

        $this->resetModelApprovalValues($model, ['state' => $model->huh_approval_state]);
    }

    public function onWorkflowTransitionSubmit(CompletedEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($this->bundleConfig[$table]['auditor_levels'][0]['groups']))) {
            return;
        }

        $auditors = $users->fetchAll();

        //select initial auditor and send notification
        $mode = $this->bundleConfig[$table]['auditor_levels'][0]['mode'];
        $options = new NotificationCenterOptionsDto();

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $random = array_rand($auditors);
            $options->recipients = [$auditors[$random]['email']];
            $auditor = [$auditors[$random]['id']];
        } else {
            $options->recipients = array_column($auditors, 'email');
            $auditor = array_column($auditors, 'id');
        }

        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->state = array_search(1, $event->getMarking()->getPlaces(), true);
        $options->transition = $event->getTransition()->getName();
        $options->table = $table;
        $options->entityId = $model->id;
        $options->author = $model->author ?? '';

        $this->notificationManager->sendMail($options);

        $model->huh_approval_state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        $model->huh_approval_auditor = serialize($auditor);
        $model->save();

        $backendUser = BackendUser::getInstance();

        $history = new EntityApprovalHistoryModel();
        $history->pid = $model->id;
        $history->ptable = $table;
        $history->transition = EntityApprovalContainer::APPROVAL_TRANSITION_SUBMIT;
        $history->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        $history->auditor = $auditor;
        $history->author = $backendUser->id;
        $history->informAuthor = false;
        $this->saveHistoryEntry($history);
    }

    public function onWorkflowTransitionAssignAuditor(CompletedEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $informAuthor = (bool) $model->huh_approval_inform_author;
        $table = $model->getTable();

        if (!empty($model->huh_approval_auditor)) {
            if (null !== ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $model->huh_approval_auditor))) {
                $options = new NotificationCenterOptionsDto();
                $options->table = $table;
                $options->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                $options->auditor = $auditor->id;
                $options->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $options->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;
                $options->recipients = [$auditor->email];
                $this->notificationManager->sendMail($options);
            }
        }

        $backendUser = BackendUser::getInstance();

        $history = new EntityApprovalHistoryModel();
        $history->pid = $model->id;
        $history->ptable = $table;
        $history->dateAdded = time();
        $history->transition = EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR;
        $history->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        $history->auditor = serialize([$model->huh_approval_auditor]);
        $history->notes = $model->huh_approval_notes;
        $history->author = $backendUser->id;
        $history->informAuthor = $informAuthor;

        $this->saveHistoryEntry($history);

        if ($informAuthor) {
            $this->informAuthor(
                $table,
                $auditor->id,
                $model->{$this->bundleConfig[$table]['author_email_field']},
                EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT
            );
        }

        $this->resetModelApprovalValues($model, ['state' => EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT]);
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

        $history = new EntityApprovalHistoryModel();
        $history->pid = $model->id;
        $history->ptable = $table;
        $history->transition = EntityApprovalContainer::APPROVAL_TRANSITION_REQUEST_CHANGE;
        $history->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
        $history->auditor = serialize([$model->huh_approval_auditor]);
        $history->notes = $model->huh_approval_notes;
        $history->author = $backendUser->id;
        $history->informAuthor = $informAuthor;
        $this->saveHistoryEntry($history);

        if ($informAuthor) {
            $this->informAuthor(
                $table,
                $auditor->id,
                $model->{$this->bundleConfig[$table]['author_email_field']},
                EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT
            );
        }

        $this->resetModelApprovalValues($model, ['state' => EntityApprovalContainer::APPROVAL_STATE_CREATED]);
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

        $history = new EntityApprovalHistoryModel();
        $history->pid = $model->id;
        $history->ptable = $table;
        $history->transition = $event->getTransition()->getName();
        $history->state = $state;
        $history->auditor = serialize([$model->huh_approval_auditor]);
        $history->notes = $model->huh_approval_notes;
        $history->author = $backendUser->id;
        $history->informAuthor = $informAuthor;
        $this->saveHistoryEntry($history);

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
            $newAuditors = $this->entityApprovalContainer->getAuditorFromGroups($table, $levelName);

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

        $this->resetModelApprovalValues($model, ['state' => $model->huh_approval_state]);

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

        $history = new EntityApprovalHistoryModel();
        $history->pid = $model->id;
        $history->ptable = $table;
        $history->transition = $event->getTransition()->getName();
        $history->state = $state;
        $history->auditor = serialize([$model->huh_approval_auditor]);
        $history->notes = $model->huh_approval_notes;
        $history->author = $backendUser->id;
        $history->informAuthor = $informAuthor;

        $this->saveHistoryEntry($history);

        // reject entity
        $this->resetModelApprovalValues($model, ['state' => $state]);

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
            $auditor = array_column($auditors, 'id')[$random];
        } else {
            $options->recipients = array_column($auditors, 'email');
            $auditor = array_column($auditors, 'id');
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

    private function saveHistoryEntry(EntityApprovalHistoryModel $model): void
    {
        $model->dateAdded = $model->dateAdded ?? time();
        $model->authorType = $model->authorType ?? 'user';
        $model->notes = $model->notes ?? '';
        $model->save();
    }

    private function informAuthor(string $table, string $auditor, array $author, string $state): void
    {
        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = implode(',', $author);
        $options->auditor = $auditor;
        $options->state = $state;
        $options->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;
        $options->recipients = $author;
        $this->notificationManager->sendMail($options);
    }

    private function collectAllAuditorEmails(string $table): array
    {
        $groups = array_values(array_column($this->bundleConfig[$table]['auditor_levels'], 'groups'));

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return [];
        }

        $auditors = $users->fetchAll();

        return array_column($auditors, 'email');
    }

    private function collectAuditorEmails(array $userIds): array
    {
        /** @var Collection $users */
        if (null === ($users = $this->databaseUtil->findResultByPk('tl_user', $userIds))) {
            return [];
        }

        $auditors = $users->fetchAll();

        return array_column($auditors, 'email');
    }

    private function resetModelApprovalValues(Model $model, array $options = [])
    {
        $model->huh_approval_notes = $options['notes'] ?? '';
        $model->huh_approval_state = $options['state'] ?? '';
        $model->huh_approval_inform_author = $options['inform_author'] ?? '';
        $model->huh_approval_transition = $options['transition'] ?? '';
        $model->huh_approval_confirm_continue = $options['confirm_continue'] ?? '';
        $model->save();
    }
}
