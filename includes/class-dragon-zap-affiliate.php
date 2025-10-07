<?php

if (! defined('ABSPATH')) {
    exit;
}

final class Dragon_Zap_Affiliate
{
    private const OPTION_API_KEY = 'dragon_zap_affiliate_api_key';
    private const OPTION_API_BASE_URI = 'dragon_zap_affiliate_api_base_uri';
    private const NONCE_ACTION = 'dragon_zap_affiliate_test';
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
    }

    public static function get_instance(string $plugin_file): self
    {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }

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
                'endpointDescription' => __('Scopes are returned from the Affiliate API test endpoint below. You can call it directly with your API key to review the raw response.', 'dragon-zap-affiliate'),
                'testEndpointUrl' => trailingslashit($this->get_api_base_uri()) . 'test',
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
            $this->ensure_sdk_autoload();
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
