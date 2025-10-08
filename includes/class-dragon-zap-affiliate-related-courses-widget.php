<?php

if (! defined('ABSPATH')) {
    exit;
}

class Dragon_Zap_Affiliate_Related_Courses_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'dragon_zap_affiliate_related_courses',
            __('Dragon Zap Related Courses', 'dragon-zap-affiliate'),
            [
                'description' => __('Displays related Dragon Zap courses for the current post.', 'dragon-zap-affiliate'),
                'show_instance_in_rest' => true,
                'customize_selective_refresh' => true,
            ]
        );
    }

    public function widget($args, $instance)
    {
        $is_admin_context = is_admin();
        $post_id = 0;

        if (is_singular('post')) {
            $post_id = absint(get_the_ID());
        } elseif (! $is_admin_context) {
            return;
        }

        if ($post_id <= 0 && ! $is_admin_context) {
            return;
        }

        $plugin = Dragon_Zap_Affiliate::instance();

        if (! $plugin instanceof Dragon_Zap_Affiliate) {
            return;
        }

        $title = isset($instance['title']) ? (string) $instance['title'] : __('Recommended Courses', 'dragon-zap-affiliate');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $show_images = ! isset($instance['show_images']) || (bool) $instance['show_images'];
        $show_description = ! isset($instance['show_description']) || (bool) $instance['show_description'];
        $show_price = ! isset($instance['show_price']) || (bool) $instance['show_price'];
        $background_color = isset($instance['background_color']) ? (string) $instance['background_color'] : '';
        $text_color = isset($instance['text_color']) ? (string) $instance['text_color'] : '';
        $accent_color = isset($instance['accent_color']) ? (string) $instance['accent_color'] : '';
        $border_color = isset($instance['border_color']) ? (string) $instance['border_color'] : '';
        $custom_class = isset($instance['custom_class']) ? (string) $instance['custom_class'] : '';

        $markup = '';

        if ($post_id > 0) {
            $markup = $plugin->get_related_courses_markup($post_id, [
                'title' => '',
                'context' => 'widget',
                'show_images' => $show_images,
                'show_description' => $show_description,
                'show_price' => $show_price,
                'background_color' => $background_color,
                'text_color' => $text_color,
                'accent_color' => $accent_color,
                'border_color' => $border_color,
                'custom_class' => $custom_class,
            ]);
        }

        if ($markup === '') {
            if ($is_admin_context) {
                echo isset($args['before_widget']) ? $args['before_widget'] : '';
                echo '<p class="dragon-zap-affiliate-widget-notice">' . esc_html__(
                    'Related courses will appear on single posts once your Dragon Zap Affiliate API key is configured and results are available.',
                    'dragon-zap-affiliate'
                ) . '</p>';
                echo isset($args['after_widget']) ? $args['after_widget'] : '';
            }

            return;
        }

        echo isset($args['before_widget']) ? $args['before_widget'] : '';

        if ($title !== '') {
            $before_title = isset($args['before_title']) ? $args['before_title'] : '<h2 class="widget-title">';
            $after_title = isset($args['after_title']) ? $args['after_title'] : '</h2>';

            echo $before_title . esc_html($title) . $after_title;
        }

        echo $markup;
        echo isset($args['after_widget']) ? $args['after_widget'] : '';
    }

    public function form($instance)
    {
        $title = isset($instance['title']) ? (string) $instance['title'] : __('Recommended Courses', 'dragon-zap-affiliate');
        $show_images = ! isset($instance['show_images']) || (bool) $instance['show_images'];
        $show_description = ! isset($instance['show_description']) || (bool) $instance['show_description'];
        $show_price = ! isset($instance['show_price']) || (bool) $instance['show_price'];
        $background_color = isset($instance['background_color']) ? (string) $instance['background_color'] : '';
        $text_color = isset($instance['text_color']) ? (string) $instance['text_color'] : '';
        $accent_color = isset($instance['accent_color']) ? (string) $instance['accent_color'] : '';
        $border_color = isset($instance['border_color']) ? (string) $instance['border_color'] : '';
        $custom_class = isset($instance['custom_class']) ? (string) $instance['custom_class'] : '';
        $field_id = $this->get_field_id('title');
        $field_name = $this->get_field_name('title');
        ?>
        <p>
            <label for="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Title:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_images')); ?>" name="<?php echo esc_attr($this->get_field_name('show_images')); ?>" value="1" <?php checked($show_images); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_images')); ?>"><?php esc_html_e('Display course images', 'dragon-zap-affiliate'); ?></label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_description')); ?>" name="<?php echo esc_attr($this->get_field_name('show_description')); ?>" value="1" <?php checked($show_description); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_description')); ?>"><?php esc_html_e('Display course descriptions', 'dragon-zap-affiliate'); ?></label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_price')); ?>" name="<?php echo esc_attr($this->get_field_name('show_price')); ?>" value="1" <?php checked($show_price); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_price')); ?>"><?php esc_html_e('Display course prices', 'dragon-zap-affiliate'); ?></label>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('background_color')); ?>"><?php esc_html_e('Background color:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($this->get_field_id('background_color')); ?>" name="<?php echo esc_attr($this->get_field_name('background_color')); ?>" type="text" value="<?php echo esc_attr($background_color); ?>" placeholder="#ffffff" data-default-color="#ffffff" />
            <span class="description"><?php esc_html_e('Leave blank to use the default widget background.', 'dragon-zap-affiliate'); ?></span>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('text_color')); ?>"><?php esc_html_e('Text color:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($this->get_field_id('text_color')); ?>" name="<?php echo esc_attr($this->get_field_name('text_color')); ?>" type="text" value="<?php echo esc_attr($text_color); ?>" placeholder="#0f172a" data-default-color="#0f172a" />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('accent_color')); ?>"><?php esc_html_e('Link & accent color:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($this->get_field_id('accent_color')); ?>" name="<?php echo esc_attr($this->get_field_name('accent_color')); ?>" type="text" value="<?php echo esc_attr($accent_color); ?>" placeholder="#1d4ed8" data-default-color="#1d4ed8" />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('border_color')); ?>"><?php esc_html_e('Border color:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($this->get_field_id('border_color')); ?>" name="<?php echo esc_attr($this->get_field_name('border_color')); ?>" type="text" value="<?php echo esc_attr($border_color); ?>" placeholder="#e2e8f0" data-default-color="#e2e8f0" />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('custom_class')); ?>"><?php esc_html_e('Additional CSS classes:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('custom_class')); ?>" name="<?php echo esc_attr($this->get_field_name('custom_class')); ?>" type="text" value="<?php echo esc_attr($custom_class); ?>" />
            <span class="description"><?php esc_html_e('Separate multiple classes with spaces.', 'dragon-zap-affiliate'); ?></span>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';

        $instance['show_images'] = ! empty($new_instance['show_images']);
        $instance['show_description'] = ! empty($new_instance['show_description']);
        $instance['show_price'] = ! empty($new_instance['show_price']);

        $instance['background_color'] = $this->sanitize_color_value(isset($new_instance['background_color']) ? $new_instance['background_color'] : '');
        $instance['text_color'] = $this->sanitize_color_value(isset($new_instance['text_color']) ? $new_instance['text_color'] : '');
        $instance['accent_color'] = $this->sanitize_color_value(isset($new_instance['accent_color']) ? $new_instance['accent_color'] : '');
        $instance['border_color'] = $this->sanitize_color_value(isset($new_instance['border_color']) ? $new_instance['border_color'] : '');
        $instance['custom_class'] = isset($new_instance['custom_class']) ? sanitize_text_field($new_instance['custom_class']) : '';

        return $instance;
    }

    private function sanitize_color_value($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $sanitized = sanitize_hex_color($value);

        return is_string($sanitized) ? $sanitized : '';
    }
}
