<?php
/**
 * Класс настроек плагина
 *
 * @package DevBrothers_Cyrillic_Slugs
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBCS_Settings {

    /**
     * @var string
     */
    private $option_name = 'dbcs_settings';

    /**
     * Санитизация настроек
     *
     * @param array $input Входные данные
     * @return array Санитизированные данные
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        if (isset($input['post_types']) && is_array($input['post_types'])) {
            $sanitized['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        }

        if (isset($input['taxonomies']) && is_array($input['taxonomies'])) {
            $sanitized['taxonomies'] = array_map('sanitize_text_field', $input['taxonomies']);
        }

        return $sanitized;
    }

    /**
     * Получение текущих настроек
     *
     * @return array
     */
    public function get_settings() {
        $defaults = [
            'post_types' => ['post', 'page', 'attachment'],
            'taxonomies' => ['category', 'post_tag', 'post_format'],
        ];

        $settings = get_option($this->option_name, $defaults);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * @return array
     */
    private function get_available_post_types() {
        $post_types = get_post_types([
            'public' => true,
        ], 'objects');

        $attachment = get_post_type_object('attachment');
        if ($attachment) {
            $post_types['attachment'] = $attachment;
        }

        $standard_types = ['post', 'page', 'attachment'];
        $sorted = [];

        foreach ($standard_types as $type) {
            if (isset($post_types[$type])) {
                $sorted[$type] = $post_types[$type];
            }
        }

        foreach ($post_types as $key => $post_type) {
            if (!in_array($key, $standard_types)) {
                $sorted[$key] = $post_type;
            }
        }

        return $sorted;
    }

    /**
     * @return array
     */
    private function get_available_taxonomies() {
        return get_taxonomies([
            'public' => true,
        ], 'objects');
    }

    /**
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Отрисовка страницы настроек
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Недостаточно прав', 'devbrothers-cyrillic-url'));
        }

        $settings_saved = false;

        if (isset($_POST['dbcs_save_settings']) &&
            isset($_POST['_wpnonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'dbcs_settings_nonce')) {
            $this->save_settings_internal();
            $settings_saved = true;
        }

        $settings = $this->get_settings();
        $post_types = $this->get_available_post_types();
        $taxonomies = $this->get_available_taxonomies();
        $is_woocommerce_active = $this->is_woocommerce_active();
        $woo_post_types = ['product', 'shop_order', 'shop_coupon'];
        $woo_taxonomies = ['product_cat', 'product_tag'];

        if ($settings_saved) {
            echo '<div class="notice notice-success"><p>' .
                 esc_html__('Настройки сохранены!', 'devbrothers-cyrillic-url') .
                 '</p></div>';
        }

        ?>
        <form method="post" action="">
            <?php wp_nonce_field('dbcs_settings_nonce'); ?>

            <!-- Категория: Основные настройки -->
            <div id="general" class="devbrothers-settings-category">
                <h2>
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Основные настройки', 'devbrothers-cyrillic-url'); ?>
                </h2>

                <p class="description">
                    <?php esc_html_e('Выберите типы записей, для которых будет автоматически работать транслитерация URL.', 'devbrothers-cyrillic-url'); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Типы записей', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Типы записей', 'devbrothers-cyrillic-url'); ?></span>
                                </legend>

                                <?php foreach ($post_types as $post_type_name => $post_type_obj) : ?>
                                    <?php $checked = in_array($post_type_name, $settings['post_types']); ?>
                                    <label>
                                        <input type="checkbox"
                                               name="dbcs_post_types[]"
                                               value="<?php echo esc_attr($post_type_name); ?>"
                                               <?php checked($checked); ?> />
                                        <?php echo esc_html($post_type_obj->label); ?>
                                        <span class="description">(<?php echo esc_html($post_type_name); ?>)</span>
                                        <?php if (in_array($post_type_name, $woo_post_types, true)) : ?>
                                            <span class="dashicons dashicons-cart dbcs-woo-icon" title="WooCommerce"></span>
                                        <?php endif; ?>
                                    </label>
                                    <br>
                                <?php endforeach; ?>
                            </fieldset>

                            <?php if ($is_woocommerce_active) : ?>
                                <p class="description dbcs-woo-detected">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('WooCommerce обнаружен! Типы товаров добавлены в список.', 'devbrothers-cyrillic-url'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Таксономии', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Таксономии', 'devbrothers-cyrillic-url'); ?></span>
                                </legend>

                                <?php foreach ($taxonomies as $taxonomy_name => $taxonomy_obj) : ?>
                                    <?php $checked = in_array($taxonomy_name, $settings['taxonomies']); ?>
                                    <label>
                                        <input type="checkbox"
                                               name="dbcs_taxonomies[]"
                                               value="<?php echo esc_attr($taxonomy_name); ?>"
                                               <?php checked($checked); ?> />
                                        <?php echo esc_html($taxonomy_obj->label); ?>
                                        <span class="description">(<?php echo esc_html($taxonomy_name); ?>)</span>
                                        <?php if (in_array($taxonomy_name, $woo_taxonomies, true)) : ?>
                                            <span class="dashicons dashicons-cart dbcs-woo-icon" title="WooCommerce"></span>
                                        <?php endif; ?>
                                    </label>
                                    <br>
                                <?php endforeach; ?>
                            </fieldset>

                            <p class="description">
                                <?php esc_html_e('Таксономии: категории, теги и другие группировки контента.', 'devbrothers-cyrillic-url'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Категория: Конвертация URL -->
            <div id="conversion" class="devbrothers-settings-category">
                <h2>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Конвертация существующих URL', 'devbrothers-cyrillic-url'); ?>
                </h2>

                <p class="description">
                    <?php esc_html_e('Конвертируйте все существующие записи и термины с кириллическими URL в латинские.', 'devbrothers-cyrillic-url'); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Массовая конвертация', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <button type="button"
                                    id="dbcs-convert-button"
                                    class="button button-primary">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Конвертировать все URL', 'devbrothers-cyrillic-url'); ?>
                            </button>

                            <p class="description">
                                <?php esc_html_e('Это действие обновит slug всех записей и терминов с кириллицей. Процесс может занять некоторое время.', 'devbrothers-cyrillic-url'); ?>
                            </p>

                            <div id="dbcs-conversion-progress" class="dbcs-progress-wrapper">
                                <div class="dbcs-progress-inner">
                                    <p><strong><?php esc_html_e('Идет конвертация...', 'devbrothers-cyrillic-url'); ?></strong></p>
                                    <div class="dbcs-progress-track">
                                        <div id="dbcs-progress-bar" class="dbcs-progress-fill"></div>
                                    </div>
                                    <p id="dbcs-progress-text" class="dbcs-progress-text">0%</p>
                                </div>
                            </div>

                            <div id="dbcs-conversion-result" class="dbcs-result-wrapper"></div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Категория: О плагине -->
            <div id="about" class="devbrothers-settings-category">
                <h2>
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('О плагине', 'devbrothers-cyrillic-url'); ?>
                </h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Версия', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <p><?php echo esc_html(DBCS_VERSION); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Стандарт транслитерации', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <p><strong>ISO 9</strong> - <?php esc_html_e('международный стандарт транслитерации кириллицы', 'devbrothers-cyrillic-url'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Информация', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <p><?php esc_html_e('Плагин автоматически конвертирует кириллические символы в URL в латинские по стандарту ISO 9.', 'devbrothers-cyrillic-url'); ?></p>
                            <p><?php esc_html_e('Это улучшает читаемость URL и совместимость с различными системами.', 'devbrothers-cyrillic-url'); ?></p>

                            <p>
                                <strong><?php esc_html_e('Пример:', 'devbrothers-cyrillic-url'); ?></strong><br>
                                <code>новая-запись</code> &rarr; <code>novaya-zapis</code>
                            </p>

                            <?php if ($is_woocommerce_active) : ?>
                                <p class="dbcs-info-box">
                                    <span class="dashicons dashicons-cart"></span>
                                    <?php esc_html_e('WooCommerce поддерживается! Плагин работает с товарами и категориями.', 'devbrothers-cyrillic-url'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('WordPress версия', 'devbrothers-cyrillic-url'); ?>
                        </th>
                        <td>
                            <p><?php echo esc_html(get_bloginfo('version')); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Кнопка сохранения -->
            <p class="submit">
                <button type="submit" name="dbcs_save_settings" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Сохранить настройки', 'devbrothers-cyrillic-url'); ?>
                </button>
            </p>
        </form>
        <?php
    }

    /**
     * Внутренний метод сохранения настроек
     */
    private function save_settings_internal() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce проверяется в render_settings_page
        $post_types = isset($_POST['dbcs_post_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['dbcs_post_types'])) : [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce проверяется в render_settings_page
        $taxonomies = isset($_POST['dbcs_taxonomies']) ? array_map('sanitize_text_field', wp_unslash($_POST['dbcs_taxonomies'])) : [];

        $settings = [
            'post_types' => $post_types,
            'taxonomies' => $taxonomies,
        ];

        update_option($this->option_name, $settings);
    }
}
