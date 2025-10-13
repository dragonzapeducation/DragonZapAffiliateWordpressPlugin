<?php

if (! defined('ABSPATH')) {
    exit;
}

class Dragon_Zap_Affiliate_Single_Course_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'dragon_zap_affiliate_single_course',
            __('Dragon Zap Single Course', 'dragon-zap-affiliate'),
            [
                'description' => __('Display a selected Dragon Zap course.', 'dragon-zap-affiliate'),
                'show_instance_in_rest' => true,
                'customize_selective_refresh' => true,
            ]
        );
    }

    public function widget($args, $instance)
    {
        $plugin = Dragon_Zap_Affiliate::instance();

        if (! $plugin instanceof Dragon_Zap_Affiliate) {
            return;
        }

        $course_id = isset($instance['course_id']) ? absint($instance['course_id']) : 0;

        if ($course_id <= 0) {
            if (is_admin()) {
                echo isset($args['before_widget']) ? $args['before_widget'] : '';
                echo '<p class="dragon-zap-affiliate-widget-notice">' . esc_html__('Select a course to display.', 'dragon-zap-affiliate') . '</p>';
                echo isset($args['after_widget']) ? $args['after_widget'] : '';
            }

            return;
        }

        $course = $plugin->get_course_by_id($course_id);

        if (! is_array($course) || empty($course['title']) || empty($course['url'])) {
            if (is_admin()) {
                echo isset($args['before_widget']) ? $args['before_widget'] : '';
                echo '<p class="dragon-zap-affiliate-widget-notice">' . esc_html__('The selected course could not be loaded.', 'dragon-zap-affiliate') . '</p>';
                echo isset($args['after_widget']) ? $args['after_widget'] : '';
            }

            return;
        }

        $title = isset($instance['title']) ? (string) $instance['title'] : __('Featured Course', 'dragon-zap-affiliate');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $show_images = isset($instance['show_images']) ? (bool) $instance['show_images'] : true;
        $show_description = isset($instance['show_description']) ? (bool) $instance['show_description'] : true;
        $show_price = isset($instance['show_price']) ? (bool) $instance['show_price'] : true;
        $background_color = isset($instance['background_color']) ? (string) $instance['background_color'] : '';
        $text_color = isset($instance['text_color']) ? (string) $instance['text_color'] : '';
        $accent_color = isset($instance['accent_color']) ? (string) $instance['accent_color'] : '';
        $border_color = isset($instance['border_color']) ? (string) $instance['border_color'] : '';
        $custom_class = isset($instance['custom_class']) ? (string) $instance['custom_class'] : '';

        $markup = $plugin->render_courses_markup([$course], [
            'title' => '',
            'context' => 'single-widget',
            'show_images' => $show_images,
            'show_description' => $show_description,
            'show_price' => $show_price,
            'background_color' => $background_color,
            'text_color' => $text_color,
            'accent_color' => $accent_color,
            'border_color' => $border_color,
            'custom_class' => $custom_class,
        ]);

        if ($markup === '') {
            if (is_admin()) {
                echo isset($args['before_widget']) ? $args['before_widget'] : '';
                echo '<p class="dragon-zap-affiliate-widget-notice">' . esc_html__('The selected course could not be displayed.', 'dragon-zap-affiliate') . '</p>';
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
        $title = isset($instance['title']) ? (string) $instance['title'] : __('Featured Course', 'dragon-zap-affiliate');
        $course_id = isset($instance['course_id']) ? absint($instance['course_id']) : 0;
        $show_images = isset($instance['show_images']) ? (bool) $instance['show_images'] : true;
        $show_description = isset($instance['show_description']) ? (bool) $instance['show_description'] : true;
        $show_price = isset($instance['show_price']) ? (bool) $instance['show_price'] : true;
        $background_color = isset($instance['background_color']) ? (string) $instance['background_color'] : '';
        $text_color = isset($instance['text_color']) ? (string) $instance['text_color'] : '';
        $accent_color = isset($instance['accent_color']) ? (string) $instance['accent_color'] : '';
        $border_color = isset($instance['border_color']) ? (string) $instance['border_color'] : '';
        $custom_class = isset($instance['custom_class']) ? (string) $instance['custom_class'] : '';

        $plugin = Dragon_Zap_Affiliate::instance();
        $selected_course = ($plugin instanceof Dragon_Zap_Affiliate && $course_id > 0) ? $plugin->get_course_by_id($course_id) : null;

        $course_title = '';
        $course_price = '';
        $course_url = '';
        $course_image = '';

        if (is_array($selected_course)) {
            $course_title = isset($selected_course['title']) ? (string) $selected_course['title'] : '';
            $course_url = isset($selected_course['url']) ? (string) $selected_course['url'] : '';
            $course_image = isset($selected_course['image']) ? (string) $selected_course['image'] : '';

            if (isset($selected_course['price']) && $selected_course['price'] !== null) {
                $price_value = (float) $selected_course['price'];
                $currency_value = isset($selected_course['currency']) ? (string) $selected_course['currency'] : '';
                $course_price = number_format_i18n($price_value, 2);

                if ($currency_value !== '') {
                    $course_price = trim($currency_value . ' ' . $course_price);
                }
            }
        }

        $field_title_id = $this->get_field_id('title');
        $field_title_name = $this->get_field_name('title');
        $field_course_id = $this->get_field_id('course_id');
        $field_show_images = $this->get_field_id('show_images');
        $field_show_description = $this->get_field_id('show_description');
        $field_show_price = $this->get_field_id('show_price');
        $field_background_color = $this->get_field_id('background_color');
        $field_text_color = $this->get_field_id('text_color');
        $field_accent_color = $this->get_field_id('accent_color');
        $field_border_color = $this->get_field_id('border_color');
        $field_custom_class = $this->get_field_id('custom_class');
        $field_search_id = $this->get_field_id('course_search');
        ?>
        <div class="dragon-zap-affiliate-single-course-widget" data-widget-id="<?php echo esc_attr($this->id); ?>">
            <p>
                <label for="<?php echo esc_attr($field_title_id); ?>"><?php esc_html_e('Title:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat" id="<?php echo esc_attr($field_title_id); ?>" name="<?php echo esc_attr($field_title_name); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
            </p>
            <p>
                <label for="<?php echo esc_attr($field_search_id); ?>"><?php esc_html_e('Search for a course:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat dragon-zap-affiliate-course-search" id="<?php echo esc_attr($field_search_id); ?>" type="text" value="" placeholder="<?php esc_attr_e('Type at least two characters...', 'dragon-zap-affiliate'); ?>" autocomplete="off" />
            </p>
            <input class="dragon-zap-affiliate-course-id" id="<?php echo esc_attr($field_course_id); ?>" name="<?php echo esc_attr($this->get_field_name('course_id')); ?>" type="hidden" value="<?php echo esc_attr($course_id); ?>" />
            <div class="dragon-zap-affiliate-course-selected">
                <?php if ($course_title !== '') : ?>
                    <div class="dragon-zap-affiliate-course-selected__item">
                        <?php if ($course_image !== '') : ?>
                            <img class="dragon-zap-affiliate-course-selected__image" src="<?php echo esc_url($course_image); ?>" alt="<?php echo esc_attr($course_title); ?>" />
                        <?php endif; ?>
                        <div class="dragon-zap-affiliate-course-selected__content">
                            <strong class="dragon-zap-affiliate-course-selected__title"><?php echo esc_html($course_title); ?></strong>
                            <?php if ($course_price !== '') : ?>
                                <span class="dragon-zap-affiliate-course-selected__price"><?php echo esc_html($course_price); ?></span>
                            <?php endif; ?>
                            <?php if ($course_url !== '') : ?>
                                <div><a href="<?php echo esc_url($course_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View course', 'dragon-zap-affiliate'); ?></a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="description"><?php esc_html_e('No course selected yet. Use the search above to choose one.', 'dragon-zap-affiliate'); ?></p>
                <?php endif; ?>
            </div>
            <p>
                <button type="button" class="button dragon-zap-affiliate-course-clear"<?php echo $course_id > 0 ? '' : ' style="display:none;"'; ?>><?php esc_html_e('Remove selection', 'dragon-zap-affiliate'); ?></button>
            </p>
            <ul class="dragon-zap-affiliate-course-results"></ul>
            <p class="description"><?php esc_html_e('Results appear as you type. Click a course to select it.', 'dragon-zap-affiliate'); ?></p>
            <p>
                <input id="<?php echo esc_attr($field_show_images); ?>" name="<?php echo esc_attr($this->get_field_name('show_images')); ?>" type="checkbox" value="1" <?php checked($show_images); ?> />
                <label for="<?php echo esc_attr($field_show_images); ?>"><?php esc_html_e('Display course image', 'dragon-zap-affiliate'); ?></label>
            </p>
            <p>
                <input id="<?php echo esc_attr($field_show_description); ?>" name="<?php echo esc_attr($this->get_field_name('show_description')); ?>" type="checkbox" value="1" <?php checked($show_description); ?> />
                <label for="<?php echo esc_attr($field_show_description); ?>"><?php esc_html_e('Display course description', 'dragon-zap-affiliate'); ?></label>
            </p>
            <p>
                <input id="<?php echo esc_attr($field_show_price); ?>" name="<?php echo esc_attr($this->get_field_name('show_price')); ?>" type="checkbox" value="1" <?php checked($show_price); ?> />
                <label for="<?php echo esc_attr($field_show_price); ?>"><?php esc_html_e('Display course price', 'dragon-zap-affiliate'); ?></label>
            </p>
            <p>
                <label for="<?php echo esc_attr($field_background_color); ?>"><?php esc_html_e('Background color:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($field_background_color); ?>" name="<?php echo esc_attr($this->get_field_name('background_color')); ?>" type="text" value="<?php echo esc_attr($background_color); ?>" placeholder="#ffffff" data-default-color="#ffffff" />
                <span class="description"><?php esc_html_e('Leave blank to use the default widget background.', 'dragon-zap-affiliate'); ?></span>
            </p>
            <p>
                <label for="<?php echo esc_attr($field_text_color); ?>"><?php esc_html_e('Text color:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($field_text_color); ?>" name="<?php echo esc_attr($this->get_field_name('text_color')); ?>" type="text" value="<?php echo esc_attr($text_color); ?>" placeholder="#0f172a" data-default-color="#0f172a" />
            </p>
            <p>
                <label for="<?php echo esc_attr($field_accent_color); ?>"><?php esc_html_e('Link & accent color:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($field_accent_color); ?>" name="<?php echo esc_attr($this->get_field_name('accent_color')); ?>" type="text" value="<?php echo esc_attr($accent_color); ?>" placeholder="#1d4ed8" data-default-color="#1d4ed8" />
            </p>
            <p>
                <label for="<?php echo esc_attr($field_border_color); ?>"><?php esc_html_e('Border color:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat dragon-zap-affiliate-color-field" id="<?php echo esc_attr($field_border_color); ?>" name="<?php echo esc_attr($this->get_field_name('border_color')); ?>" type="text" value="<?php echo esc_attr($border_color); ?>" placeholder="#e2e8f0" data-default-color="#e2e8f0" />
            </p>
            <p>
                <label for="<?php echo esc_attr($field_custom_class); ?>"><?php esc_html_e('Additional CSS classes:', 'dragon-zap-affiliate'); ?></label>
                <input class="widefat" id="<?php echo esc_attr($field_custom_class); ?>" name="<?php echo esc_attr($this->get_field_name('custom_class')); ?>" type="text" value="<?php echo esc_attr($custom_class); ?>" />
                <span class="description"><?php esc_html_e('Separate multiple classes with spaces.', 'dragon-zap-affiliate'); ?></span>
            </p>
        </div>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['course_id'] = isset($new_instance['course_id']) ? absint($new_instance['course_id']) : 0;
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

