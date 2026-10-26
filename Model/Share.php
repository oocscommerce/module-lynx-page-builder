<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * A share link: one row of lynx_pagebuilder_share (see etc/db_schema.xml)
 */
class Share extends AbstractModel
{
    /** How many wrong passwords in a row lock a link. */
    public const MAX_ATTEMPTS = 5;

    /** For how long, in seconds. */
    public const LOCK_SECONDS = 900;

    /**
     * The hours a link may be created for; the editor offers exactly these
     */
    public const HOURS = [3, 24, 72, 168];

    /**
     * Construct
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Share::class);
    }
}
