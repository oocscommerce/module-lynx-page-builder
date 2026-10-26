<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Share;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use OOCS\LynxPageBuilder\Model\BuilderTarget;
use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;

/**
 * The share links of the design the editor is open on that still work
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::share';

    /**
     * Context, result json factory, target, share resource, timezone
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param BuilderTarget $target
     * @param ShareResource $shareResource
     * @param TimezoneInterface $timezone
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly BuilderTarget $target,
        private readonly ShareResource $shareResource,
        private readonly TimezoneInterface $timezone,
        private readonly DateTime $dateTime,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Json
     */
    public function execute(): Json
    {
        $shares = [];
        foreach ($this->shareResource->getAliveByDesign($this->target->getDesignId()) as $row) {
            $expires = (int) $this->dateTime->gmtTimestamp($row['expires_at']);
            $shares[] = [
                'id'            => (int) $row['id'],
                // ISO 8601 in UTC for arithmetic (the span a link was made for)
                'expires_at'    => $this->dateTime->gmtDate('c', $expires),
                'created_at'    => $this->dateTime->gmtDate('c', $row['created_at']),
                'expires_label' => $this->timezone->formatDateTime(
                    $this->timezone->date($expires),
                    \IntlDateFormatter::MEDIUM,
                    \IntlDateFormatter::SHORT
                ),
            ];
        }

        return $this->resultJsonFactory->create()->setData(['shares' => $shares]);
    }
}
