<?php
/**
 * Класс транслитератора
 *
 * @package DevBrothers_Cyrillic_Slugs
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBCS_Transliterator {

    /**
     * Таблица транслитерации ISO 9
     * @var array
     */
    private $translit_table = [];

    public function __construct() {
        $this->init_translit_table();
        $this->init_hooks();
    }

    /**
     * Инициализация таблицы транслитерации ISO 9
     */
    private function init_translit_table() {
        $this->translit_table = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'j',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shh',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',

            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'Yo',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'J',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Shh',
            'Ъ' => '',
            'Ы' => 'Y',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',

            '№' => '',
            '«' => '',
            '»' => '',
            '—' => '-',
            '–' => '-',
        ];

        $this->translit_table = apply_filters('dbcs_translit_table', $this->translit_table);
    }

    /**
     * Инициализация хуков
     *
     * Фильтры sanitize_title, sanitize_file_name и wp_insert_post_data применяются глобально,
     * но каждый обработчик содержит строгие проверки контекста.
     */
    private function init_hooks() {
        add_filter('sanitize_title', [$this, 'transliterate_title'], 9, 3);
        add_filter('sanitize_file_name', [$this, 'transliterate_filename'], 9);
        add_filter('wp_insert_post_data', [$this, 'transliterate_post_slug'], 10, 2);
    }

    /**
     * Транслитерация slug при сохранении записи (REST API / Gutenberg)
     *
     * @param array $data  Данные записи
     * @param array $postarr Массив аргументов
     * @return array
     */
    public function transliterate_post_slug($data, $postarr) {
        if (isset($data['post_type']) && $data['post_type'] === 'revision') {
            return $data;
        }

        if (isset($data['post_status']) && $data['post_status'] === 'auto-draft') {
            return $data;
        }

        $settings = get_option('dbcs_settings', []);
        if (empty($settings['post_types'])) {
            $settings['post_types'] = ['post', 'page'];
        }

        if (empty($data['post_type']) || !in_array($data['post_type'], $settings['post_types'])) {
            return $data;
        }

        $slug        = isset($data['post_name']) ? $data['post_name'] : '';
        $title       = isset($data['post_title']) ? $data['post_title'] : '';
        $post_status = isset($data['post_status']) ? $data['post_status'] : '';

        $decoded_slug = urldecode($slug);

        // Slug содержит кириллицу — транслитерируем
        if (!empty($decoded_slug) && $this->has_cyrillic($decoded_slug)) {
            $data['post_name'] = $this->transliterate($decoded_slug);
            return $data;
        }

        // Slug пустой, заголовок кириллический, не черновик — генерируем
        if (empty($slug) && !empty($title) && $this->has_cyrillic($title)) {
            if (!in_array($post_status, ['auto-draft', 'draft'], true)) {
                $data['post_name'] = $this->transliterate($title);
            }
            return $data;
        }

        // При публикации: slug мог быть сгенерирован из слова-статуса («Черновик» и т.п.)
        if ($post_status === 'publish' && !empty($title) && $this->has_cyrillic($title)) {
            $correct_slug = $this->transliterate($title);

            if (!empty($decoded_slug) && $decoded_slug !== $correct_slug) {
                $is_auto_slug = $this->is_auto_generated_slug($decoded_slug);

                if ($is_auto_slug) {
                    $data['post_name'] = $correct_slug;
                }
            }
        }

        return $data;
    }

    /**
     * Проверяет, является ли slug автоматически сгенерированным
     * из слов-статусов (черновик, draft, untitled и т.д.)
     *
     * Список генерируется динамически через таблицу транслитерации,
     * поэтому при изменении таблицы список остаётся актуальным.
     *
     * @param string $slug Slug для проверки
     * @return bool
     */
    private function is_auto_generated_slug($slug) {
        $draft_labels = [
            'Черновик',
            'Без названия',
            'Чернетка',
            'Нарис',
        ];

        $auto_slugs = ['draft', 'auto-draft', 'untitled'];

        foreach ($draft_labels as $label) {
            $transliterated = $this->transliterate($label);
            if (!empty($transliterated)) {
                $auto_slugs[] = $transliterated;
            }
        }

        $auto_slugs = array_unique($auto_slugs);
        $slug_lower = strtolower($slug);

        foreach ($auto_slugs as $auto) {
            if ($slug_lower === $auto || preg_match('/^' . preg_quote($auto, '/') . '-\d+$/', $slug_lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Основная функция транслитерации
     *
     * @param string $text Текст
     * @return string
     */
    public function transliterate($text) {
        if (empty($text)) {
            return $text;
        }

        $original = $text;

        $text = strtr($text, $this->translit_table);
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace('_', ' ', $text);
        $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
        $text = preg_replace('/[\s\-]+/', '-', $text);
        $text = trim($text, '-');

        return apply_filters('dbcs_transliterate_result', $text, $original);
    }

    /**
     * Транслитерация заголовка (slug)
     *
     * @param string $title     Заголовок
     * @param string $raw_title Сырой заголовок
     * @param string $context   Контекст
     * @return string
     */
    public function transliterate_title($title, $raw_title = '', $context = 'save') {
        if ($context === 'query') {
            return $title;
        }

        $text = !empty($raw_title) ? $raw_title : $title;

        if (!$this->has_cyrillic($text)) {
            return $title;
        }

        if (!$this->should_transliterate($context)) {
            return $title;
        }

        if (!$this->is_post_type_enabled()) {
            return $title;
        }

        return $this->transliterate($text);
    }

    /**
     * Транслитерация имени файла
     *
     * @param string $filename Имя файла
     * @return string
     */
    public function transliterate_filename($filename) {
        if (!$this->has_cyrillic($filename)) {
            return $filename;
        }

        $settings = get_option('dbcs_settings', []);
        if (empty($settings['post_types']) || !in_array('attachment', $settings['post_types'])) {
            return $filename;
        }

        $pathinfo  = pathinfo($filename);
        $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
        $basename  = isset($pathinfo['filename']) ? $pathinfo['filename'] : $filename;

        return $this->transliterate($basename) . $extension;
    }

    /**
     * @param string $text
     * @return bool
     */
    private function has_cyrillic($text) {
        return preg_match('/[а-яёА-ЯЁ]/u', $text) === 1;
    }

    /**
     * @param string $context
     * @return bool
     */
    private function should_transliterate($context) {
        if ($context === 'query') {
            return false;
        }

        $is_rest = defined('REST_REQUEST') && REST_REQUEST;

        if (!is_admin() && !$is_rest && did_action('wp')) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function is_post_type_enabled() {
        $settings = get_option('dbcs_settings', []);

        if (empty($settings['post_types'])) {
            $settings['post_types'] = ['post', 'page'];
        }

        $post_type = $this->get_current_post_type();

        if ($post_type === null) {
            return false;
        }

        return in_array($post_type, $settings['post_types']);
    }

    /**
     * @return string|null
     */
    private function get_current_post_type() {
        global $post, $typenow, $current_screen;

        if ($post && isset($post->post_type)) {
            return $post->post_type;
        }

        if ($typenow) {
            return $typenow;
        }

        if ($current_screen && isset($current_screen->post_type)) {
            return $current_screen->post_type;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['post_type'])) {
            return sanitize_text_field(wp_unslash($_REQUEST['post_type']));
        }

        if (isset($_REQUEST['post'])) {
            $post_id = absint(wp_unslash($_REQUEST['post']));
            if ($post_id) {
                return get_post_type($post_id);
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $rest_route = '';

            if (isset($GLOBALS['wp']->query_vars['rest_route'])) {
                $rest_route = $GLOBALS['wp']->query_vars['rest_route'];
            }

            if (empty($rest_route) && isset($_SERVER['REQUEST_URI'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                if (preg_match('#/wp-json(/wp/v2/[a-z_-]+)#i', $request_uri, $uri_matches)) {
                    $rest_route = sanitize_text_field($uri_matches[1]);
                }
            }

            if (preg_match('#/wp/v2/([a-z_-]+)#i', $rest_route, $matches)) {
                $endpoint = strtolower($matches[1]);
                $type_map = [
                    'posts'    => 'post',
                    'pages'    => 'page',
                    'products' => 'product',
                    'media'    => 'attachment',
                ];
                if (isset($type_map[$endpoint])) {
                    return $type_map[$endpoint];
                }
                $singular = rtrim($endpoint, 's');
                if (post_type_exists($singular)) {
                    return $singular;
                }
                if (post_type_exists($endpoint)) {
                    return $endpoint;
                }
            }
        }

        return null;
    }
}
