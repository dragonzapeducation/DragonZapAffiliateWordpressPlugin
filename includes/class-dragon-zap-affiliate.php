<?php

if (! defined('ABSPATH')) {
    exit;
}

final class Dragon_Zap_Affiliate
{
    private const OPTION_API_KEY = 'dragon_zap_affiliate_api_key';
    private const OPTION_API_BASE_URI = 'dragon_zap_affiliate_api_base_uri';
    private const NONCE_ACTION = 'dragon_zap_affiliate_test';
    private const OPTION_AUTO_APPEND = 'dragon_zap_affiliate_auto_append';
    private const DEFAULT_API_BASE_URI = 'https://affiliate.dragonzap.com/api/v1';

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * @var string
     */
    private $plugin_file;

    /**
     * @var string|null
     */
    private $settings_page_hook = null;

    /**
     * @var bool
     */
    private $sdk_autoloader_registered = false;

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_dza_test_connection', [$this, 'handle_test_request']);
        add_action('init', [$this, 'register_frontend_assets']);
        add_action('init', [$this, 'register_blocks']);
        add_action('widgets_init', [$this, 'register_widgets']);
      
        add_action('save_post', [$this, 'clear_related_courses_cache']);
        add_action('delete_post', [$this, 'clear_related_courses_cache']);
        
