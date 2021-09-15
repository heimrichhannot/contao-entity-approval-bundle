<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle;

use HeimrichHannot\EntityApprovementBundle\DependencyInjection\HeimrichHannotEntityApprovementExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotEntityApprovementBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new HeimrichHannotEntityApprovementExtension();
    }
}
