<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Test\Unit\Helper;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use OOCS\LynxPageBuilder\Helper\PreviewToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Preview tokens: what is written reads back, expired or tampered ones read as nothing
 */
class PreviewTokenTest extends TestCase
{
    private const NOW = 1800000000;

    /**
     * @var EncryptorInterface&MockObject
     */
    private EncryptorInterface $encryptor;

    /**
     * @var PreviewToken
     */
    private PreviewToken $token;

    /**
     * An encryptor that only marks the text, and a clock fixed at NOW
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->encryptor->method('encrypt')->willReturnCallback(fn ($text) => 'enc:' . $text);
        $this->encryptor->method('decrypt')->willReturnCallback(
            fn ($text) => str_starts_with($text, 'enc:') ? substr($text, 4) : ''
        );
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);

        $this->token = new PreviewToken($this->encryptor, $dateTime);
    }

    /**
     * A generated token reads back as its design, share and expiry
     *
     * @return void
     */
    public function testRoundTrip(): void
    {
        $read = $this->token->read($this->token->generate(5, 9, self::NOW + 60));

        $this->assertSame(['design' => 5, 'share' => 9, 'expiry' => self::NOW + 60], $read);
    }

    /**
     * Without an expiry the token lasts LIFETIME seconds
     *
     * @return void
     */
    public function testDefaultLifetime(): void
    {
        $read = $this->token->read($this->token->generate(5));

        $this->assertSame(self::NOW + PreviewToken::LIFETIME, $read['expiry']);
        $this->assertSame(0, $read['share']);
    }

    /**
     * An expired token reads as nothing
     *
     * @return void
     */
    public function testExpired(): void
    {
        $this->assertNull($this->token->read($this->token->generate(5, 0, self::NOW - 1)));
    }

    /**
     * An empty token reads as nothing
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $this->assertNull($this->token->read(''));
    }

    /**
     * A token that does not decrypt, or decrypts to something else, reads as nothing
     *
     * @return void
     */
    public function testTampered(): void
    {
        $this->assertNull($this->token->read('garbage'));
        $this->assertNull($this->token->read('enc:' . (self::NOW + 60) . ':5x:0'));
        $this->assertNull($this->token->read('enc:' . (self::NOW + 60) . ':-5:0'));
        $this->assertNull($this->token->read('enc:soon:5:0'));
    }

    /**
     * A token without a share part reads as share 0
     *
     * @return void
     */
    public function testWithoutShare(): void
    {
        $this->assertSame(0, $this->token->read('enc:' . (self::NOW + 60) . ':5')['share']);
    }
}
