<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use HeimrichHannot\EntityApprovalBundle\HeimrichHannotEntityApprovalBundle;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * Class Plugin.
 */
class Plugin implements BundlePluginInterface, ConfigPluginInterface
{
    /**
     *  {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(HeimrichHannotEntityApprovalBundle::class)->setLoadAfter([
                    'submissions',
                    ContaoCoreBundle::class,
            ]),
        ];
    }

    /**
     * Allows a plugin to load container configuration.
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig)
    {
        $loader->load('@HeimrichHannotEntityApprovalBundle/Resources/config/services.yml');
        $loader->load('@HeimrichHannotEntityApprovalBundle/Resources/config/workflow.yml');
    }
}
