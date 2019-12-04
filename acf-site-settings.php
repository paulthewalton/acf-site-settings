<?php

namespace ACF_Site_Settings;

/**
 * ACF Site Settings
 *
 * @package ACF_Site_Settings
 * @author Paul Walton <pwalton@live.ca>
 * @license GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: ACF Site Settings
 * Description: Get and set WordPress site settings (options & theme_mods) with Advanced Custom Fields.
 * Version: 1.0.0
 * Requires at least: 4.7
 * Requires PHP: 5.4
 * Author: Paul Walton
 * License: GPL 3.0 or later
 */
defined('ABSPATH') || exit;

function str_starts_with($haystack, $needle)
{
    return (substr($haystack, 0, strlen($needle)) === $needle);
}

if (!class_exists('ACF_Site_Settings\ACF_Site_Settings')) {
    class ACF_Site_Settings
    {
        const PREFIX = "acf_site_settings";
        const THEME_MOD_PREFIX = 'wp_theme_mod_';
        const OPTION_PREFIX = 'wp_option_';

        private $did_init = false;
        private static $instance = null;

        /**
         * Hidden constructor
         *
         * @since 1.0.0
         */
        private function __construct()
        { }

        /**
         * Get singleton instance
         *
         * @since 1.0.0
         * @return ACF_Site_Settings
         */
        public static function getInstance()
        {
            if (self::$instance == null) {
                self::$instance = new ACF_Site_Settings();
            }
            return self::$instance;
        }

        /**
         * Initialize
         *
         * @since 1.0.0
         */
        public function init()
        {
            if (!did_action('plugins_loaded') || $this->did_init) {
                return;
            }
            // This hook allows us to modify the value of a field before it is saved to the database.
            add_filter('acf/update_value', [$this, "filter_acf_update"], 5, 3);
            //This hook allows us to modify the value of a field right after it is loaded from the database.
            add_filter('acf/load_value', [$this, "filter_acf_load"], 5, 3);

            $this->did_init = true;
        }

        /**
         * Preprocess ACF field values before they are saved, and update options.
         *
         * @since 1.0.0
         *
         * @param mixed $value - The value to be stored by ACF.
         * @param int $post_id - The post with which this data is associated.
         * @param array $field - The field data.
         * @return mixed
         */
        public function filter_acf_update($value, $post_id, $field)
        {
            // Preserve original value for ACF
            $acf_value = $value;
            // Preprocess certain field type values
            switch ($field['type']) {
                case 'image':
                    $value = wp_get_attachment_url($value);
                    break;
            }
            $value = apply_filters(
                self::PREFIX . "/preprocess_${$field['type']}_field_update",
                $value,
                $acf_value,
                $post_id,
                $field
            );
            // Set theme mods/options
            if (str_starts_with($field['name'], self::THEME_MOD_PREFIX)) {
                set_theme_mod(
                    substr($field['name'], strlen(self::THEME_MOD_PREFIX)),
                    $value
                );
            }
            if (str_starts_with($field['name'], self::OPTION_PREFIX)) {
                update_option(
                    substr($field['name'], strlen(self::OPTION_PREFIX)),
                    $value
                );
            }
            // Pass along initial value for ACF
            return $acf_value;
        }

        /**
         * Preprocess ACF field values as they are loaded, replacing with site setting value.
         *
         * @since 1.0.0
         *
         * @param mixed $value - The value stored by ACF.
         * @param int $post_id - The post with which this data is associated.
         * @param array $field - The field data.
         * @return mixed
         */
        public function filter_acf_load($value, $post_id, $field)
        {
            // Preserve original value from ACF
            $acf_value = $value;
            // Load from site settings, falling back to value returned by ACF
            if (str_starts_with($field['name'], self::THEME_MOD_PREFIX)) {
                $value = get_theme_mod(
                    substr($field['name'], strlen(self::THEME_MOD_PREFIX)),
                    $acf_value
                );
            } else if (str_starts_with($field['name'], self::OPTION_PREFIX)) {
                $value = get_option(
                    substr($field['name'], strlen(self::OPTION_PREFIX)),
                    $acf_value
                );
            }
            if ($value !== $acf_value) {
                // Prepare certain field types for rendering by ACF
                switch ($field['type']) {
                    case 'image':
                        $value = attachment_url_to_postid($value);
                        break;
                }
            }
            return apply_filters(
                self::PREFIX . "/preprocess_${$field['type']}_field_load",
                $value,
                $acf_value,
                $post_id,
                $field
            );
        }
    }

    $acf_site_settings = ACF_Site_Settings::getInstance();
    $acf_site_settings->init();
}
