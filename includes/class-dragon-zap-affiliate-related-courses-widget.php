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
            ]
        );
    }

    public function widget($args, $instance)
    {
        if (! is_singular('post')) {
            return;
        }

        $post_id = absint(get_the_ID());

        if ($post_id <= 0) {
            return;
        }

        $plugin = Dragon_Zap_Affiliate::instance();

        if (! $plugin instanceof Dragon_Zap_Affiliate) {
            return;
        }

        $title = isset($instance['title']) ? (string) $instance['title'] : __('Recommended Courses', 'dragon-zap-affiliate');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $markup = $plugin->get_related_courses_markup($post_id, [
            'title' => '',
            'context' => 'widget',
        ]);

        if ($markup === '') {
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
        $field_id = $this->get_field_id('title');
        $field_name = $this->get_field_name('title');
        ?>
        <p>
            <label for="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Title:', 'dragon-zap-affiliate'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';

        return $instance;
    }
}
