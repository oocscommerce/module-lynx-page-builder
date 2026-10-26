<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxPageBuilder\Helper;

use Magento\Framework\Exception\LocalizedException;
use OOCS\LynxComponent\Model\Registry;

/**
 * Turns nodes an API caller sends ({name, props, slots}) into stored nodes, refusing what the editor would not allow
 */
class LayoutNormalizer
{
    /**
     * Registry
     *
     * @param Registry $registry
     */
    public function __construct(
        private readonly Registry $registry,
    ) {
    }

    /**
     * Fill component, label and visible from the registry and check zone, page, settings, slots and slot groups
     *
     * @param array $nodes
     * @param string $zone header, content, footer or backstage
     * @param string $pagePath the page for available_on; empty for a shared zone
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    public function normalize(array $nodes, string $zone, string $pagePath): array
    {
        $components = [];
        foreach ($this->registry->getComponents($pagePath) as $component) {
            $components[$component['name']] = $component;
        }

        return $this->walk($nodes, $zone, $components, $zone);
    }

    /**
     * One list of nodes, recursing into slots
     *
     * @param array $nodes
     * @param string $zone
     * @param array<string,array> $components
     * @param string $where names the list in messages: the zone, or component.slot
     * @param string[] $allowedGroups the slot's allowed_groups, when it declares them
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    private function walk(
        array $nodes,
        string $zone,
        array $components,
        string $where,
        array $allowedGroups = []
    ): array {
        $out = [];

        foreach (array_values($nodes) as $i => $node) {
            $name = \is_array($node) ? (string) ($node['name'] ?? '') : '';
            if ($name === '') {
                throw new LocalizedException(__('Node %1 in %2 names no component.', $i, $where));
            }
            $component = $components[$name] ?? null;
            if ($component === null) {
                throw new LocalizedException(__('No component is registered as "%1" for this page.', $name));
            }

            $this->checkPlacement($name, $component, $zone, $where, $allowedGroups);
            $this->checkKeys($name, $node, $component);

            $stored = $this->toStored($name, $node, $component);
            $declared = (array) ($component['slots'] ?? []);
            foreach ((array) ($node['slots'] ?? []) as $slot => $children) {
                $stored['slots'][$slot] = $this->walk(
                    (array) $children,
                    $zone,
                    $components,
                    $name . '.' . $slot,
                    array_values((array) ($declared[$slot]['allowed_groups'] ?? []))
                );
            }
            $out[] = $stored;
        }

        return $out;
    }

    /**
     * Refuse a component the zone or the slot's allowed groups do not take
     *
     * @param string $name
     * @param array $component
     * @param string $zone
     * @param string $where
     * @param string[] $allowedGroups
     * @return void
     * @throws LocalizedException
     */
    private function checkPlacement(
        string $name,
        array $component,
        string $zone,
        string $where,
        array $allowedGroups
    ): void {
        $zones = array_values((array) ($component['zones'] ?? []));
        // Backstage takes only components that name it; elsewhere no zones means anywhere
        $fits = $zone === 'backstage'
            ? \in_array('backstage', $zones, true)
            : ($zones === [] || \in_array($zone, $zones, true));
        if (!$fits) {
            throw new LocalizedException(__('"%1" cannot go in the %2 zone.', $name, $zone));
        }
        if ($allowedGroups !== [] && !\in_array($component['group'], $allowedGroups, true)) {
            throw new LocalizedException(__(
                '%1 takes only group %2; "%3" is %4.',
                $where,
                implode(', ', $allowedGroups),
                $name,
                $component['group']
            ));
        }
    }

    /**
     * Refuse props and slots the component does not declare
     *
     * @param string $name
     * @param array $node
     * @param array $component
     * @return void
     * @throws LocalizedException
     */
    private function checkKeys(string $name, array $node, array $component): void
    {
        $settings = array_keys($component['settings'] ?? []);
        $unknown  = array_diff(array_keys((array) ($node['props'] ?? [])), $settings);
        if ($unknown !== []) {
            throw new LocalizedException(__(
                '"%1" has no setting %2. Its settings: %3.',
                $name,
                implode(', ', $unknown),
                implode(', ', $settings) ?: 'none'
            ));
        }

        $declared = array_keys((array) ($component['slots'] ?? []));
        $unknown  = array_diff(array_keys((array) ($node['slots'] ?? [])), $declared);
        if ($unknown !== []) {
            throw new LocalizedException(__(
                '"%1" has no slot %2. Its slots: %3.',
                $name,
                implode(', ', $unknown),
                implode(', ', $declared) ?: 'none'
            ));
        }
    }

    /**
     * The stored node without its slots: component, label and visible filled from the registry
     *
     * @param string $name
     * @param array $node
     * @param array $component
     * @return array<string, mixed>
     */
    private function toStored(string $name, array $node, array $component): array
    {
        $stored = [
            'component' => $component['key'],
            'name'      => $name,
            'label'     => (string) ($node['label'] ?? $component['label'] ?? $name),
            'visible'   => \array_key_exists('visible', $node) ? (bool) $node['visible'] : true,
            'props'     => (array) ($node['props'] ?? []),
        ];
        // The name an admin typed over the label in the editor's outline
        if (isset($node['custom_name']) && (string) $node['custom_name'] !== '') {
            $stored['custom_name'] = (string) $node['custom_name'];
        }

        return $stored;
    }
}
