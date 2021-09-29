<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle;

use HeimrichHannot\EntityApprovalBundle\DependencyInjection\HeimrichHannotEntityApprovalExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotEntityApprovalBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new HeimrichHannotEntityApprovalExtension();
    }
}
