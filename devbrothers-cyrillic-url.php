<?php
/**
 * Plugin Name: DevBrothers Cyrillic URL
 * Plugin URI: https://devbrothers.ru/cyrillic-slugs
 * Description: Automatic transliteration of Cyrillic URLs to Latin according to ISO 9 standard. Support for all post types including WooCommerce.
 * Version: 1.0.1
 * Author: DevBrothers
 * Author URI: https://devbrothers.ru
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: devbrothers-cyrillic-url
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: devbrothers-admin-panel
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DBCS_VERSION', '1.0.0');
define('DBCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DBCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DBCS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('DBCS_PREFIX', 'dbcs');

function dbcs_get_default_settings() {
    return [
        'post_types' => ['post', 'page', 'attachment'],
        'taxonomies' => ['category', 'post_tag', 'post_format'],
    ];
}

function dbcs_activate_plugin() {
    if (get_option('dbcs_settings', null) === null) {
        add_option('dbcs_settings', dbcs_get_default_settings());
    }
}

register_activation_hook(__FILE__, 'dbcs_activate_plugin');

class DevBrothers_Cyrillic_Slugs {

    /**
     * @var DevBrothers_Cyrillic_Slugs
     */
    private static $instance = null;

    /**
     * @var DBCS_Transliterator
     */
    public $transliterator;

    /**
     * @var DBCS_Settings
     */
    public $settings;

    /**
     * @var DBCS_Converter
     */
    public $converter;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
    }

    private function load_dependencies() {
        require_once DBCS_PLUGIN_DIR . 'includes/class-transliterator.php';
        require_once DBCS_PLUGIN_DIR . 'includes/class-settings.php';
        require_once DBCS_PLUGIN_DIR . 'includes/class-converter.php';
    }

    private function init_hooks() {
        add_action('devbrothers_ready', [$this, 'register_in_devbrothers']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    private function init_components() {
        $this->transliterator = new DBCS_Transliterator();
        $this->settings = new DBCS_Settings();
        $this->converter = new DBCS_Converter($this->transliterator);
    }

    public function register_in_devbrothers() {
        if (!function_exists('devbrothers_register_plugin')) {
            return;
        }

        devbrothers_register_plugin([
            'id'   => 'cyrillic-slugs',
            'name' => __('Cyrillic URL', 'devbrothers-cyrillic-url'),
            'name_ru' => __('Кириллические URL', 'devbrothers-cyrillic-url'),
            'description' => __('Автоматическая транслитерация кириллических URL в латиницу', 'devbrothers-cyrillic-url'),
            'version' => DBCS_VERSION,
            'icon' => 'dashicons-translation',
            'settings_callback' => [$this->settings, 'render_settings_page'],
            'categories' => [
                [
                    'id'   => 'general',
                    'name' => __('Основные настройки', 'devbrothers-cyrillic-url'),
                    'icon' => 'dashicons-admin-generic',
                ],
                [
                    'id'   => 'conversion',
                    'name' => __('Конвертация URL', 'devbrothers-cyrillic-url'),
                    'icon' => 'dashicons-update',
                ],
                [
                    'id'   => 'about',
                    'name' => __('О плагине', 'devbrothers-cyrillic-url'),
                    'icon' => 'dashicons-info',
                ],
            ],
        ]);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'devbrothers') === false) {
            return;
        }

        wp_enqueue_style(
            'dbcs-admin',
            DBCS_PLUGIN_URL . 'assets/css/admin.css',
            ['devbrothers-admin'],
            DBCS_VERSION
        );

        wp_enqueue_script(
            'dbcs-admin',
            DBCS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            DBCS_VERSION,
            true
        );

        wp_localize_script('dbcs-admin', 'dbcsData', [
            'ajax_url'             => admin_url('admin-ajax.php'),
            'nonce'                => wp_create_nonce('dbcs_convert_nonce'),
            'confirm_convert'      => __('Вы уверены, что хотите конвертировать все существующие URL?\n\nЭто действие изменит slug всех записей и терминов с кириллицей.\nРекомендуется сделать резервную копию перед началом.', 'devbrothers-cyrillic-url'),
            'progress_text'        => __('Конвертировано: {converted} из {total}', 'devbrothers-cyrillic-url'),
            'conversion_completed' => __('Конвертация завершена!', 'devbrothers-cyrillic-url'),
            'converted_count'      => __('Успешно конвертировано: {count} элементов.', 'devbrothers-cyrillic-url'),
            'errors_count'         => __('Ошибок: {count}', 'devbrothers-cyrillic-url'),
            'show_errors'          => __('Показать ошибки', 'devbrothers-cyrillic-url'),
            'error_title'          => __('Ошибка!', 'devbrothers-cyrillic-url'),
            'ajax_error'           => __('Ошибка AJAX: {error}', 'devbrothers-cyrillic-url'),
            'unknown_error'        => __('Произошла неизвестная ошибка', 'devbrothers-cyrillic-url'),
            'leave_page_warning'   => __('Конвертация еще не завершена. Вы уверены, что хотите покинуть страницу?', 'devbrothers-cyrillic-url'),
        ]);
    }
}

function dbcs_plugin() {
    return DevBrothers_Cyrillic_Slugs::get_instance();
}

add_action('plugins_loaded', 'dbcs_plugin', 10);
