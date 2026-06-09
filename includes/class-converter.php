<?php
/**
 * Класс конвертера существующих URL
 *
 * @package DevBrothers_Cyrillic_Slugs
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBCS_Converter {

    /**
     * @var DBCS_Transliterator
     */
    private $transliterator;

    /**
     * @var int
     */
    private $batch_limit = 100;

    /**
     * @param DBCS_Transliterator $transliterator
     */
    public function __construct($transliterator) {
        $this->transliterator = $transliterator;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_dbcs_convert_urls', [$this, 'ajax_convert_urls']);
    }

    public function ajax_convert_urls() {
        check_ajax_referer('dbcs_convert_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Недостаточно прав для выполнения этой операции', 'devbrothers-cyrillic-url'),
            ]);
        }

        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        $result = $this->convert_batch($offset);

        wp_send_json_success($result);
    }

    /**
     * Конвертация одной партии записей
     *
     * @param int $offset Смещение для SQL-выборки записей
     * @return array
     */
    public function convert_batch($offset = 0) {
        $settings   = get_option('dbcs_settings', []);
        $post_types = !empty($settings['post_types']) ? $settings['post_types'] : ['post', 'page'];
        $taxonomies = !empty($settings['taxonomies']) ? $settings['taxonomies'] : [];

        // Кэшируем total при первом вызове, чтобы не считать заново каждый батч
        if ($offset === 0) {
            $total = $this->get_total_count($post_types, $taxonomies);
            set_transient('dbcs_convert_total', $total, HOUR_IN_SECONDS);
        } else {
            $total = (int) get_transient('dbcs_convert_total');
            if (!$total) {
                $total = $this->get_total_count($post_types, $taxonomies);
            }
        }

        $converted_posts = 0;
        $converted_terms = 0;
        $errors          = [];

        // Всегда обрабатываем записи по текущему offset
        $posts_result    = $this->convert_posts($post_types, $offset);
        $converted_posts = $posts_result['converted'];
        $errors          = array_merge($errors, $posts_result['errors']);
        $posts_done      = $posts_result['fetched'] < $this->batch_limit;

        // Когда все записи просканированы, обрабатываем термины за один проход
        if ($posts_done) {
            $terms_result    = $this->convert_terms($taxonomies, 0);
            $converted_terms = $terms_result['converted'];
            $errors          = array_merge($errors, $terms_result['errors']);
        }

        $next_offset = $offset + $this->batch_limit;
        $has_more    = !$posts_done;

        if (!$has_more) {
            delete_transient('dbcs_convert_total');
        }

        return [
            'converted_posts' => $converted_posts,
            'converted_terms' => $converted_terms,
            'total'           => max($total, 1),
            'offset'          => $next_offset,
            'has_more'        => $has_more,
            'errors'          => $errors,
        ];
    }

    /**
     * @param array $post_types
     * @param int   $offset
     * @return array{converted: int, fetched: int, errors: array}
     */
    private function convert_posts($post_types, $offset = 0) {
        global $wpdb;

        $offset    = absint($offset);
        $limit     = absint($this->batch_limit);
        $converted = 0;
        $errors    = [];

        if (empty($post_types)) {
            return ['converted' => 0, 'fetched' => 0, 'errors' => []];
        }

        $sanitized_post_types = array_map('sanitize_text_field', (array) $post_types);
        $placeholders         = implode(', ', array_fill(0, count($sanitized_post_types), '%s'));

        $sql = "
            SELECT ID, post_name, post_type, post_status
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
              AND post_status NOT IN ('trash', 'auto-draft')
            LIMIT %d OFFSET %d
        ";

        $args = array_merge($sanitized_post_types, [$limit, $offset]);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $args));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $posts = $wpdb->get_results($query);

        $fetched_count = is_array($posts) ? count($posts) : 0;

        $posts = array_filter($posts, function ($post) {
            $decoded_slug = urldecode($post->post_name);
            return preg_match('/[а-яёА-ЯЁ]/u', $decoded_slug);
        });

        foreach ($posts as $post) {
            try {
                $decoded_slug = urldecode($post->post_name);
                $new_slug     = $this->transliterator->transliterate($decoded_slug);

                $new_slug = wp_unique_post_slug(
                    $new_slug,
                    $post->ID,
                    get_post_status($post->ID),
                    $post->post_type,
                    0
                );

                $updated = wp_update_post([
                    'ID'        => $post->ID,
                    'post_name' => $new_slug,
                ], true);

                if (is_wp_error($updated)) {
                    $errors[] = sprintf(
                        /* translators: 1: Post ID, 2: Error message */
                        __('Ошибка при обновлении записи #%1$d: %2$s', 'devbrothers-cyrillic-url'),
                        $post->ID,
                        $updated->get_error_message()
                    );
                } else {
                    $converted++;
                }
            } catch (Exception $e) {
                $errors[] = sprintf(
                    /* translators: 1: Post ID, 2: Exception message */
                    __('Исключение при обновлении записи #%1$d: %2$s', 'devbrothers-cyrillic-url'),
                    $post->ID,
                    $e->getMessage()
                );
            }
        }

        return [
            'converted' => $converted,
            'fetched'   => $fetched_count,
            'errors'    => $errors,
        ];
    }

    /**
     * @param array $taxonomies
     * @param int   $offset
     * @return array{converted: int, fetched: int, errors: array}
     */
    private function convert_terms($taxonomies, $offset = 0) {
        global $wpdb;

        $offset    = absint($offset);
        $limit     = absint($this->batch_limit);
        $converted = 0;
        $errors    = [];

        if (empty($taxonomies)) {
            $taxonomies = get_taxonomies(['public' => true]);
        }

        if (empty($taxonomies)) {
            return ['converted' => 0, 'fetched' => 0, 'errors' => []];
        }

        $sanitized_taxonomies = array_map('sanitize_text_field', (array) $taxonomies);
        $placeholders         = implode(', ', array_fill(0, count($sanitized_taxonomies), '%s'));

        $sql = "
            SELECT t.term_id, t.slug, tt.taxonomy
            FROM {$wpdb->terms} AS t
            INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ($placeholders)
            LIMIT %d OFFSET %d
        ";

        $args = array_merge($sanitized_taxonomies, [$limit, $offset]);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $args));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $terms = $wpdb->get_results($query);

        $fetched_count = is_array($terms) ? count($terms) : 0;

        $terms = array_filter($terms, function ($term) {
            $decoded_slug = urldecode($term->slug);
            return preg_match('/[а-яёА-ЯЁ]/u', $decoded_slug);
        });

        foreach ($terms as $term) {
            try {
                $decoded_slug = urldecode($term->slug);
                $new_slug     = $this->transliterator->transliterate($decoded_slug);

                $updated = wp_update_term($term->term_id, $term->taxonomy, [
                    'slug' => $new_slug,
                ]);

                if (is_wp_error($updated)) {
                    $errors[] = sprintf(
                        /* translators: 1: Term ID, 2: Error message */
                        __('Ошибка при обновлении термина #%1$d: %2$s', 'devbrothers-cyrillic-url'),
                        $term->term_id,
                        $updated->get_error_message()
                    );
                } else {
                    $converted++;
                }
            } catch (Exception $e) {
                $errors[] = sprintf(
                    /* translators: 1: Term ID, 2: Exception message */
                    __('Исключение при обновлении термина #%1$d: %2$s', 'devbrothers-cyrillic-url'),
                    $term->term_id,
                    $e->getMessage()
                );
            }
        }

        return [
            'converted' => $converted,
            'fetched'   => $fetched_count,
            'errors'    => $errors,
        ];
    }

    /**
     * Подсчёт элементов с кириллическими slug
     *
     * @param array $post_types
     * @param array $taxonomies
     * @return int
     */
    private function get_total_count($post_types, $taxonomies) {
        global $wpdb;

        $total = 0;

        if (!empty($post_types)) {
            $sanitized_post_types = array_map('sanitize_text_field', (array) $post_types);
            $placeholders         = implode(', ', array_fill(0, count($sanitized_post_types), '%s'));
            $sql = "
                SELECT post_name
                FROM {$wpdb->posts}
                WHERE post_type IN ($placeholders)
                  AND post_status NOT IN ('trash', 'auto-draft')
            ";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $query = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $sanitized_post_types));

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $posts = $wpdb->get_col($query);
            foreach ($posts as $post_name) {
                $decoded = urldecode($post_name);
                if (preg_match('/[а-яёА-ЯЁ]/u', $decoded)) {
                    $total++;
                }
            }
        }

        if (empty($taxonomies)) {
            $taxonomies = get_taxonomies(['public' => true]);
        }

        if (!empty($taxonomies)) {
            $sanitized_taxonomies = array_map('sanitize_text_field', (array) $taxonomies);
            $placeholders         = implode(', ', array_fill(0, count($sanitized_taxonomies), '%s'));
            $sql = "
                SELECT t.slug
                FROM {$wpdb->terms} AS t
                INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ($placeholders)
            ";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $query = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $sanitized_taxonomies));

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $slugs = $wpdb->get_col($query);
            foreach ($slugs as $slug) {
                $decoded = urldecode($slug);
                if (preg_match('/[а-яёА-ЯЁ]/u', $decoded)) {
                    $total++;
                }
            }
        }

        return $total;
    }

    /**
     * Конвертация всех URL (синхронная, для CLI или крона)
     *
     * @return array
     */
    public function convert_all() {
        $settings   = get_option('dbcs_settings', []);
        $post_types = !empty($settings['post_types']) ? $settings['post_types'] : ['post', 'page'];
        $taxonomies = !empty($settings['taxonomies']) ? $settings['taxonomies'] : [];

        $total_converted = 0;
        $all_errors      = [];

        $offset = 0;
        do {
            $result          = $this->convert_posts($post_types, $offset);
            $total_converted += $result['converted'];
            $all_errors       = array_merge($all_errors, $result['errors']);
            $offset          += $this->batch_limit;
        } while ($result['fetched'] >= $this->batch_limit);

        $offset = 0;
        do {
            $result          = $this->convert_terms($taxonomies, $offset);
            $total_converted += $result['converted'];
            $all_errors       = array_merge($all_errors, $result['errors']);
            $offset          += $this->batch_limit;
        } while ($result['fetched'] >= $this->batch_limit);

        return [
            'total_converted' => $total_converted,
            'errors'          => $all_errors,
        ];
    }
}
