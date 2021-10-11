<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Manager;

use Contao\Environment;
use HeimrichHannot\EntityApprovalBundle\Dto\NotificationCenterOptionsDto;
use HeimrichHannot\UtilsBundle\Driver\DC_Table_Utils;
use HeimrichHannot\UtilsBundle\Form\FormUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\Salutation\SalutationUtil;

class NotificationManager
{
    const NOTIFICATION_TYPE_AUDITOR_CHANGED = 'huh_entity_approval_auditor_changed';
    const NOTIFICATION_TYPE_STATE_CHANGED = 'huh_entity_approval_state_changed';

    const NOTIFICATION_TYPES = [
        self::NOTIFICATION_TYPE_AUDITOR_CHANGED,
        self::NOTIFICATION_TYPE_STATE_CHANGED,
    ];

    protected array          $bundleConfig;
    protected ModelUtil      $modelUtil;
    protected FormUtil       $formUtil;
    protected SalutationUtil $salutationUtil;

    public function __construct(FormUtil $formUtil, ModelUtil $modelUtil, SalutationUtil $salutationUtil, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->modelUtil = $modelUtil;
        $this->formUtil = $formUtil;
        $this->salutationUtil = $salutationUtil;
    }

    public function sendMail(NotificationCenterOptionsDto $options): void
    {
        if (null !== ($notificationCollection = $this->modelUtil->findModelInstancesBy('tl_nc_notification', ['tl_nc_notification.type=?'], [$options->type]))) {
            while ($notificationCollection->next()) {
                $notification = $notificationCollection->current();
                $tokens = $this->generateTokens($options);
                $tokens['recipient_email'] = implode(',', $options->recipients);

                $notification->send($tokens);
            }
        }
    }

    private function generateTokens(NotificationCenterOptionsDto $options): array
    {
        $tokens = [];
        $tokens['entity_url'] = Environment::get('url').'contao?do=submission&table='.$options->table.'&id='.$options->entityId.'&act=edit';

        if (null !== ($entity = $this->modelUtil->findModelInstanceByPk($options->table, $options->entityId))) {
            $dc = new DC_Table_Utils($options->table);
            $dc->activeRecord = $entity;
            $dc->id = $entity->id;

            $tokens = array_merge($this->formUtil->getModelDataAsNotificationTokens($entity->row(), 'approval_entity_', $dc, []), $tokens);
        }

        if (null !== ($auditor = $this->modelUtil->findModelInstanceByPk('tl_user', $options->auditor))) {
            $dc = new DC_Table_Utils('tl_user');
            $dc->activeRecord = $auditor;
            $dc->id = $auditor->id;

            $tokens = array_merge($this->formUtil->getModelDataAsNotificationTokens($auditor->row(), 'approval_auditor_', $dc, []), $tokens);
            $tokens['salutation_auditor'] = $this->salutationUtil->createSalutation($GLOBALS['TL_LANGUAGE'], $auditor->row());
        }

        return $tokens;
    }
}
