<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * The draft layout a preview request renders with
 */
class PreviewDraft
{
    private const EMPTY_ZONES = ['header' => [], 'content' => [], 'footer' => [], 'backstage' => []];

    /**
     * Null until a preview request captures a draft, which is also what tells a preview request apart from any other
     *
     * @var array<string, array>|null
     */
    private ?array $zones = null;

    /**
     * The theme settings the editor holds, or null when the draft carried none (an older editor
     *
     * @var array<string, mixed>|null
     */
    private ?array $theme = null;

    /**
     * Whether the editor has actually posted a layout
     *
     * @var bool
     */
    private bool $posted = false;

    /**
     * The preview shows the saved layouts rather than a draft (Router::PARAM_PREVIEW)
     *
     * @var bool
     */
    private bool $saved = false;

    /**
     * Serializer
     *
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Capture
     *
     * @param RequestInterface $request
     * @return void
     */
    public function capture(RequestInterface $request): void
    {
        $layout = $this->readParam($request, 'layout');
        $this->posted = $layout !== '';
        $this->zones = $this->decode($layout);
        $this->theme = $this->decodeArray($this->readParam($request, 'theme'));
    }

    /**
     * Show the saved layouts: no draft, and an entity's own layout even where it is not switched on
     *
     * @return void
     */
    public function markSaved(): void
    {
        $this->saved = true;
    }

    /**
     * Whether the preview shows the saved layouts
     *
     * @return bool
     */
    public function isSaved(): bool
    {
        return $this->saved;
    }

    /**
     * Get Theme
     *
     * @return array<string, mixed>|null
     */
    public function getTheme(): ?array
    {
        return $this->theme;
    }

    /**
     * Has Draft
     *
     * @return bool
     */
    public function hasDraft(): bool
    {
        return $this->posted;
    }

    /**
     * Is Preview
     *
     * @return bool
     */
    public function isPreview(): bool
    {
        return $this->zones !== null;
    }

    /**
     * Get Zone
     *
     * @param string $zone
     * @return array
     */
    public function getZone(string $zone): array
    {
        return $this->zones[$zone] ?? [];
    }

    /**
     * Read Param
     *
     * @param RequestInterface $request
     * @param string $name
     * @return string
     */
    private function readParam(RequestInterface $request, string $name): string
    {
        $value = (string) $request->getParam($name, '');
        if ($value) {
            return $value;
        }

        // Inertia posts a JSON body rather than form fields, so $_POST is empty and getParam() finds nothing
        $body = $this->decodeArray((string) $request->getContent());

        return $body !== null ? (string) ($body[$name] ?? '') : '';
    }

    /**
     * Decode
     *
     * @param string $layout
     * @return array
     */
    private function decode(string $layout): array
    {
        // The first GET of a preview URL carries no draft
        if (!$layout) {
            return self::EMPTY_ZONES;
        }

        $decoded = $this->decodeArray($layout);

        return $decoded !== null ? $decoded + self::EMPTY_ZONES : self::EMPTY_ZONES;
    }

    /**
     * The request's JSON as an array, or null: what the editor posts is trusted to be JSON no more than any other
     *
     * @param string $json
     * @return array|null
     */
    private function decodeArray(string $json): ?array
    {
        if ($json === '') {
            return null;
        }
        try {
            $decoded = $this->serializer->unserialize($json);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }
}
