<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Helper;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * The token the admin puts on the preview URL it hands to its iframe
 */
class PreviewToken
{
    /**
     * How long an editor's token lasts: two hours, after which the storefront leaves preview mode.
     */
    public const LIFETIME = 7200;

    /**
     * Encryptor
     *
     * @param EncryptorInterface $encryptor
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Generate
     *
     * @param int $designId
     * @param int $shareId the share link this token is for, 0 for the editor's own
     * @param int|null $expiry when it stops working, as a timestamp
     * @return string
     */
    public function generate(int $designId, int $shareId = 0, ?int $expiry = null): string
    {
        $expiry ??= $this->dateTime->gmtTimestamp() + self::LIFETIME;

        return $this->encryptor->encrypt($expiry . ':' . $designId . ':' . $shareId);
    }

    /**
     * What a token is good for
     *
     * @param string $token
     * @return array{design: int, share: int, expiry: int}|null
     */
    public function read(string $token): ?array
    {
        if (!$token) {
            return null;
        }

        [$expiry, $designId, $shareId] = array_pad(explode(':', $this->encryptor->decrypt($token), 3), 3, '0');

        if (!$this->isDigits($expiry) || (int) $expiry < $this->dateTime->gmtTimestamp()
            || !$this->isDigits($designId) || !$this->isDigits($shareId)
        ) {
            return null;
        }

        return ['design' => (int) $designId, 'share' => (int) $shareId, 'expiry' => (int) $expiry];
    }

    /**
     * Whether the value is a non-empty run of ASCII digits
     *
     * @param string $value
     * @return bool
     */
    private function isDigits(string $value): bool
    {
        return preg_match('/\A\d+\z/', $value) === 1;
    }
}
