<?php
/**
 * Plugin Name: Dragon Zap Affiliate
 * Description: Integrates WordPress with the Dragon Zap Affiliate API.
 * Version: 0.1.0
 * Author: Dragon Zap
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-dragon-zap-affiliate.php';

Dragon_Zap_Affiliate::get_instance(__FILE__);
