<?php
defined('ABSPATH') || exit;

/** Read dependency state without instantiating vendor services or reading credentials. */
function sanro_studio_dependencies() {
    $version = defined('LUMISE') && is_string(LUMISE) ? LUMISE : '';
    $lumise = class_exists('LumiseWoo', false) && function_exists('LW');
    $woo = class_exists('WooCommerce', false);
    return [
        'woocommerce' => $woo,
        'lumise' => $lumise,
        'lumise_version' => $version,
        // Only this version has been inspected. Unknown versions remain unchanged.
        'supported' => $woo && $lumise && $version === '2.1.2',
    ];
}

function sanro_studio_options() {
    $stored = get_option('sanro_studio_options', []);
    return ['admin_preview' => is_array($stored) && ($stored['admin_preview'] ?? '') === 'yes'];
}

function sanro_studio_preview_enabled() {
    $dependencies = sanro_studio_dependencies();
    return $dependencies['supported']
        && !is_admin()
        && !(defined('LUMISE_ADMIN') && LUMISE_ADMIN)
        && current_user_can('manage_options')
        && sanro_studio_options()['admin_preview'];
}

/** Runtime-only UI profile; does not enforce upload/object/checkout business rules. */
function sanro_studio_filter_settings($settings) {
    if (!is_array($settings) || !sanro_studio_preview_enabled()) {
        return $settings;
    }
    $allowed = [
        'components' => ['shop', 'product', 'text', 'uploads', 'back'],
        'toolbars' => ['replace-image', 'position', 'transform', 'select-font', 'font-size',
            'line-height', 'letter-spacing', 'text-align', 'font-style'],
    ];
    foreach ($allowed as $key => $values) {
        if (isset($settings[$key]) && is_array($settings[$key])) {
            // Preserve the existing order and never enable a component disabled by the owner.
            $settings[$key] = array_values(array_filter($settings[$key], function ($value) use ($values) {
                return is_string($value) && in_array($value, $values, true);
            }));
        }
    }
    return $settings;
}
