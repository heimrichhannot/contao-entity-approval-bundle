<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Manager;

use Contao\Environment;
use HeimrichHannot\EntityApprovementBundle\DataContainer\EntityApprovementContainer;
use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovementBundle\Dto\NotificationCenterOptionsDto;
use NotificationCenter\Model\Notification;

class NotificationManager
{
    protected array $bundleConfig;

    public function __construct(array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
    }

    public function sendNotifications(NotificationCenterOptionsDto $options): void
    {
        switch ($options->state) {
            case EntityApprovementContainer::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR:
                $initialAuditors = explode(',', $this->bundleConfig[$options->table]['initial_auditor_groups']);

                if (empty($initialAuditors)) {
                    break;
                }

                if ($this->bundleConfig[$options->table]['initial_auditor_mode'][Configuration::AUDITOR_MODE_RANDOM]) {
                    //send mails to all initial auditors
                    $options->recipients = array_merge($options->recipients, $initialAuditors);
                } elseif ($this->bundleConfig[$options->table]['initial_auditor_mode'][Configuration::AUDITOR_MODE_ALL]) {
                    //send mails to a random initial auditor
                    $options->recipients = array_merge($options->recipients, [$initialAuditors[array_rand($initialAuditors)]]);
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS:
                $stayedAuditors = array_intersect($options->auditorFormer, $options->auditorNew);

                if ($this->bundleConfig[$options->table]['emails']['auditor_changed_former'] && !empty($options->auditorFormer)) {
                    //send mails to former auditors he is not an auditor anymore
                    $options->recipients = array_merge($options->recipients, array_diff($options->auditorFormer, $stayedAuditors));
                }

                if ($this->bundleConfig[$options->table]['emails']['auditor_changed_new'] && !empty($options->auditorNew)) {
                    //send mails to new auditors who was not auditor before
                    $options->recipients = array_merge($options->recipients, array_diff($options->auditorNew, $stayedAuditors));
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_APPROVED:
            case EntityApprovementContainer::APPROVEMENT_STATE_REJECTED:
                // send mail to author on final result of the entity approvement
                if ($this->bundleConfig[$options->table]['emails']['state_changed_author'] && !empty($options->author)) {
                    $options->recipients = array_merge($options->recipients, [$options->author]);
                }

                break;

            default:
                break;
        }

        $options->recipients = array_unique($options->recipients);

        $this->sendMail($options);
    }

    public function sendMail(NotificationCenterOptionsDto $options): void
    {
        $notificationCollection = Notification::findByType($options->type);

        if (null !== $notificationCollection) {
            $tokens = [];
            $tokens['approvement_recipients'] = $options->recipients;
            $tokens['approvement_entity_url'] = Environment::get('url').'contao?do=submission&table='.$options->table.'&id='.$options->entityId.'&act=edit';

            while ($notificationCollection->next()) {
                $notification = $notificationCollection->current();

                $notification->send($tokens);
            }
        }
    }
}
