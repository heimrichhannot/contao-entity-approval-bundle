<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\DataContainer;

use Contao\BackendUser;
use Contao\DataContainer;
use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;
use HeimrichHannot\EntityApprovalBundle\Util\AuditorUtil;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

class EntityApprovalContainer
{
    const APPROVAL_STATE_CREATED = 'created';
    const APPROVAL_STATE_IN_AUDIT = 'in_audit';
    const APPROVAL_STATE_APPROVED = 'approved';
    const APPROVAL_STATE_REJECTED = 'rejected';

    const APPROVAL_STATES = [
        self::APPROVAL_STATE_CREATED,
        self::APPROVAL_STATE_IN_AUDIT,
        self::APPROVAL_STATE_APPROVED,
        self::APPROVAL_STATE_REJECTED,
    ];

    const APPROVAL_ENTITY_STATES = [
        self::APPROVAL_STATE_IN_AUDIT,
        self::APPROVAL_STATE_APPROVED,
        self::APPROVAL_STATE_REJECTED,
    ];

    const APPROVAL_TRANSITION_SUBMIT = 'submit';
    const APPROVAL_TRANSITION_APPROVE = 'approve';
    const APPROVAL_TRANSITION_REJECT = 'reject';
    const APPROVAL_TRANSITION_REQUEST_CHANGE = 'request_change';
    const APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR = 'assign_new_auditor';

    const APPROVAL_TRANSITIONS = [
        self::APPROVAL_TRANSITION_SUBMIT,
        self::APPROVAL_TRANSITION_ASSIGN_NEW_AUDITOR,
        self::APPROVAL_TRANSITION_APPROVE,
        self::APPROVAL_TRANSITION_REJECT,
        self::APPROVAL_TRANSITION_REQUEST_CHANGE,
    ];

    protected DatabaseUtil        $databaseUtil;
    protected ModelUtil           $modelUtil;
    protected NotificationManager $notificationManager;
    protected TranslatorInterface $translator;
    protected UserUtil            $userUtil;
    protected WorkflowInterface   $entityApprovalStateMachine;
    protected array               $bundleConfig;
    protected DcaUtil             $dcaUtil;
    protected AuditorUtil         $auditorUtil;

    public function __construct(
        AuditorUtil $auditorUtil,
        DatabaseUtil $databaseUtil,
        DcaUtil $dcaUtil,
        ModelUtil $modelUtil,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        UserUtil $userUtil,
        WorkflowInterface $entityApprovalStateMachine,
        array $bundleConfig
    ) {
        $this->databaseUtil = $databaseUtil;
        $this->modelUtil = $modelUtil;
        $this->notificationManager = $notificationManager;
        $this->translator = $translator;
        $this->userUtil = $userUtil;
        $this->entityApprovalStateMachine = $entityApprovalStateMachine;
        $this->bundleConfig = $bundleConfig;
        $this->dcaUtil = $dcaUtil;
        $this->auditorUtil = $auditorUtil;
    }

    public function onSubmit(?DataContainer $dc): void
    {
        /** @var Model $model */
        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
        $activeRecord = $dc->activeRecord;
        $backendUser = BackendUser::getInstance();

        $transition = $activeRecord->row()['huh_approval_transition'];

        $modelAuditor = StringUtil::deserialize($model->huh_approval_auditor, true);

        if ($model->huh_approval_state === static::APPROVAL_STATE_CREATED) {
            if (empty($transition)) {
                $transition = static::APPROVAL_TRANSITION_SUBMIT;
            }
        } elseif (
            (!\in_array((string) $backendUser->id, $modelAuditor) || $model->huh_approval_state === static::APPROVAL_STATE_APPROVED)
            && !$backendUser->isAdmin
        ) {
            $message = $this->translator->trans('huh.entity_approval.blocking.modification_not_allowed');

            throw new \Exception($message);
        }

        if ($this->entityApprovalStateMachine->can($model, $transition)) {
            $this->applyApprovalModelChanges($model, $activeRecord);
            $this->entityApprovalStateMachine->apply($model, $transition);
        } else {
            $this->createTransitionException($model, $activeRecord->row());
        }
    }

