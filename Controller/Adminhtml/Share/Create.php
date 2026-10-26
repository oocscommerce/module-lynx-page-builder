<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Adminhtml\Share;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use OOCS\LynxPageBuilder\Model\BuilderTarget;
use OOCS\LynxPageBuilder\Model\ShareLink;

/**
 * Makes a share link for the design the editor is open on
 */
class Create extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'OOCS_LynxPageBuilder::share';

    /**
     * Context, json factory, target, share link, timezone
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param BuilderTarget $target
     * @param ShareLink $shareLink
     * @param TimezoneInterface $timezone
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly BuilderTarget $target,
        private readonly ShareLink $shareLink,
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
        $result   = $this->resultJsonFactory->create();
        $password = (string) $this->getRequest()->getParam('password', '');
        $hours    = (int) $this->getRequest()->getParam('hours', 0);

        try {
            $link = $this->shareLink->create(
                $this->target->getDesignId(),
                $password,
                $hours,
                (int) $this->target->getStore()->getId()
            );
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result->setData([
            'success'    => true,
            'id'         => $link['id'],
            'url'        => $link['url'],
            'password'   => $password,
            'expires_at' => $this->dateTime->gmtDate('c', $link['expires']),
            // In the configured timezone and this admin's locale, like Share\Index.
            'expires_label' => $this->timezone->formatDateTime(
                $this->timezone->date($link['expires']),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT
            ),
        ]);
    }
}
