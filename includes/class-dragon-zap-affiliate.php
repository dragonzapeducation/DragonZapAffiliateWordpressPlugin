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
    private const META_BLOG_ENABLED = '_dragon_zap_affiliate_blog_enabled';
    private const META_BLOG_ID = '_dragon_zap_affiliate_blog_id';
    private const META_BLOG_PROFILE_ID = '_dragon_zap_affiliate_blog_profile_id';
    private const META_BLOG_CATEGORY = '_dragon_zap_affiliate_blog_category_slug';
    private const NOTICE_TRANSIENT_PREFIX = 'dragon_zap_affiliate_notice_';
    private const META_BLOG_FINAL_PROMPT_SHOWN = '_dragon_zap_affiliate_blog_prompt_shown';
    private const BLOG_PROFILE_CREATE_SENTINEL = '__dragon_zap_affiliate_create_profile';

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

    /**
     * @var array<string, mixed>|null
     */
    private $cached_capabilities = null;

    /**
     * @var array<string, mixed>|null
     */
    private $cached_blog_profiles = null;

    /**
     * @var array<string, mixed>|null
     */
    private $cached_blog_categories = null;

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_dza_test_connection', [$this, 'handle_test_request']);
        add_action('wp_ajax_dza_create_blog_profile', [$this, 'handle_create_blog_profile_request']);
        add_action('init', [$this, 'register_frontend_assets']);
        add_action('init', [$this, 'register_blocks']);
        add_action('widgets_init', [$this, 'register_widgets']);
        add_filter('the_content', [$this, 'append_related_courses_to_content']);
      
        add_action('save_post', [$this, 'clear_related_courses_cache']);
        add_action('delete_post', [$this, 'clear_related_courses_cache']);
        add_action('add_meta_boxes', [$this, 'register_post_meta_box']);
        add_action('save_post', [$this, 'handle_blog_submission'], 20, 3);
        add_action('admin_notices', [$this, 'render_admin_notices']);
        add_action('admin_footer-post.php', [$this, 'maybe_highlight_blog_meta_box']);
        
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
                echo '<p>' . esc_html__('Enter your Dragon Zap Affiliate API credentials.', 'dragon-zap-affiliate') . '</p>             <p>You can generate your API key on your affilaite account here: <a href="https://dragonzap.com/user/affiliate">https://dragonzap.com/user/affiliate</a></p>';
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
        if ($this->settings_page_hook !== null && $hook === $this->settings_page_hook) {
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

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;

            if ($screen !== null && $screen->post_type === 'post') {
                wp_enqueue_style(
                    'dragon-zap-affiliate-blog-meta-box',
                    $this->plugin_url('assets/css/blog-meta-box.css'),
                    [],
                    $this->plugin_version()
                );

                wp_enqueue_script(
                    'dragon-zap-affiliate-blog-meta-box',
                    $this->plugin_url('assets/js/blog-meta-box.js'),
                    ['jquery'],
                    $this->plugin_version(),
                    true
                );

                wp_localize_script(
                    'dragon-zap-affiliate-blog-meta-box',
                    'dragonZapAffiliateBlogMeta',
                    [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('dragon_zap_affiliate_create_blog_profile'),
                        'createOptionValue' => self::BLOG_PROFILE_CREATE_SENTINEL,
                        'strings' => [
                            'modalTitle' => __('Create blog profile', 'dragon-zap-affiliate'),
                            'nameLabel' => __('Profile name', 'dragon-zap-affiliate'),
                            'identifierLabel' => __('Profile identifier', 'dragon-zap-affiliate'),
                            'namePlaceholder' => __('e.g. Main Site', 'dragon-zap-affiliate'),
                            'identifierPlaceholder' => __('e.g. main-site', 'dragon-zap-affiliate'),
                            'createButton' => __('Create profile', 'dragon-zap-affiliate'),
                            'cancelButton' => __('Cancel', 'dragon-zap-affiliate'),
                            'creatingText' => __('Creating...', 'dragon-zap-affiliate'),
                            'errorDefault' => __('Something went wrong. Please try again.', 'dragon-zap-affiliate'),
                            'missingFields' => __('Please enter both a name and an identifier.', 'dragon-zap-affiliate'),
                            'successMessage' => __('Blog profile created successfully.', 'dragon-zap-affiliate'),
                            'createOptionLabel' => __('Create new profile', 'dragon-zap-affiliate'),
                        ],
                    ]
                );
            }
        }

        if ($hook === 'widgets.php' || $hook === 'customize.php') {
            wp_enqueue_style('wp-color-picker');

            wp_enqueue_script(
                'dragon-zap-affiliate-widget-controls',
                $this->plugin_url('assets/js/widget-related-courses-controls.js'),
                ['jquery', 'wp-color-picker'],
                $this->plugin_version(),
                true
            );
        }
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

    public function handle_create_blog_profile_request(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized request.', 'dragon-zap-affiliate')], 403);
        }

        check_ajax_referer('dragon_zap_affiliate_create_blog_profile', 'nonce');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $identifier = isset($_POST['identifier']) ? sanitize_text_field(wp_unslash($_POST['identifier'])) : '';

        if ($name === '' || $identifier === '') {
            wp_send_json_error(['message' => esc_html__('Both a profile name and identifier are required.', 'dragon-zap-affiliate')], 400);
        }

        $capabilities = $this->get_api_capabilities();
        $capability_error = isset($capabilities['error']) && is_string($capabilities['error']) ? $capabilities['error'] : '';

        if ($capability_error !== '') {
            wp_send_json_error(['message' => $capability_error], 400);
        }

        if (empty($capabilities['has_blogs_accounts_manage'])) {
            wp_send_json_error(['message' => esc_html__('Your API key is not authorised to create blog profiles.', 'dragon-zap-affiliate')], 403);
        }

        if (! empty($capabilities['blogs_accounts_manage_restricted'])) {
            wp_send_json_error(['message' => esc_html__('Blog profile creation is restricted for your API key. Contact Dragon Zap support to request access.', 'dragon-zap-affiliate')], 403);
        }

        $client = $this->create_api_client();

        if ($client === null) {
            wp_send_json_error(['message' => esc_html__('A Dragon Zap API key is required to create blog profiles.', 'dragon-zap-affiliate')], 400);
        }

        try {
            $response = $client->blogProfiles()->create([
                'name' => $name,
                'identifier' => $identifier,
            ]);
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 500);
        }

        $profile = [];

        if (isset($response['data']['profile']) && is_array($response['data']['profile'])) {
            $profile = $response['data']['profile'];
        }

        $id = isset($profile['id']) && is_numeric($profile['id']) ? (int) $profile['id'] : 0;
        $profile_name = isset($profile['name']) ? (string) $profile['name'] : $name;
        $profile_identifier = isset($profile['identifier']) ? (string) $profile['identifier'] : $identifier;

        if ($id <= 0) {
            wp_send_json_error(['message' => esc_html__('The blog profile was created but no ID was returned. Please refresh and try again.', 'dragon-zap-affiliate')], 500);
        }

        wp_send_json_success([
            'message' => esc_html__('Blog profile created successfully.', 'dragon-zap-affiliate'),
            'profile' => [
                'id' => $id,
                'name' => $profile_name,
                'identifier' => $profile_identifier,
            ],
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

    public function register_post_meta_box(): void
    {
        if (! is_admin()) {
            return;
        }
        
        add_meta_box(
            'dragon-zap-affiliate-blog',
            __('Post to Dragon Zap', 'dragon-zap-affiliate'),
            [$this, 'render_blog_meta_box'],
            'post',
            'side',
            'high',
            [
                '__block_editor_compatible_meta_box' => true,
            ]
        );
    }

    public function render_blog_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('dragon_zap_affiliate_blog_meta', 'dragon_zap_affiliate_blog_nonce');

        $enabled = get_post_meta($post->ID, self::META_BLOG_ENABLED, true) === '1';
        $blog_id = get_post_meta($post->ID, self::META_BLOG_ID, true);
        $selected_profile = (string) get_post_meta($post->ID, self::META_BLOG_PROFILE_ID, true);
        $selected_category = (string) get_post_meta($post->ID, self::META_BLOG_CATEGORY, true);

        $checkbox_id = 'dragon-zap-affiliate-blog-enabled-' . $post->ID;

        echo '<p>';
        echo '<label for="' . esc_attr($checkbox_id) . '">';
        echo '<input type="checkbox" id="' . esc_attr($checkbox_id) . '" name="dragon_zap_affiliate_blog_enabled" value="1" ' . checked($enabled, true, false) . ' />';
        echo ' ' . esc_html__('Send this post to Dragon Zap', 'dragon-zap-affiliate');
        echo '</label>';
        echo '</p>';

        if ($blog_id !== '') {
            echo '<p class="description">';
            printf(
                /* translators: %s: Dragon Zap blog ID */
                esc_html__('Linked Dragon Zap blog ID: %s', 'dragon-zap-affiliate'),
                esc_html($blog_id)
            );
            echo '</p>';
        }

        $capabilities = $this->get_api_capabilities();
        $capability_error = isset($capabilities['error']) && is_string($capabilities['error']) ? $capabilities['error'] : '';

        if ($capability_error !== '') {
            echo '<p class="description">' . esc_html($capability_error) . '</p>';
            return;
        }

        if (empty($capabilities['has_blogs_manage'])) {
            echo '<p class="description">' . esc_html__('Your API key is not authorised to manage Dragon Zap blogs.', 'dragon-zap-affiliate') . '</p>';
            return;
        }

        if (! empty($capabilities['blogs_manage_restricted'])) {
            echo '<p class="description">' . esc_html__('Blog publishing is currently restricted for your API key. Please contact Dragon Zap support to request access.', 'dragon-zap-affiliate') . '</p>';
            return;
        }

        echo '<p class="description">' . esc_html__('When enabled, the post content will be sent to Dragon Zap as a draft for review.', 'dragon-zap-affiliate') . '</p>';

        echo '<div class="dragon-zap-affiliate-blog-meta" data-dza-blog-meta="1" data-post-id="' . esc_attr((string) $post->ID) . '" data-create-option="' . esc_attr(self::BLOG_PROFILE_CREATE_SENTINEL) . '">';

        $profiles = $this->get_blog_profiles();
        $profile_items = isset($profiles['items']) && is_array($profiles['items']) ? $profiles['items'] : [];
        $profiles_error = isset($profiles['error']) && is_string($profiles['error']) ? $profiles['error'] : '';
        $profile_field_id = 'dragon-zap-affiliate-blog-profile-' . $post->ID;

        if (! empty($profile_items)) {
            $options = [];

            foreach ($profile_items as $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                $id = isset($profile['id']) ? (string) $profile['id'] : '';

                if ($id === '') {
                    continue;
                }

                $label_parts = [];

                if (! empty($profile['name'])) {
                    $label_parts[] = (string) $profile['name'];
                }

                if (! empty($profile['identifier'])) {
                    $label_parts[] = sprintf('(%s)', (string) $profile['identifier']);
                }

                $options[$id] = implode(' ', $label_parts);
            }

            if ($selected_profile !== '' && ! isset($options[$selected_profile])) {
                $options[$selected_profile] = sprintf(
                    /* translators: %s: Blog profile ID */
                    __('Current profile (%s)', 'dragon-zap-affiliate'),
                    $selected_profile
                );
            }

            echo '<p>';
            echo '<label for="' . esc_attr($profile_field_id) . '">' . esc_html__('Blog profile', 'dragon-zap-affiliate') . '</label><br />';
            echo '<select name="dragon_zap_affiliate_blog_profile_id" id="' . esc_attr($profile_field_id) . '" data-dza-blog-profile-select="1">';
            echo '<option value="">' . esc_html__('Select a blog profile', 'dragon-zap-affiliate') . '</option>';

            foreach ($options as $value => $label) {
                echo '<option value="' . esc_attr($value) . '" ' . selected($selected_profile, (string) $value, false) . '>' . esc_html($label) . '</option>';
            }

            echo '<option value="' . esc_attr(self::BLOG_PROFILE_CREATE_SENTINEL) . '">' . esc_html__('Create new profile', 'dragon-zap-affiliate') . '</option>';

            echo '</select>';
            echo '</p>';
        } else {
            echo '<p>';
            echo '<label for="' . esc_attr($profile_field_id) . '">' . esc_html__('Blog profile ID', 'dragon-zap-affiliate') . '</label><br />';
            echo '<input type="number" min="1" step="1" name="dragon_zap_affiliate_blog_profile_id" id="' . esc_attr($profile_field_id) . '" value="' . esc_attr($selected_profile) . '" class="small-text" />';
            echo '</p>';
        }

        if ($profiles_error !== '') {
            echo '<p class="description">' . esc_html($profiles_error) . '</p>';
        } elseif (empty($profile_items)) {
            echo '<p class="description">' . esc_html__('No blog profiles were returned. Create one in your Dragon Zap dashboard or enter the profile ID manually.', 'dragon-zap-affiliate') . '</p>';
        }

        $categories = $this->get_blog_categories();
        $category_items = isset($categories['items']) && is_array($categories['items']) ? $categories['items'] : [];
        $categories_error = isset($categories['error']) && is_string($categories['error']) ? $categories['error'] : '';
        $category_field_id = 'dragon-zap-affiliate-blog-category-' . $post->ID;

        if (! empty($category_items)) {
            echo '<p>';
            echo '<label for="' . esc_attr($category_field_id) . '">' . esc_html__('Dragon Zap category', 'dragon-zap-affiliate') . '</label><br />';
            echo '<select name="dragon_zap_affiliate_blog_category" id="' . esc_attr($category_field_id) . '">';
            echo '<option value="">' . esc_html__('Select a category', 'dragon-zap-affiliate') . '</option>';

            foreach ($category_items as $category) {
                if (! is_array($category)) {
                    continue;
                }

                $slug = isset($category['slug']) ? (string) $category['slug'] : '';

                if ($slug === '') {
                    continue;
                }

                $name = isset($category['name']) ? (string) $category['name'] : $slug;

                echo '<option value="' . esc_attr($slug) . '" ' . selected($selected_category, $slug, false) . '>' . esc_html($name) . '</option>';
            }

            echo '</select>';
            echo '</p>';
        } else {
            echo '<p>';
            echo '<label for="' . esc_attr($category_field_id) . '">' . esc_html__('Dragon Zap category slug', 'dragon-zap-affiliate') . '</label><br />';
            echo '<input type="text" name="dragon_zap_affiliate_blog_category" id="' . esc_attr($category_field_id) . '" value="' . esc_attr($selected_category) . '" class="regular-text" />';
            echo '</p>';
        }

        if ($categories_error !== '') {
            echo '<p class="description">' . esc_html($categories_error) . '</p>';
        } elseif (empty($category_items)) {
            echo '<p class="description">' . esc_html__('Enter the Dragon Zap category slug that should be applied to this blog post.', 'dragon-zap-affiliate') . '</p>';
        }

        echo '</div>';
    }

    public function render_admin_notices(): void
    {
        if (! is_admin()) {
            return;
        }

        $key = $this->get_admin_notice_transient_key();

        if ($key === '') {
            return;
        }

        $notice = get_transient($key);

        if (! is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient($key);

        $type = isset($notice['type']) ? (string) $notice['type'] : 'info';

        switch ($type) {
            case 'success':
                $class = 'notice-success';
                break;
            case 'warning':
                $class = 'notice-warning';
                break;
            case 'error':
                $class = 'notice-error';
                break;
            default:
                $class = 'notice-info';
                break;
        }

        $message = isset($notice['message']) ? $notice['message'] : '';

        if (! is_string($message) || $message === '') {
            return;
        }

        $allow_html = ! empty($notice['allow_html']);
        $content = $allow_html ? wp_kses_post($message) : esc_html($message);

        echo '<div class="notice ' . esc_attr($class) . '"><p>' . $content . '</p></div>';
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

    public function handle_blog_submission(int $post_id, \WP_Post $post, bool $update): void
    {
        if ($post_id <= 0 || $post->post_type !== 'post') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ((function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) || (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post_id))) {
            return;
        }

        if (! isset($_POST['dragon_zap_affiliate_blog_nonce'])) {
            return;
        }

        $nonce = wp_unslash($_POST['dragon_zap_affiliate_blog_nonce']);

        if (! is_string($nonce) || ! wp_verify_nonce($nonce, 'dragon_zap_affiliate_blog_meta')) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled_raw = isset($_POST['dragon_zap_affiliate_blog_enabled']) ? wp_unslash($_POST['dragon_zap_affiliate_blog_enabled']) : '';
        $enabled = $this->sanitize_checkbox_value($enabled_raw);
        $was_enabled = get_post_meta($post_id, self::META_BLOG_ENABLED, true) === '1';

        if ($enabled) {
            update_post_meta($post_id, self::META_BLOG_ENABLED, '1');
            delete_post_meta($post_id, self::META_BLOG_FINAL_PROMPT_SHOWN);
        } else {
            delete_post_meta($post_id, self::META_BLOG_ENABLED);
        }

        $profile_raw = isset($_POST['dragon_zap_affiliate_blog_profile_id']) ? wp_unslash($_POST['dragon_zap_affiliate_blog_profile_id']) : '';
        $profile_id = is_numeric($profile_raw) ? absint($profile_raw) : 0;

        if ($profile_id > 0) {
            update_post_meta($post_id, self::META_BLOG_PROFILE_ID, (string) $profile_id);
        } else {
            delete_post_meta($post_id, self::META_BLOG_PROFILE_ID);
        }

        $category_raw = isset($_POST['dragon_zap_affiliate_blog_category']) ? wp_unslash($_POST['dragon_zap_affiliate_blog_category']) : '';
        $category_slug = is_string($category_raw) ? sanitize_title($category_raw) : '';

        if ($category_slug !== '') {
            update_post_meta($post_id, self::META_BLOG_CATEGORY, $category_slug);
        } else {
            delete_post_meta($post_id, self::META_BLOG_CATEGORY);
        }

        if (! $enabled) {
            if (! $was_enabled && $post->post_status === 'publish' && ! $this->has_final_prompt_been_shown($post_id)) {
                $this->add_blog_final_prompt_notice($post_id);
            }

            return;
        }

        if ($profile_id <= 0) {
            $this->add_admin_notice('error', __('Please select or enter a valid blog profile ID before sending the post to Dragon Zap.', 'dragon-zap-affiliate'));
            return;
        }

        if ($category_slug === '') {
            $this->add_admin_notice('error', __('Please select or enter a Dragon Zap category before sending the post.', 'dragon-zap-affiliate'));
            return;
        }

        $capabilities = $this->get_api_capabilities();
        $capability_error = isset($capabilities['error']) && is_string($capabilities['error']) ? $capabilities['error'] : '';

        if ($capability_error !== '') {
            $this->add_admin_notice('error', sprintf(__('Unable to contact the Dragon Zap API: %s', 'dragon-zap-affiliate'), $capability_error));
            return;
        }

        if (empty($capabilities['has_blogs_manage']) || ! empty($capabilities['blogs_manage_restricted'])) {
            $this->add_admin_notice('error', __('Your API key is not authorised to manage Dragon Zap blogs.', 'dragon-zap-affiliate'));
            return;
        }

        $client = $this->create_api_client();

        if ($client === null) {
            $this->add_admin_notice('error', __('A Dragon Zap API key is required before posts can be sent.', 'dragon-zap-affiliate'));
            return;
        }

        $blog_id = (string) get_post_meta($post_id, self::META_BLOG_ID, true);

        $payload = [
            'title' => wp_strip_all_tags($post->post_title),
            'content' => $this->prepare_post_content_for_blog($post),
            'category_slug' => $category_slug,
            'blog_profile_id' => $profile_id,
        ];

        if ($payload['content'] === '') {
            $this->add_admin_notice('error', __('The post content is empty after processing. Please add content before sending it to Dragon Zap.', 'dragon-zap-affiliate'));
            return;
        }

        try {
            if ($blog_id !== '') {
                $client->blogs()->update($blog_id, $payload);
                $this->add_admin_notice('success', __('Dragon Zap blog entry updated successfully.', 'dragon-zap-affiliate'));
            } else {
                $response = $client->blogs()->create($payload);
                $created_blog = isset($response['data']['blog']) && is_array($response['data']['blog']) ? $response['data']['blog'] : [];
                $new_id = isset($created_blog['id']) ? (string) $created_blog['id'] : '';

                if ($new_id !== '') {
                    update_post_meta($post_id, self::META_BLOG_ID, $new_id);
                }

                $this->add_admin_notice('success', __('Dragon Zap blog entry created successfully.', 'dragon-zap-affiliate'));
            }
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            $this->add_admin_notice('error', sprintf(__('Dragon Zap API error: %s', 'dragon-zap-affiliate'), $exception->getMessage()));
            return;
        } catch (\Throwable $exception) {
            $this->add_admin_notice('error', sprintf(__('Unexpected error while sending the blog to Dragon Zap: %s', 'dragon-zap-affiliate'), $exception->getMessage()));
            return;
        }

        update_post_meta($post_id, self::META_BLOG_PROFILE_ID, (string) $profile_id);
        update_post_meta($post_id, self::META_BLOG_CATEGORY, $category_slug);
        update_post_meta($post_id, self::META_BLOG_ENABLED, '1');
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

    /**
     * @return array<string, mixed>
     */
    private function get_api_capabilities(): array
    {
        if (is_array($this->cached_capabilities)) {
            return $this->cached_capabilities;
        }

        $result = [
            'scopes' => [],
            'restrictions' => [],
            'has_blogs_manage' => false,
            'blogs_manage_restricted' => false,
            'has_blogs_accounts_manage' => false,
            'blogs_accounts_manage_restricted' => false,
            'error' => '',
        ];

        $client = $this->create_api_client();

        if ($client === null) {
            $result['error'] = __('Enter your Dragon Zap Affiliate API key to enable blog publishing.', 'dragon-zap-affiliate');
            $this->cached_capabilities = $result;

            return $result;
        }

        try {
            $response = $client->testConnection();
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            $result['error'] = $exception->getMessage();
            $this->cached_capabilities = $result;

            return $result;
        } catch (\Throwable $exception) {
            $result['error'] = $exception->getMessage();
            $this->cached_capabilities = $result;

            return $result;
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];

        $scopes = [];

        if (isset($data['scopes']) && is_array($data['scopes'])) {
            foreach ($data['scopes'] as $scope) {
                if (is_string($scope) && $scope !== '') {
                    $scopes[] = $scope;
                }
            }
        }

        $restrictions = [];

        if (isset($data['restrictions']) && is_array($data['restrictions'])) {
            foreach ($data['restrictions'] as $restriction) {
                if (is_string($restriction) && $restriction !== '') {
                    $restrictions[] = $restriction;
                }
            }
        }

        $result['scopes'] = $scopes;
        $result['restrictions'] = $restrictions;
        $result['has_blogs_manage'] = in_array('blogs.manage', $scopes, true);
        $result['blogs_manage_restricted'] = in_array('blogs.manage', $restrictions, true);
        $result['has_blogs_accounts_manage'] = in_array('blogs.accounts.manage', $scopes, true);
        $result['blogs_accounts_manage_restricted'] = in_array('blogs.accounts.manage', $restrictions, true);

        $this->cached_capabilities = $result;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function get_blog_profiles(): array
    {
        if (is_array($this->cached_blog_profiles)) {
            return $this->cached_blog_profiles;
        }

        $result = [
            'items' => [],
            'error' => '',
        ];

        $capabilities = $this->get_api_capabilities();
        $capability_error = isset($capabilities['error']) && is_string($capabilities['error']) ? $capabilities['error'] : '';

        if ($capability_error !== '') {
            $result['error'] = $capability_error;

            return $this->cached_blog_profiles = $result;
        }

        if (empty($capabilities['has_blogs_accounts_manage'])) {
            if (! empty($capabilities['blogs_accounts_manage_restricted'])) {
                $result['error'] = __('Blog profile access is restricted for your API key.', 'dragon-zap-affiliate');
            } else {
                $result['error'] = __('Your API key cannot list blog profiles. Enter the profile ID manually.', 'dragon-zap-affiliate');
            }

            return $this->cached_blog_profiles = $result;
        }

        if (! empty($capabilities['blogs_accounts_manage_restricted'])) {
            $result['error'] = __('Blog profile access is restricted for your API key.', 'dragon-zap-affiliate');

            return $this->cached_blog_profiles = $result;
        }

        $client = $this->create_api_client();

        if ($client === null) {
            $result['error'] = __('A Dragon Zap API key is required to load blog profiles.', 'dragon-zap-affiliate');

            return $this->cached_blog_profiles = $result;
        }

        try {
            $response = $client->blogProfiles()->list();
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            $result['error'] = $exception->getMessage();

            return $this->cached_blog_profiles = $result;
        } catch (\Throwable $exception) {
            $result['error'] = $exception->getMessage();

            return $this->cached_blog_profiles = $result;
        }

        $profiles = [];

        if (isset($response['data']['profiles']) && is_array($response['data']['profiles'])) {
            foreach ($response['data']['profiles'] as $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                $id = isset($profile['id']) ? (int) $profile['id'] : 0;

                if ($id <= 0) {
                    continue;
                }

                $profiles[] = [
                    'id' => $id,
                    'name' => isset($profile['name']) ? (string) $profile['name'] : '',
                    'identifier' => isset($profile['identifier']) ? (string) $profile['identifier'] : '',
                ];
            }
        }

        $result['items'] = $profiles;

        return $this->cached_blog_profiles = $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function get_blog_categories(): array
    {
        if (is_array($this->cached_blog_categories)) {
            return $this->cached_blog_categories;
        }

        $result = [
            'items' => [],
            'error' => '',
        ];

        $client = $this->create_api_client();

        if ($client === null) {
            $result['error'] = __('A Dragon Zap API key is required to load categories.', 'dragon-zap-affiliate');

            return $this->cached_blog_categories = $result;
        }

        try {
            $response = $client->categories()->list(['per_page' => 100]);
        } catch (\DragonZap\AffiliateApi\Exceptions\ApiException $exception) {
            $result['error'] = $exception->getMessage();

            return $this->cached_blog_categories = $result;
        } catch (\Throwable $exception) {
            $result['error'] = $exception->getMessage();

            return $this->cached_blog_categories = $result;
        }

        $categories = [];

        if (isset($response['data']['categories']) && is_array($response['data']['categories'])) {
            foreach ($response['data']['categories'] as $category) {
                if (! is_array($category)) {
                    continue;
                }

                $slug = isset($category['slug']) ? (string) $category['slug'] : '';

                if ($slug === '') {
                    continue;
                }

                $categories[] = [
                    'slug' => $slug,
                    'name' => isset($category['name']) ? (string) $category['name'] : $slug,
                ];
            }
        }

        $result['items'] = $categories;

        return $this->cached_blog_categories = $result;
    }

    private function prepare_post_content_for_blog(\WP_Post $post): string
    {
        $content = $post->post_content;
        $filter_removed = false;

        if (has_filter('the_content', [$this, 'append_related_courses_to_content'])) {
            remove_filter('the_content', [$this, 'append_related_courses_to_content']);
            $filter_removed = true;
        }

        $original_post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;

        $processed = apply_filters('the_content', $content);

        if ($filter_removed) {
            add_filter('the_content', [$this, 'append_related_courses_to_content']);
        }

        if ($original_post !== null) {
            $GLOBALS['post'] = $original_post;
        } else {
            unset($GLOBALS['post']);
        }

        if (! is_string($processed)) {
            $processed = '';
        }

        $processed = wp_kses_post($processed);

        return trim($processed);
    }

    private function add_admin_notice(string $type, string $message): void
    {
        $key = $this->get_admin_notice_transient_key();

        if ($key === '') {
            return;
        }

        set_transient($key, [
            'type' => $type,
            'message' => $message,
            'allow_html' => false,
        ], 5 * MINUTE_IN_SECONDS);
    }

    private function add_admin_notice_with_html(string $type, string $message): void
    {
        $key = $this->get_admin_notice_transient_key();

        if ($key === '') {
            return;
        }

        set_transient($key, [
            'type' => $type,
            'message' => $message,
            'allow_html' => true,
        ], 5 * MINUTE_IN_SECONDS);
    }

    private function add_blog_final_prompt_notice(int $post_id): void
    {
        $edit_link = get_edit_post_link($post_id, 'raw');

        if ($edit_link === false) {
            return;
        }

        $edit_link = add_query_arg('dza_focus_blog_meta', '1', $edit_link) . '#dragon-zap-affiliate-blog';
        $title = get_the_title($post_id);

        if (! is_string($title) || $title === '') {
            $title = __('this post', 'dragon-zap-affiliate');
        }

        $title = wp_strip_all_tags($title);

        $message = sprintf(
            /* translators: %1$s: Post title, %2$s: URL to edit post */
            __(
                'You just published “%1$s” without sending it to Dragon Zap. <a href="%2$s" class="button button-primary">Review Dragon Zap options</a>',
                'dragon-zap-affiliate'
            ),
            $title,
            esc_url($edit_link)
        );

        $this->add_admin_notice_with_html('warning', $message);
        update_post_meta($post_id, self::META_BLOG_FINAL_PROMPT_SHOWN, '1');
    }

    private function has_final_prompt_been_shown(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_BLOG_FINAL_PROMPT_SHOWN, true) === '1';
    }

    public function maybe_highlight_blog_meta_box(): void
    {
        if (! isset($_GET['dza_focus_blog_meta'])) {
            return;
        }

        $focus_raw = wp_unslash($_GET['dza_focus_blog_meta']);

        if ($focus_raw !== '1') {
            return;
        }

        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;

        if ($post_id <= 0 || ! current_user_can('edit_post', $post_id)) {
            return;
        }

        ?>
        <style>
            #dragon-zap-affiliate-blog.dragon-zap-affiliate-highlight {
                box-shadow: 0 0 0 2px #0073aa;
                transition: box-shadow 0.3s ease;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var metaBox = document.getElementById('dragon-zap-affiliate-blog');

                if (!metaBox) {
                    return;
                }

                metaBox.classList.add('dragon-zap-affiliate-highlight');

                if (typeof metaBox.scrollIntoView === 'function') {
                    try {
                        metaBox.scrollIntoView({behavior: 'smooth', block: 'center'});
                    } catch (error) {
                        metaBox.scrollIntoView();
                    }
                }

                window.setTimeout(function () {
                    metaBox.classList.remove('dragon-zap-affiliate-highlight');
                }, 4000);
            });
        </script>
        <?php
    }

    private function get_admin_notice_transient_key(): string
    {
        $user_id = get_current_user_id();

        if ($user_id <= 0) {
            return '';
        }

        return self::NOTICE_TRANSIENT_PREFIX . $user_id;
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
