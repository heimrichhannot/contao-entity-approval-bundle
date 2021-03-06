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
use HeimrichHannot\EntityApprovalBundle\Util\AuditorUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Model\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use function Symfony\Component\String\b;
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
    protected AuditorUtil             $auditorUtil;

    public function __construct(
        DatabaseUtil $databaseUtil,
        EntityApprovalContainer $entityApprovalContainer,
        LoggerInterface $logger,
        ModelUtil $modelUtil,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        UserUtil $userUtil,
        WorkflowInterface $entityApprovalStateMachine,
        AuditorUtil $auditorUtil,
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
        $this->auditorUtil = $auditorUtil;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.entity_approval.entered.created' => ['onWorkflowEnteredInitialPlace'],
            'workflow.entity_approval.completed' => ['onWorkflowTransitionCompleted'],
        ];
    }

    public function onWorkflowTransitionCompleted(CompletedEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();
        $informAuthor = (bool) $model->huh_approval_inform_author;
        $transition = $event->getTransition()->getName();
        $backendUser = BackendUser::getInstance();
        $request = Request::createFromGlobals();

        $resetOptions = [];
        $informAuthorOptions = [];
        $notificationOptions = new NotificationCenterOptionsDto();
        $history = new EntityApprovalHistoryModel();

        switch ($transition) {
            case EntityApprovalContainer::APPROVAL_TRANSITION_SUBMIT:
                /** @var Collection $users */
                if (null === ($users = $this->userUtil->findActiveByGroups($this->bundleConfig[$table]['auditor_levels'][0]['groups']))) {
                    return;
                }

                $auditors = $users->fetchAll();

                //select initial auditor and fill in notification data
                $mode = $this->bundleConfig[$table]['auditor_levels'][0]['mode'];

                if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
                    $random = array_rand($auditors);
                    $notificationOptions->recipients = [$auditors[$random]['email']];
                    $auditor = [$auditors[$random]['id']];
                } else {
                    $notificationOptions->recipients = array_column($auditors, 'email');
                    $auditor = array_column($auditors, 'id');
                }

                $notificationOptions->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
                $notificationOptions->state = array_search(1, $event->getMarking()->getPlaces(), true);
                $notificationOptions->transition = $event->getTransition()->getName();
                $notificationOptions->table = $table;
                $notificationOptions->entityId = $model->id;
                $notificationOptions->author = $model->author ?? '';

                // fill updated model values
                $resetOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $resetOptions['auditor'] = serialize($auditor);

                foreach ($backendUser->groups as $group) {
                    $resetOptions['group'][$group] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                }

                // fill in history data
                $history->pid = $model->id;
                $history->ptable = $table;
                $history->transition = EntityApprovalContainer::APPROVAL_TRANSITION_SUBMIT;
                $history->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $history->auditor = $auditor;
                $history->author = $backendUser->id;
                $history->informAuthor = false;

                break;

            case EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR:
                if (!empty($request->get('huh_approval_auditor'))) {
                    if (null !== ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $request->get('huh_approval_auditor')))) {
                        $notificationOptions->entityId = $model->id;
                        $notificationOptions->table = $table;
                        $notificationOptions->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                        $notificationOptions->auditor = $auditor->id;
                        $notificationOptions->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                        $notificationOptions->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;
                        $notificationOptions->recipients = [$auditor->email];
                    }
                }

                $history->pid = $model->id;
                $history->ptable = $table;
                $history->dateAdded = time();
                $history->transition = EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR;
                $history->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $history->auditor = serialize([$request->get('huh_approval_auditor')]);
                $history->notes = $request->get('huh_approval_notes');
                $history->author = $backendUser->id;
                $history->informAuthor = (bool) $request->get('huh_approval_inform_author');

                $informAuthorOptions['table'] = $table;
                $informAuthorOptions['auditor'] = $request->get('huh_approval_auditor');
                $informAuthorOptions['authorEmail'] = $model->{$this->bundleConfig[$table]['author_email_field']};
                $informAuthorOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;

                $resetOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;

                // apply in_audit state to entity group state
                $auditorGroups = StringUtil::deserialize($auditor->groups, true);

                foreach ($auditorGroups as $group) {
                    $resetOptions['group'][$group] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                }

                break;

            case EntityApprovalContainer::APPROVAL_TRANSITION_REQUEST_CHANGE:
                if (null === ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $model->huh_approval_auditor))) {
                    return;
                }

                if (!empty($model->huh_approval_auditor)) {
                    $notificationOptions->table = $table;
                    $notificationOptions->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                    $notificationOptions->auditor = $auditor->id;
                    $notificationOptions->entityId = $model->id;
                    $notificationOptions->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                    $notificationOptions->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
                    $notificationOptions->recipients = $auditor->email;
                }

                $history->pid = $model->id;
                $history->ptable = $table;
                $history->transition = EntityApprovalContainer::APPROVAL_TRANSITION_REQUEST_CHANGE;
                $history->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                $history->auditor = serialize([$model->huh_approval_auditor]);
                $history->notes = $model->huh_approval_notes;
                $history->author = $backendUser->id;
                $history->informAuthor = $informAuthor;

                $informAuthorOptions['table'] = $table;
                $informAuthorOptions['auditor'] = $auditor->id;
                $informAuthorOptions['authorEmail'] = $model->{$this->bundleConfig[$table]['author_email_field']};
                $informAuthorOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;

                $resetOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_CREATED;

                foreach ($backendUser->groups as $group) {
                    $resetOptions['group'][$group] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                }

                break;

            case EntityApprovalContainer::APPROVAL_TRANSITION_APPROVE:
                $state = array_search(1, $event->getMarking()->getPlaces(), true);

                $history->pid = $model->id;
                $history->ptable = $table;
                $history->transition = $event->getTransition()->getName();
                $history->state = $state;
                $history->auditor = serialize([$model->huh_approval_auditor]);
                $history->notes = $model->huh_approval_notes;
                $history->author = $backendUser->id;
                $history->informAuthor = $informAuthor;

                if ($this->isEntityApproved($model)) {
                    $resetOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_APPROVED;
                } else {
                    if (null !== ($levelName = $this->auditorUtil->getNextAuditorGroupName($model))) {
                        $newAuditor = $this->auditorUtil->getAuditorFromGroups($table, $levelName);

                        if (!empty($newAuditor)) {
                            $notificationOptions->table = $table;
                            $notificationOptions->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                            $notificationOptions->auditor = $newAuditor[0];
                            $notificationOptions->state = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                            $notificationOptions->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
                            $notificationOptions->recipients = $this->collectAuditorEmails($newAuditor);
                            $notificationOptions->entityId = $model->id;

                            $resetOptions['auditor'] = $newAuditor;
                        }
                    }
                    $resetOptions['state'] = EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT;
                }

                foreach ($backendUser->groups as $group) {
                    $resetOptions['group'][$group] = EntityApprovalContainer::APPROVAL_STATE_APPROVED;
                }

                break;

            case EntityApprovalContainer::APPROVAL_TRANSITION_REJECT:
                $state = array_search(1, $event->getMarking()->getPlaces(), true);

                $history->pid = $model->id;
                $history->ptable = $table;
                $history->transition = $event->getTransition()->getName();
                $history->state = $state;
                $history->auditor = serialize([$model->huh_approval_auditor]);
                $history->notes = $model->huh_approval_notes;
                $history->author = $backendUser->id;
                $history->informAuthor = $informAuthor;

                // reject entity
                $resetOptions['state'] = $state;

                foreach ($backendUser->groups as $group) {
                    $resetOptions['group'][$group] = EntityApprovalContainer::APPROVAL_STATE_REJECTED;
                }

                // send mail to all auditors
                $notificationOptions->table = $table;
                $notificationOptions->author = $model->{$this->bundleConfig[$table]['author_email_field']};
                $notificationOptions->auditor = $model->huhApproval_auditor ?: '';
                $notificationOptions->state = $state;
                $notificationOptions->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
                $notificationOptions->entityId = $model->id;
                $notificationOptions->transition = $event->getTransition()->getName();
                $notificationOptions->recipients = $this->collectAllAuditorEmails($table);

                break;

            default:
                break;
        }

        if ($informAuthor) {
            $this->informAuthor($informAuthorOptions);
        }

        $this->setModelApprovalValues($model, $resetOptions);
        $this->saveHistoryEntry($history);
        $this->notificationManager->sendMail($notificationOptions);
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

        $this->setModelApprovalValues($model, ['state' => $model->huh_approval_state]);
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

    private function informAuthor(array $options): void
    {
        $notification = new NotificationCenterOptionsDto();
        $notification->table = $options['table'];
        $notification->author = implode(',', $options['authorEmail']);
        $notification->auditor = $options['auditor'];
        $notification->state = $options['state'];
        $notification->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;
        $notification->recipients = $options['authorEmail'];
        $this->notificationManager->sendMail($notification);
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

        $auditors = $users->fetchAllAssoc();

        return array_column($auditors, 'email');
    }

    private function setModelApprovalValues(Model $model, array $options = [])
    {
        $model->huh_approval_notes = $options['notes'] ?? '';
        $model->huh_approval_state = $options['state'] ?? $model->huh_approval_state;
        $model->huh_approval_inform_author = $options['inform_author'] ?? '';
        $model->huh_approval_transition = $options['transition'] ?? '';
        $model->huh_approval_confirm_continue = '';
        $model->huh_approval_auditor = $options['auditor'] ?? $model->huh_approval_auditor;

        if (!empty($options['group'])) {
            foreach ($options['group'] as $group => $state) {
                foreach ($this->bundleConfig[$model->getTable()]['auditor_levels'] as $level) {
                    if (\in_array($group, $level['groups'])) {
                        $model->{'huh_approval_state_'.b($level['name'])} = $state;
                    }
                }
            }
        }

        $model->save();
    }

    private function isEntityApproved(Model $entity): bool
    {
        // check if all auditors approved
        $savedHistory = $this->databaseUtil->findResultsBy(
            'tl_entity_approval_history',
            ['tl_entity_approval_history.pid=?', 'tl_entity_approval_history.ptable=?'],
            [$entity->id, $entity->getTable()]
        )->fetchAllAssoc();

        $adjustedHistory = [];
        $auditorLevels = $this->bundleConfig[$entity->getTable()]['auditor_levels'];

        foreach ($savedHistory as $history) {
            if (null === ($userGroups = $this->userUtil->getActiveGroups($history['author']))) {
                $userGroups = [];
            }

            foreach ($auditorLevels as $auditorLevel) {
                if (array_intersect($auditorLevel['groups'], $userGroups->fetchEach('id'))) {
                    $adjustedHistory[$auditorLevel['name']] = $history['transition'];
                }
            }
        }

        foreach ($auditorLevels as $level) {
            if (EntityApprovalContainer::APPROVAL_STATE_APPROVED !== $entity->{'huh_approval_state_'.b($level['name'])} || EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR !== $adjustedHistory[$level]) {
                return false;
            }
        }

        return true;
    }
}
