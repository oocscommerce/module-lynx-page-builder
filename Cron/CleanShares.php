<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Cron;

use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;

/**
 * Nightly (etc/crontab.xml): share links past their expiry
 */
class CleanShares
{
    /**
     * Share resource
     *
     * @param ShareResource $shareResource
     */
    public function __construct(
        private readonly ShareResource $shareResource,
    ) {
    }

    /**
     * Execute
     *
     * @return void
     */
    public function execute(): void
    {
        $this->shareResource->deleteExpired();
    }
}
