<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Controller\Share;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use OOCS\LynxPageBuilder\Helper\PreviewToken;
use OOCS\LynxPageBuilder\Model\PreviewMode;
use OOCS\LynxPageBuilder\Model\ResourceModel\Share as ShareResource;

/**
 * The password form's POST
 */
class Unlock implements HttpPostActionInterface
{
    /**
     * Request, redirect factory, store manager, encryptor, share resource, preview token, preview
     *
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param ShareResource $shareResource
     * @param PreviewToken $previewToken
     * @param PreviewMode $preview
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly EncryptorInterface $encryptor,
        private readonly ShareResource $shareResource,
        private readonly PreviewToken $previewToken,
        private readonly PreviewMode $preview,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Execute
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $key      = (string) $this->request->getParam('key', '');
        $password = (string) $this->request->getParam('password', '');
        $share    = $key ? $this->shareResource->findByToken($key) : null;
        $redirect = $this->redirectFactory->create();
        // Back to the form with a generic failure flag
        $back = $redirect->setPath('lynx/share/open', ['key' => $key, 'err' => 1]);

        $now = $this->dateTime->gmtTimestamp();
        if ($share === null || $this->dateTime->gmtTimestamp($share['expires_at']) < $now) {
            return $back;
        }

        if ($share['locked_until'] !== null && $this->dateTime->gmtTimestamp($share['locked_until']) > $now) {
            return $redirect->setPath('lynx/share/open', ['key' => $key]);
        }

        if ($password === '' || !$this->encryptor->isValidHash($password, (string) $share['password_hash'])) {
            $this->shareResource->recordFailure((int) $share['id'], (int) $share['attempts']);
            return $back;
        }

        $this->shareResource->clearFailures((int) $share['id']);

        // Good for as long as the link is, and no longer: a link made for three hours must not leave a browser
        $this->preview->start($this->previewToken->generate(
            (int) $share['design_id'],
            (int) $share['id'],
            (int) $this->dateTime->gmtTimestamp($share['expires_at'])
        ));

        return $redirect->setUrl($this->storeManager->getStore()->getBaseUrl());
    }
}