    public function onLoadApprovalTransition($value, DataContainer $dc)
    {
        return '';
    }

    public function getAvailableTransitions(?DataContainer $dc): array
    {
        $this->dcaUtil->loadLanguageFile('default');
        $options = [];

        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);

        foreach ($this->entityApprovalStateMachine->getEnabledTransitions($model) as $transition) {
            $options[$transition->getName()] = $GLOBALS['TL_LANG']['MSC']['reference'][$transition->getName()];
        }

        return $options;
    }

    public function getAuditors(?DataContainer $dc): array
    {
        $options = [];
        $initialKey = array_search('initial', array_column($this->bundleConfig[$dc->table]['auditor_levels'], 'name'));

        $groups = [];

        foreach ($this->bundleConfig[$dc->table]['auditor_levels'] as $key => $level) {
            if ($key === $initialKey) {
                continue;
            }

            $groups = array_merge($groups, $level['groups']);
        }

        if (empty($groups)) {
            $groups = $this->bundleConfig[$dc->table]['auditor_levels'][$initialKey]['groups'];
        }

        if (null !== ($users = $this->userUtil->findActiveByGroups($groups))) {
            foreach ($users->fetchAll() as $user) {
                $options[$user['id']] = $user['name'].' - '.$user['email'];
            }
        }

        return $options;
    }

    public function onPublish($value, DataContainer $dc)
    {
        //Admin still can publish even without workflow
        if ($this->userUtil->isAdmin() || ($this->bundleConfig[$dc->table]['invert_publish_field'] && '1' === $value) || (!$this->bundleConfig[$dc->table]['invert_publish_field'] && '0' === $value)) {
            return $value;
        }

        $activeRecord = $dc->activeRecord->row();

        $state = $activeRecord['huh_approval_state'];

        if ($value === ($this->bundleConfig[$dc->table]['invert_publish_field'] ? '0' : '1') && $state !== static::APPROVAL_STATE_APPROVED) {
            $unpublishValue = $this->bundleConfig[$dc->table]['invert_publish_field'] ? '1' : '0';

            $this->databaseUtil->update(
                $dc->table,
                [$dc->table.'.'.$this->bundleConfig[$dc->table]['publish_field'] => $unpublishValue],
                $dc->table.'.id=?',
                [$dc->id]);

            $message = sprintf(
                $this->translator->trans('huh.entity_approval.blocking.publishing_blocked'),
                $GLOBALS['TL_LANG']['MSC']['approval_state'][static::APPROVAL_STATE_APPROVED],
                $GLOBALS['TL_LANG']['MSC']['approval_state'][$state]
            );

            throw new \Exception($message);
        }

        return $value;
    }

    public function onAuditorOptionsCallback(DataContainer $dc): array
    {
        return $this->auditorUtil->getEntityAuditorsByTable($dc->id, $dc->table);
    }

    private function applyApprovalModelChanges(Model $model, $activeRecord): void
    {
        $row = $model->row();

        if ('' === $model->huh_approval_state) {
            $row['huh_approval_state'] = static::APPROVAL_STATE_CREATED;
        }

        if ($model->huh_approval_state === static::APPROVAL_STATE_IN_AUDIT) {
            $this->checkForLogicException((bool) $activeRecord->huh_approval_confirm_continue);

            $row['huh_approval_notes'] = $activeRecord->{'huh_approval_notes'};
            $row['huh_approval_auditor'] = $activeRecord->{'huh_approval_auditor'};
        }

        $model->setRow($row);
        $model->save();
    }

    private function createTransitionException(Model $model, array $activeRecord): void
    {
        $message = sprintf(
            $this->translator->trans('huh.entity_approval.blocking.transition_not_allowed'),
            $activeRecord['huh_approval_transition'],
        );

        throw new TransitionException($model, $activeRecord['huh_approval_transition'], $this->entityApprovalStateMachine, $message);
    }

    private function checkForLogicException(bool $accept = false): void
    {
        $backendUser = BackendUser::getInstance();

        if (!$accept && !$backendUser->isAdmin) {
            $message = $this->translator->trans('huh.entity_approval.blocking.transition_not_accepted');

            throw new LogicException($message, 0);
        }
    }
}