        $this->ensure_sdk_autoload();

    }

    public static function get_instance(string $plugin_file): self
    {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    public function register_menu(): void
    {
        $this->settings_page_hook = add_options_page(
            __('Dragon Zap Affiliate', 'dragon-zap-affiliate'),
            __('Dragon Zap Affiliate', 'dragon-zap-affiliate'),
            'manage_options',
            'dragon-zap-affiliate',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'dragon_zap_affiliate',
            self::OPTION_API_KEY,
            [
                'sanitize_callback' => [$this, 'sanitize_api_key'],
                'type' => 'string',
                'default' => '',
            ]
        );

        add_settings_section(
            'dragon_zap_affiliate_general',
            __('API Settings', 'dragon-zap-affiliate'),
            static function (): void {
                echo '<p>' . esc_html__('Enter your Dragon Zap Affiliate API credentials.', 'dragon-zap-affiliate') . '</p>';
            },
            'dragon_zap_affiliate'
        );

        add_settings_field(
            self::OPTION_API_KEY,
            __('API Key', 'dragon-zap-affiliate'),
            [$this, 'render_api_key_field'],
            'dragon_zap_affiliate',
            'dragon_zap_affiliate_general'
        );

        register_setting(
            'dragon_zap_affiliate',
            self::OPTION_API_BASE_URI,
            [
                'sanitize_callback' => [$this, 'sanitize_api_base_uri'],
                'type' => 'string',
                'default' => self::DEFAULT_API_BASE_URI,
            ]
        );

        add_settings_field(
            self::OPTION_API_BASE_URI,
            __('API Base URL', 'dragon-zap-affiliate'),
            [$this, 'render_api_base_uri_field'],
            'dragon_zap_affiliate',
            'dragon_zap_affiliate_general'
        );

        register_setting(
            'dragon_zap_affiliate',
            self::OPTION_AUTO_APPEND,
            [
                'sanitize_callback' => [$this, 'sanitize_checkbox_value'],
                'type' => 'boolean',
                'default' => true,
            ]
        );

        add_settings_field(
            self::OPTION_AUTO_APPEND,
            __('Automatically append widget', 'dragon-zap-affiliate'),
            [$this, 'render_auto_append_field'],
            'dragon_zap_affiliate',
            'dragon_zap_affiliate_general'
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($this->settings_page_hook === null || $hook !== $this->settings_page_hook) {
            return;
        }

        wp_enqueue_style(
            'dragon-zap-affiliate-admin',
            $this->plugin_url('assets/css/admin.css'),
            [],
            $this->plugin_version()
        );

        wp_enqueue_script(
            'dragon-zap-affiliate-admin',
            $this->plugin_url('assets/js/admin.js'),
            ['jquery'],
            $this->plugin_version(),
            true
        );

        wp_localize_script(
            'dragon-zap-affiliate-admin',
            'dragonZapAffiliate',
            [
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'buttonLabel' => __('Test Connection', 'dragon-zap-affiliate'),
                'testingText' => __('Testing...', 'dragon-zap-affiliate'),
                'testSuccessMessage' => __('Connection successful!', 'dragon-zap-affiliate'),
                'testErrorMessage' => __('Connection failed. Please check your API key and try again.', 'dragon-zap-affiliate'),
                'scopesTitle' => __('Authorized scopes', 'dragon-zap-affiliate'),
                'scopesEmpty' => __('No scopes were returned for this API key.', 'dragon-zap-affiliate'),
                'restrictionsTitle' => __('Restricted scopes', 'dragon-zap-affiliate'),
                'restrictionsEmpty' => __('No restricted scopes were reported for this API key.', 'dragon-zap-affiliate'),
                'restrictionsHelp' => __('Restricted scopes require additional approval. Contact Dragon Zap support to request access.', 'dragon-zap-affiliate'),
                'endpointTitle' => __('Affiliate test endpoint', 'dragon-zap-affiliate'),
            ]
        );
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'dragon-zap-affiliate'));
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Dragon Zap Affiliate', 'dragon-zap-affiliate'); ?></h1>
            <?php settings_errors('dragon_zap_affiliate'); ?>
            <form id="dragon-zap-affiliate-settings" action="options.php" method="post">
                <?php
                settings_fields('dragon_zap_affiliate');
                do_settings_sections('dragon_zap_affiliate');
                submit_button();
                ?>
            </form>

            <p>
                <button type="button" class="button button-secondary" id="dragon-zap-affiliate-test">
                    <?php esc_html_e('Test Connection', 'dragon-zap-affiliate'); ?>
                </button>
            </p>
            <div id="dragon-zap-affiliate-test-result" role="status" aria-live="polite"></div>
        </div>
        <?php
    }

    public function render_api_key_field(): void
    {
        $value = $this->get_api_key();

        printf(
            '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="new-password" spellcheck="false" />',
            esc_attr(self::OPTION_API_KEY),
            esc_attr($value)
        );

        echo '<p class="description">' . esc_html__('The API key is stored securely in your WordPress options table.', 'dragon-zap-affiliate') . '</p>';
    }

    public function render_api_base_uri_field(): void
    {
        $value = $this->get_api_base_uri();

        printf(
            '<input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text" spellcheck="false" />',
            esc_attr(self::OPTION_API_BASE_URI),
            esc_attr($value)
        );

        echo '<p class="description">' . esc_html__('Change the API base URL if instructed by Dragon Zap support. The default value works for most integrations.', 'dragon-zap-affiliate') . '</p>';
    }

    public function render_auto_append_field(): void
    {
        $value = $this->is_auto_append_enabled();

        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
            esc_attr(self::OPTION_AUTO_APPEND),
            checked($value, true, false),
            esc_html__('Display the related courses widget after the post content by default.', 'dragon-zap-affiliate'),
            esc_html__('Uncheck to place the widget manually using the block or widget editor.', 'dragon-zap-affiliate')
        );
    }

    /**
     * @param mixed $value
     */
    public function sanitize_api_key($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim(sanitize_text_field($value));
    }

    public function handle_test_request(): void
    {

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized request.', 'dragon-zap-affiliate')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $api_key = isset($_POST['api_key']) ? $this->sanitize_api_key(wp_unslash($_POST['api_key'])) : '';

        if ($api_key === '') {
            $api_key = $this->get_api_key();
        }

        if ($api_key === '') {
            wp_send_json_error(['message' => esc_html__('Please enter an API key before testing the connection.', 'dragon-zap-affiliate')], 400);
        }

        try {
            $client = new \DragonZap\AffiliateApi\Client($api_key, $this->get_api_base_uri());
            $response = $client->testConnection();
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage(),
            ]);
        }

        wp_send_json_success([
            'message' => $response['message'] ?? esc_html__('Connection successful!', 'dragon-zap-affiliate'),
            'response' => $response,
        ]);
    }

    private function ensure_sdk_autoload(): void
    {
        if ($this->sdk_autoloader_registered) {
            return;
        }

        $sdk_dir = plugin_dir_path($this->plugin_file) . 'DragonZapAffiliateApiPhpSdk/';
        $autoload = $sdk_dir . 'vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
            $this->sdk_autoloader_registered = true;
            return;
        }

        spl_autoload_register(function (string $class) use ($sdk_dir): void {
            $prefix = 'DragonZap\\AffiliateApi\\';

            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
            $path = $sdk_dir . 'src/' . $relative . '.php';

            if (file_exists($path)) {
                require_once $path;
            }
        });

        $this->sdk_autoloader_registered = true;
    }

    public function register_frontend_assets(): void
    {
        wp_register_style(
            'dragon-zap-affiliate-related-courses',
            $this->plugin_url('assets/css/related-courses.css'),
            [],
            $this->plugin_version()
        );
    }

    public function register_blocks(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        $script_handle = 'dragon-zap-affiliate-related-courses-block';

        wp_register_script(
            $script_handle,
            $this->plugin_url('assets/js/block-related-courses.js'),
            ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-editor'],
            $this->plugin_version(),
            true
        );

        $editor_style_handle = 'dragon-zap-affiliate-related-courses-block-editor';

        wp_register_style(
            $editor_style_handle,
            $this->plugin_url('assets/css/block-related-courses-editor.css'),
            ['wp-edit-blocks'],
            $this->plugin_version()
        );

        register_block_type('dragon-zap-affiliate/related-courses', [
            'api_version' => 2,
            'editor_script' => $script_handle,
            'editor_style' => $editor_style_handle,
            'style' => 'dragon-zap-affiliate-related-courses',
            'render_callback' => [$this, 'render_related_courses_block'],
            'attributes' => [
                'title' => [
                    'type' => 'string',
                    'default' => __('Recommended Courses', 'dragon-zap-affiliate'),
                ],
                'showTitle' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showImages' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showDescription' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showPrice' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'backgroundColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'textColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'accentColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'borderColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'customClass' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    public function register_widgets(): void
    {
        $widget_file = plugin_dir_path($this->plugin_file) . 'includes/class-dragon-zap-affiliate-related-courses-widget.php';

        if (! class_exists('Dragon_Zap_Affiliate_Related_Courses_Widget') && file_exists($widget_file)) {
            require_once $widget_file;
        }

        if (class_exists('Dragon_Zap_Affiliate_Related_Courses_Widget')) {
            register_widget('Dragon_Zap_Affiliate_Related_Courses_Widget');
        }
    }

    /**
     * @param array<string, mixed> $attributes
     * @param string $content
     * @param mixed $block
     */
    public function render_related_courses_block(array $attributes = [], string $content = '', $block = null): string
    {
        $show_title = isset($attributes['showTitle']) ? (bool) $attributes['showTitle'] : true;
        $title = '';
        $is_editor_request = is_admin() || (function_exists('wp_is_json_request') && wp_is_json_request());

        if ($show_title) {
            $title = isset($attributes['title']) && is_string($attributes['title'])
                ? $attributes['title']
                : __('Recommended Courses', 'dragon-zap-affiliate');
        }

        $show_images = isset($attributes['showImages']) ? (bool) $attributes['showImages'] : true;
        $show_description = isset($attributes['showDescription']) ? (bool) $attributes['showDescription'] : true;
        $show_price = isset($attributes['showPrice']) ? (bool) $attributes['showPrice'] : true;
        $background_color = isset($attributes['backgroundColor']) && is_string($attributes['backgroundColor'])
            ? $attributes['backgroundColor']
            : '';
        $text_color = isset($attributes['textColor']) && is_string($attributes['textColor'])
            ? $attributes['textColor']
            : '';
        $accent_color = isset($attributes['accentColor']) && is_string($attributes['accentColor'])
            ? $attributes['accentColor']
            : '';
        $border_color = isset($attributes['borderColor']) && is_string($attributes['borderColor'])
            ? $attributes['borderColor']
            : '';
        $custom_class = isset($attributes['customClass']) && is_string($attributes['customClass'])
            ? $attributes['customClass']
            : '';

        $post_id = get_the_ID();

        if (! $post_id || get_post_type($post_id) !== 'post') {
            if ($is_editor_request) {
                return '<p>' . esc_html__('Related courses will appear on single posts.', 'dragon-zap-affiliate') . '</p>';
            }

            return '';
        }

        $markup = $this->get_related_courses_markup((int) $post_id, [
            'title' => $title,
            'context' => 'block',
            'show_images' => $show_images,
            'show_description' => $show_description,
            'show_price' => $show_price,
            'background_color' => $background_color,
            'text_color' => $text_color,
            'accent_color' => $accent_color,
            'border_color' => $border_color,
            'custom_class' => $custom_class,
        ]);

        if ($markup === '' && $is_editor_request) {
            return '<p>' . esc_html__('No related courses are available yet for this post.', 'dragon-zap-affiliate') . '</p>';
        }

        return $markup;
    }

    /**
     * @param mixed $content
     * @return mixed
     */
    public function append_related_courses_to_content($content)
    {
        if (! is_singular('post') || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        if (! $this->is_auto_append_enabled()) {
            return $content;
        }

        $post_id = absint(get_the_ID());

        if ($post_id <= 0) {

            return $content;
        }

        $markup = $this->get_related_courses_markup($post_id, [
            'title' => __('Recommended Courses', 'dragon-zap-affiliate'),
            'context' => 'content',
        ]);

        if ($markup === '') {
            
            return $content;
        }

        return $content . $markup;
    }

    public function clear_related_courses_cache(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) {
            return;
        }

        if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'post') {
            return;
        }

        delete_transient($this->get_related_courses_transient_key($post_id));
    }

    public function get_related_courses_for_post(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $cache_key = $this->get_related_courses_transient_key($post_id);;
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            return [];
        }

        $search_terms = $this->build_post_search_terms($post);
        $client = $this->create_api_client();

        if ($client === null) {
            return [];
        }

        try {
            $response = $client->products()->list([
                'per_page' => 3,
                'search' => $search_terms,
                'type' => 'App\\Models\\Course',
            ]);
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            return [];
        } catch (\Throwable $exception) {
            return [];
        }

        // if the array is empty we will bring back without search terms.
        if (empty($response['data']['products']))
        {
            $search_terms = '';
            try {
                $response = $client->products()->list([
                    'per_page' => 3,
                    'search' => $search_terms,
                    'type' => 'App\\Models\\Course',
                ]);
            } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
                return [];
            } catch (\Throwable $exception) {
                return [];
            }
        }
        $products = [];

        if (is_array($response) && isset($response['data']['products']) && is_array($response['data']['products'])) {
            $products = $response['data']['products'];
        }

        $courses = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $type = isset($product['type']) ? (string) $product['type'] : '';

            if ($type !== '' && $type !== 'App\\Models\\Course') {
                continue;
            }

            $title = isset($product['title']) ? sanitize_text_field((string) $product['title']) : '';
            $url = isset($product['url']) ? esc_url_raw((string) $product['url']) : '';

            if ($title === '' || $url === '') {
                continue;
            }

            $image = isset($product['image']) ? esc_url_raw((string) $product['image']) : '';
            $description = '';

            if (! empty($product['description'])) {
                $description = wp_trim_words(wp_strip_all_tags((string) $product['description']), 24, '…');
            }

            $price = null;

            if (isset($product['price']) && is_numeric($product['price'])) {
                $price = (float) $product['price'];
            }

            $currency = isset($product['currency']) ? sanitize_text_field((string) $product['currency']) : '';

            $courses[] = [
                'title' => $title,
                'url' => $url,
                'image' => $image,
                'description' => $description,
                'price' => $price,
                'currency' => $currency,
            ];

            if (count($courses) === 3) {
                break;
            }
        }

        $courses = apply_filters('dragon_zap_affiliate_related_courses', $courses, $post_id, $search_terms);

        if (! is_array($courses)) {
            $courses = [];
        }

        set_transient($cache_key, $courses, 12 * HOUR_IN_SECONDS);

        return $courses;
    }

    public function get_related_courses_markup(int $post_id, array $options = []): string
    {
        $courses = $this->get_related_courses_for_post($post_id);

        if (empty($courses)) {
            return '';
        }

        if (wp_style_is('dragon-zap-affiliate-related-courses', 'registered')) {
            wp_enqueue_style('dragon-zap-affiliate-related-courses');
        } else {
            wp_enqueue_style(
                'dragon-zap-affiliate-related-courses',
                $this->plugin_url('assets/css/related-courses.css'),
                [],
                $this->plugin_version()
            );
        }

        $title = $options['title'] ?? __('Recommended Courses', 'dragon-zap-affiliate');
        $context = isset($options['context']) ? (string) $options['context'] : '';
        $show_images = isset($options['show_images']) ? (bool) $options['show_images'] : true;
        $show_description = isset($options['show_description']) ? (bool) $options['show_description'] : true;
        $show_price = isset($options['show_price']) ? (bool) $options['show_price'] : true;
        $background_color = isset($options['background_color']) ? (string) $options['background_color'] : '';
        $text_color = isset($options['text_color']) ? (string) $options['text_color'] : '';
        $accent_color = isset($options['accent_color']) ? (string) $options['accent_color'] : '';
        $border_color = isset($options['border_color']) ? (string) $options['border_color'] : '';
        $custom_class = isset($options['custom_class']) ? (string) $options['custom_class'] : '';

        $classes = ['dragon-zap-affiliate-related-courses'];

        if ($context !== '') {
            $classes[] = 'dragon-zap-affiliate-related-courses--' . sanitize_html_class($context);
        }

        if (! $show_images) {
            $classes[] = 'dragon-zap-affiliate-related-courses--no-images';
        }

        if ($custom_class !== '') {
            $custom_classnames = preg_split('/\s+/', $custom_class) ?: [];

            foreach ($custom_classnames as $classname) {
                $classname = trim($classname);

                if ($classname !== '') {
                    $classes[] = sanitize_html_class($classname);
                }
            }
        }

        $style_variables = [];
        $background_color = $this->sanitize_color_value($background_color);
        $text_color = $this->sanitize_color_value($text_color);
        $accent_color = $this->sanitize_color_value($accent_color);
        $border_color = $this->sanitize_color_value($border_color);

        if ($background_color !== '') {
            $style_variables[] = '--dza-related-bg: ' . $background_color . ';';
        }

        if ($text_color !== '') {
            $style_variables[] = '--dza-related-text: ' . $text_color . ';';
            $style_variables[] = '--dza-related-muted: ' . $text_color . ';';
        }

        if ($accent_color !== '') {
            $style_variables[] = '--dza-related-accent: ' . $accent_color . ';';
        }

        if ($border_color !== '') {
            $style_variables[] = '--dza-related-border: ' . $border_color . ';';
        }

        $class_attribute = implode(' ', array_unique(array_filter($classes)));
        $style_attribute = $style_variables !== []
            ? ' style="' . esc_attr(implode(' ', $style_variables)) . '"'
            : '';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class_attribute); ?>"<?php echo $style_attribute; ?>>
            <?php if ($title !== '') : ?>
                <h2 class="dragon-zap-affiliate-related-courses__heading"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <ul class="dragon-zap-affiliate-related-courses__list">
                <?php foreach ($courses as $course) : ?>
                    <li class="dragon-zap-affiliate-related-courses__item">
                        <?php if ($show_images && ! empty($course['image'])) : ?>
                            <a class="dragon-zap-affiliate-related-courses__image-link" href="<?php echo esc_url($course['url']); ?>" target="_blank" rel="nofollow noopener">
                                <img class="dragon-zap-affiliate-related-courses__image" src="<?php echo esc_url($course['image']); ?>" alt="<?php echo esc_attr($course['title']); ?>" loading="lazy" />
                            </a>
                        <?php endif; ?>
                        <div class="dragon-zap-affiliate-related-courses__content">
                            <a class="dragon-zap-affiliate-related-courses__title" href="<?php echo esc_url($course['url']); ?>" target="_blank" rel="nofollow noopener">
                                <?php echo esc_html($course['title']); ?>
                            </a>
                            <?php if ($show_price && $course['price'] !== null) : ?>
                                <div class="dragon-zap-affiliate-related-courses__price"><?php echo esc_html($this->format_course_price($course)); ?></div>
                            <?php endif; ?>
                            <?php if ($show_description && ! empty($course['description'])) : ?>
                                <p class="dragon-zap-affiliate-related-courses__description"><?php echo esc_html($course['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    private function is_auto_append_enabled(): bool
    {
        $value = get_option(self::OPTION_AUTO_APPEND, true);

        if (is_string($value)) {
            return $value === '1';
        }

        return (bool) $value;
    }

    public function sanitize_checkbox_value($value): bool
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return ! empty($value);
    }

    private function sanitize_color_value(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $sanitized = sanitize_hex_color($value);

        return is_string($sanitized) ? $sanitized : '';
    }

    private function get_related_courses_transient_key(int $post_id): string
    {
        return 'dragon_zap_affiliate_related_courses_' . $post_id;
    }

    private function create_api_client(): ?\DragonZap\AffiliateApi\Client
    {
        $api_key = $this->get_api_key();

        if ($api_key === '') {
            return null;
        }

        $this->ensure_sdk_autoload();

        return new \DragonZap\AffiliateApi\Client($api_key, $this->get_api_base_uri());
    }

    private function build_post_search_terms(\WP_Post $post): string
    {
        $terms = [];

        $tags = get_the_tags($post->ID);

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (isset($tag->name)) {
                    $terms[] = sanitize_text_field($tag->name);
                }
            }
        }

        $categories = get_the_category($post->ID);

        if (is_array($categories)) {
            foreach ($categories as $category) {
                if (isset($category->name)) {
                    $terms[] = sanitize_text_field($category->name);
                }
            }
        }

        $title_words = preg_split('/\s+/', wp_strip_all_tags($post->post_title));

        if (is_array($title_words)) {
            foreach ($title_words as $word) {
                $word = sanitize_text_field($word);

                if ($word !== '') {
                    $terms[] = $word;
                }
            }
        }

        $terms = array_values(array_filter(array_unique($terms), static function (string $value): bool {
            return $value !== '';
        }));

        if (empty($terms)) {
            return '';
        }

        $terms = array_slice($terms, 0, 10);

        return implode(' ', $terms);
    }

    private function format_course_price(array $course): string
    {
        if (! isset($course['price']) || $course['price'] === null) {
            return '';
        }

        $price = (float) $course['price'];
        $currency = isset($course['currency']) ? (string) $course['currency'] : '';
        $formatted_price = number_format_i18n($price, 2);

        if ($currency !== '') {
            return trim($currency . ' ' . $formatted_price);
        }

        return $formatted_price;
    }

    private function plugin_url(string $path = ''): string
    {
        $url = plugin_dir_url($this->plugin_file);

        if ($path !== '') {
            $url .= ltrim($path, '/');
        }

        return $url;
    }

    private function plugin_version(): string
    {
        $data = get_file_data($this->plugin_file, ['Version' => 'Version']);

        return $data['Version'] ?: '1.0.0';
    }

    private function get_api_key(): string
    {
        $value = get_option(self::OPTION_API_KEY, '');

        if (! is_string($value)) {
            return '';
        }

        return $this->sanitize_api_key($value);
    }

    private function get_api_base_uri(): string
    {
        $value = get_option(self::OPTION_API_BASE_URI, self::DEFAULT_API_BASE_URI);

        if (! is_string($value) || $value === '') {
            return self::DEFAULT_API_BASE_URI;
        }

        $value = $this->sanitize_api_base_uri($value);

        if ($value === '') {
            return self::DEFAULT_API_BASE_URI;
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public function sanitize_api_base_uri($value): string
    {
        if (! is_string($value)) {
            return self::DEFAULT_API_BASE_URI;
        }

        $value = trim($value);

        if ($value === '') {
            return self::DEFAULT_API_BASE_URI;
        }

        $value = esc_url_raw($value);

        if (! is_string($value) || $value === '') {
            return self::DEFAULT_API_BASE_URI;
        }

        return untrailingslashit($value);
    }
}
