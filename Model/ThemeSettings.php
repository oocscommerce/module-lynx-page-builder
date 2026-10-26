<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use OOCS\LynxComponent\Model\Cache\Type\ComponentCache;
use OOCS\LynxPageBuilder\Api\DesignRepositoryInterface;

/**
 * The theme settings a design carries
 */
class ThemeSettings
{
    /**
     * CSS per design id, for the request; the cache below holds it across requests
     *
     * @var array<int, string>
     */
    private array $css = [];

    /**
     * Design repository, cache, fields
     *
     * @param DesignRepositoryInterface $designRepository
     * @param ComponentCache $cache
     * @param array $fields
     */
    public function __construct(
        private readonly DesignRepositoryInterface $designRepository,
        private readonly ComponentCache $cache,
        private readonly array $fields = [],
    ) {
    }

    /**
     * The stylesheet of a design, by id
     *
     * @param int $designId
     * @return string
     */
    public function getCss(int $designId): string
    {
        if (!isset($this->css[$designId])) {
            $key = 'lynx_theme_' . $designId;
            $css = $this->cache->load($key);
            if ($css === false) {
                try {
                    $settings = $this->designRepository->getById($designId)->getSettings();
                } catch (NoSuchEntityException) {
                    $settings = [];
                }
                $css = $this->toCss($settings);
                $this->cache->save($css, $key, [Design::CACHE_TAG]);
            }
            $this->css[$designId] = (string) $css;
        }

        return $this->css[$designId];
    }

    /**
     * The field definitions the editor builds its Theme tab from, in pool order
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get Defaults
     *
     * @return array<string, mixed> key => default value
     */
    public function getDefaults(): array
    {
        $defaults = [];
        foreach ($this->fields as $key => $field) {
            $defaults[$key] = $field['default'] ?? '';
        }

        return $defaults;
    }

    /**
     * A full set of values: what was saved, over the defaults
     *
     * @param array $settings
     * @return array<string, mixed>
     */
    public function normalize(array $settings): array
    {
        $out = [];
        foreach ($this->fields as $key => $field) {
            $value = $settings[$key] ?? $field['default'] ?? '';
            if (($field['type'] ?? '') === 'number') {
                $value = (int) $value;
                if (isset($field['min'])) {
                    $value = max((int) $field['min'], $value);
                }
                if (isset($field['max'])) {
                    $value = min((int) $field['max'], $value);
                }
            } elseif (($field['type'] ?? '') === 'select') {
                $allowed = array_column($field['options'] ?? [], 'value');
                if (!\in_array($value, $allowed, true)) {
                    $value = $field['default'] ?? '';
                }
            } else {
                $value = trim((string) $value);
                // An empty color or text means "not set" and takes the default
                if ($value === '' && empty($field['raw'])) {
                    $value = $field['default'] ?? '';
                }
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * The stylesheet a page carries for its design: the CSS custom properties the theme's components read, on :root
     *
     * @param array $settings
     * @return string
     */
    public function toCss(array $settings): string
    {
        $values = $this->normalize($settings);
        $root = [];
        $html = [];
        $raw = [];

        foreach ($this->fields as $key => $field) {
            $value = $values[$key];
            // Raw CSS (the Custom CSS field): appended as written, after the variables
            if (!empty($field['raw'])) {
                if ($value !== '') {
                    $raw[] = str_replace('</', '<\/', (string) $value);
                }
                continue;
            }
            // A select may map its option to the CSS value it stands for (a font stack).
            if (isset($field['values'][$value])) {
                $value = $field['values'][$value];
            }
            if (isset($field['unit'])) {
                $value .= $field['unit'];
            }
            if (!empty($field['var'])) {
                $root[] = $field['var'] . ':' . $value;
            }
            if (!empty($field['html'])) {
                $html[] = $field['html'] . ':' . $value;
            }
        }

        $css = ':root{' . implode(';', $root) . '}';
        if ($html) {
            $css .= 'html{' . implode(';', $html) . '}';
        }
        if ($raw) {
            $css .= "\n" . implode("\n", $raw);
        }

        return $css;
    }
}
