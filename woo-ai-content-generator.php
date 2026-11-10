<?php
/**
 * Plugin Name: Woo AI Content Generator
 * Description: Draft product descriptions, short descriptions, and SEO suggestions with a configurable AI API.
 * Version: 1.0.0
 * Author: Previn Kalisetty Appadu
 * Author URI: https://previn.me
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Update URI: https://github.com/previnus/Woo-AI-Content
 * Text Domain: woo-ai-content-generator
 * License: GPL-2.0-or-later
 */

namespace Woo_AI_Content;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';
const SETTINGS = 'woo_ai_content_settings';
const COOLDOWN = 'woo_ai_content_next_request';
const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
const DEFAULT_MODEL = 'gpt-4.1-mini';

add_action( 'plugins_loaded', static function () {
	if ( PHP_VERSION_ID < 80300 || ! is_admin() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/provider.php';
	require_once __DIR__ . '/includes/admin.php';
} );
