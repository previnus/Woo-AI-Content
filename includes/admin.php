<?php

namespace Woo_AI_Content;

defined( 'ABSPATH' ) || exit;

const SETTINGS_PAGE = 'woo-ai-content-generator';
const GENERATE_ACTION = 'woo_ai_content_generate';

add_action( 'admin_menu', __NAMESPACE__ . '\\add_settings_page', 99 );
add_action( 'admin_post_woo_ai_content_save', __NAMESPACE__ . '\\save_settings' );
add_action( 'add_meta_boxes_product', __NAMESPACE__ . '\\add_product_panel' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_product_script' );
add_action( 'wp_ajax_' . GENERATE_ACTION, __NAMESPACE__ . '\\generate_ajax' );

function saved_settings(): array {
	return wp_parse_args( get_option( SETTINGS, array() ), array(
		'endpoint' => DEFAULT_ENDPOINT,
		'model'    => DEFAULT_MODEL,
		'api_key'  => '',
	) );
}

function effective_settings(): array {
	$settings = saved_settings();
	if ( defined( 'WOO_AI_CONTENT_API_KEY' ) ) {
		$settings['api_key']  = (string) WOO_AI_CONTENT_API_KEY;
		$settings['endpoint'] = defined( 'WOO_AI_CONTENT_API_ENDPOINT' ) ? (string) WOO_AI_CONTENT_API_ENDPOINT : DEFAULT_ENDPOINT;
	}
	return $settings;
}

function settings_url(): string {
	return admin_url( 'admin.php?page=' . SETTINGS_PAGE );
}

function add_settings_page(): void {
	add_submenu_page( 'woocommerce', __( 'AI Content Generator', 'woo-ai-content-generator' ), __( 'AI Content Generator', 'woo-ai-content-generator' ), 'manage_options', SETTINGS_PAGE, __NAMESPACE__ . '\\render_settings' );
}

function post_value( string $key ): string {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}

function save_settings(): void {
	if ( ! current_user_can( 'manage_options' ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( esc_html__( 'You cannot change the AI settings.', 'woo-ai-content-generator' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'woo_ai_content_save' );
	$stored   = saved_settings();
	$locked   = defined( 'WOO_AI_CONTENT_API_KEY' );
	$endpoint = $locked ? $stored['endpoint'] : trim( post_value( 'endpoint' ) );
	$model    = trim( post_value( 'model' ) );
	$key      = $locked ? '' : trim( post_value( 'api_key' ) );
	$clear    = '1' === post_value( 'clear_key' );

	if ( ! $locked && ! endpoint_host( $endpoint ) ) {
		$status = 'endpoint';
	} elseif ( ! preg_match( '/\A[a-z0-9][a-z0-9_.:\/-]{0,199}\z/i', $model ) ) {
		$status = 'model';
	} elseif ( '' !== $key && ! valid_api_key( $key ) ) {
		$status = 'key';
	} else {
		if ( $locked || $clear || ( '' === $key && $endpoint !== $stored['endpoint'] ) ) {
			$key = '';
		} elseif ( '' === $key ) {
			$key = $stored['api_key'];
		}
		$updated = array( 'endpoint' => $endpoint, 'model' => $model, 'api_key' => $key );
		$saved   = $updated === $stored || update_option( SETTINGS, $updated, false );
		$status  = $saved ? 'saved' : 'failed';
	}

	wp_safe_redirect( add_query_arg( 'woo_ai_status', $status, settings_url() ) );
	exit;
}

function render_settings(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = effective_settings();
	$locked   = defined( 'WOO_AI_CONTENT_API_KEY' );
	$endpoint = endpoint_host( $settings['endpoint'] ) ? $settings['endpoint'] : '';
	$status   = isset( $_GET['woo_ai_status'] ) && is_string( $_GET['woo_ai_status'] ) ? sanitize_key( wp_unslash( $_GET['woo_ai_status'] ) ) : '';
	$messages = array(
		'saved'    => __( 'AI settings saved.', 'woo-ai-content-generator' ),
		'endpoint' => __( 'Enter a full HTTPS endpoint with a DNS hostname and no query string, credentials, or fragment.', 'woo-ai-content-generator' ),
		'model'    => __( 'Enter a valid model ID of up to 200 characters.', 'woo-ai-content-generator' ),
		'key'      => __( 'Enter an API key of up to 512 printable characters with no spaces.', 'woo-ai-content-generator' ),
		'failed'   => __( 'WordPress could not save the AI settings. Try again.', 'woo-ai-content-generator' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Woo AI Content Generator', 'woo-ai-content-generator' ); ?></h1>
		<p><?php esc_html_e( 'Connect an OpenAI-compatible Chat Completions API to generate draft copy from the product editor.', 'woo-ai-content-generator' ); ?></p>
		<?php if ( isset( $messages[ $status ] ) ) : ?>
			<div class="notice <?php echo 'saved' === $status ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $messages[ $status ] ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! function_exists( 'curl_init' ) ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Enable PHP cURL on the server to use the generator.', 'woo-ai-content-generator' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $locked && '' === $endpoint ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The endpoint configured in wp-config.php is invalid.', 'woo-ai-content-generator' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="woo_ai_content_save">
			<?php wp_nonce_field( 'woo_ai_content_save' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="woo-ai-endpoint"><?php esc_html_e( 'API endpoint', 'woo-ai-content-generator' ); ?></label></th>
					<td>
						<input type="url" name="endpoint" id="woo-ai-endpoint" class="regular-text code" maxlength="512" value="<?php echo esc_attr( $endpoint ); ?>" <?php disabled( $locked ); ?> required aria-describedby="woo-ai-endpoint-help">
						<p id="woo-ai-endpoint-help" class="description"><?php esc_html_e( 'Enter the full public HTTPS Chat Completions URL. Changing the endpoint clears the saved key unless you enter a new one.', 'woo-ai-content-generator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="woo-ai-model"><?php esc_html_e( 'Model ID', 'woo-ai-content-generator' ); ?></label></th>
					<td><input type="text" name="model" id="woo-ai-model" class="regular-text code" maxlength="200" value="<?php echo esc_attr( $settings['model'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="woo-ai-key"><?php esc_html_e( 'API key', 'woo-ai-content-generator' ); ?></label></th>
					<td>
						<input type="password" name="api_key" id="woo-ai-key" class="regular-text" autocomplete="new-password" maxlength="512" <?php disabled( $locked ); ?> aria-describedby="woo-ai-key-help">
						<p id="woo-ai-key-help" class="description">
							<?php if ( $locked ) : ?>
								<?php esc_html_e( 'The API key and endpoint are managed in wp-config.php.', 'woo-ai-content-generator' ); ?>
							<?php elseif ( '' !== $settings['api_key'] ) : ?>
								<?php esc_html_e( 'An API key is saved. Leave this field blank to keep it.', 'woo-ai-content-generator' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Enter the bearer key issued by your provider.', 'woo-ai-content-generator' ); ?>
							<?php endif; ?>
						</p>
						<?php if ( ! $locked && '' !== $settings['api_key'] ) : ?>
							<p><label><input type="checkbox" name="clear_key" value="1"> <?php esc_html_e( 'Remove the saved API key', 'woo-ai-content-generator' ); ?></label></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function add_product_panel( \WP_Post $post ): void {
	if ( current_user_can( 'edit_post', $post->ID ) ) {
		add_meta_box( 'woo-ai-content-panel', __( 'AI Content Generator', 'woo-ai-content-generator' ), __NAMESPACE__ . '\\render_product_panel', 'product', 'normal', 'default' );
	}
}

function enqueue_product_script( string $hook ): void {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_current_screen()?->post_type ) {
		wp_enqueue_script( 'woo-ai-content-editor', plugins_url( 'assets/editor.js', dirname( __DIR__ ) . '/woo-ai-content-generator.php' ), array(), VERSION, true );
	}
}

function render_product_panel( \WP_Post $post ): void {
	$settings = effective_settings();
	if ( ! endpoint_host( $settings['endpoint'] ) || ! valid_api_key( $settings['api_key'] ) || '' === $settings['model'] || ! function_exists( 'curl_init' ) ) {
		echo '<p>' . esc_html__( 'Configure the AI endpoint, model, and API key and enable PHP cURL to use the generator.', 'woo-ai-content-generator' ) . '</p>';
		if ( current_user_can( 'manage_options' ) ) {
			printf( '<p><a href="%s">%s</a></p>', esc_url( settings_url() ), esc_html__( 'Open AI settings', 'woo-ai-content-generator' ) );
		}
		return;
	}
	?>
	<div id="woo-ai-content" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( GENERATE_ACTION . '_' . $post->ID ) ); ?>"
		data-generating="<?php esc_attr_e( 'Generating suggestions…', 'woo-ai-content-generator' ); ?>"
		data-ready="<?php esc_attr_e( 'Suggestions are ready to review.', 'woo-ai-content-generator' ); ?>"
		data-failed="<?php esc_attr_e( 'The request could not be completed. Try again or reload the product editor.', 'woo-ai-content-generator' ); ?>"
		data-no-title="<?php esc_attr_e( 'Enter a product name before generating suggestions.', 'woo-ai-content-generator' ); ?>"
		data-confirm="<?php esc_attr_e( 'Replace the current text in this editor with the suggestion?', 'woo-ai-content-generator' ); ?>"
		data-applied="<?php esc_attr_e( 'Suggestion inserted. Save or update the product when ready.', 'woo-ai-content-generator' ); ?>"
		data-no-editor="<?php esc_attr_e( 'The product editor could not be found. Copy the suggestion into the field manually.', 'woo-ai-content-generator' ); ?>"
		data-copied="<?php esc_attr_e( 'Copied to the clipboard.', 'woo-ai-content-generator' ); ?>"
		data-copy-manually="<?php esc_attr_e( 'The text is selected. Use your device’s copy command.', 'woo-ai-content-generator' ); ?>">
		<p><?php esc_html_e( 'Generation sends the product name, current descriptions, saved categories and visible attributes, and your notes to the configured AI provider. Review the suggestions before using them.', 'woo-ai-content-generator' ); ?></p>
		<p><label for="woo-ai-notes"><?php esc_html_e( 'Product facts or writing preferences (optional)', 'woo-ai-content-generator' ); ?></label></p>
		<textarea id="woo-ai-notes" class="large-text" rows="3" maxlength="1500" placeholder="<?php esc_attr_e( 'Example: 350 ml capacity. Use a friendly tone for everyday shoppers.', 'woo-ai-content-generator' ); ?>"></textarea>
		<p><button type="button" class="button button-primary" id="woo-ai-generate"><?php esc_html_e( 'Generate suggestions', 'woo-ai-content-generator' ); ?></button></p>
		<p id="woo-ai-status" role="status" aria-live="polite" aria-atomic="true"></p>
		<div id="woo-ai-results" hidden>
			<h3><?php esc_html_e( 'Review suggestions', 'woo-ai-content-generator' ); ?></h3>
			<?php
			$fields = array(
				'description'       => array( __( 'Product description', 'woo-ai-content-generator' ), 6, 'content' ),
				'short_description' => array( __( 'Short description', 'woo-ai-content-generator' ), 3, 'excerpt' ),
				'seo_title'         => array( __( 'SEO title', 'woo-ai-content-generator' ), 1, '' ),
				'meta_description'  => array( __( 'Meta description', 'woo-ai-content-generator' ), 2, '' ),
				'keywords'          => array( __( 'Suggested keywords', 'woo-ai-content-generator' ), 2, '' ),
			);
			foreach ( $fields as $field => list( $label, $rows, $editor ) ) :
				?>
				<p><label for="woo-ai-<?php echo esc_attr( $field ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label></p>
				<textarea id="woo-ai-<?php echo esc_attr( $field ); ?>" class="large-text" rows="<?php echo esc_attr( (string) $rows ); ?>" readonly></textarea>
				<p>
					<?php if ( '' !== $editor ) : ?>
						<button type="button" class="button" data-apply="<?php echo esc_attr( $editor ); ?>" data-field="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( 'content' === $editor ? __( 'Insert product description', 'woo-ai-content-generator' ) : __( 'Insert short description', 'woo-ai-content-generator' ) ); ?></button>
					<?php else : ?>
						<button type="button" class="button" data-copy="<?php echo esc_attr( $field ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy %s', 'woo-ai-content-generator' ), $label ) ); ?>"><?php esc_html_e( 'Copy', 'woo-ai-content-generator' ); ?></button>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

function generate_ajax(): void {
	$id = filter_var( post_value( 'post_id' ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! $id || ! wp_verify_nonce( post_value( 'nonce' ), GENERATE_ACTION . '_' . $id ) ) {
		wp_send_json_error( array( 'message' => __( 'Reload the product editor before trying again.', 'woo-ai-content-generator' ) ), 403 );
	}
	$post = get_post( $id );
	if ( ! $post || 'product' !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to generate content for this product.', 'woo-ai-content-generator' ) ), 403 );
	}

	$title = clean_text( substr( post_value( 'title' ), 0, 1600 ), 250 );
	if ( '' === $title ) {
		wp_send_json_error( array( 'message' => __( 'Enter a product name before generating suggestions.', 'woo-ai-content-generator' ) ), 400 );
	}
	if ( ! claim_request_slot() ) {
		header( 'Retry-After: 10' );
		wp_send_json_error( array( 'message' => __( 'Generation is temporarily limited. Wait 10 seconds and try again.', 'woo-ai-content-generator' ) ), 429 );
	}

	$context = array(
		'name'              => $title,
		'description'       => clean_text( strip_shortcodes( substr( post_value( 'description' ), 0, 16000 ) ), 4000 ),
		'short_description' => clean_text( strip_shortcodes( substr( post_value( 'short_description' ), 0, 6000 ) ), 1500 ),
		'notes'             => clean_text( substr( post_value( 'notes' ), 0, 6000 ), 1500 ),
		'locale'            => get_locale(),
		'categories'        => array(),
		'attributes'        => array(),
	);
	$categories = get_the_terms( $id, 'product_cat' );
	if ( is_array( $categories ) ) {
		foreach ( array_slice( $categories, 0, 15 ) as $category ) {
			$context['categories'][] = clean_text( $category->name, 100 );
		}
	}
	$product = wc_get_product( $id );
	if ( $product ) {
		foreach ( array_slice( $product->get_attributes( 'edit' ), 0, 15 ) as $attribute ) {
			if ( ! $attribute->get_visible() ) {
				continue;
			}
			$name   = clean_text( wc_attribute_label( $attribute->get_name() ), 100 );
			$values = array();
			foreach ( array_slice( $attribute->get_options(), 0, 10 ) as $option ) {
				if ( $attribute->is_taxonomy() ) {
					$term = get_term( (int) $option, $attribute->get_name() );
					$option = $term instanceof \WP_Term ? $term->name : '';
				}
				$values[] = clean_text( (string) $option, 100 );
			}
			$context['attributes'][] = array( 'name' => $name, 'values' => $values );
		}
	}

	$result = generate_content( effective_settings(), $context );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}
	wp_send_json_success( $result );
}

/** Reserve the site's next slot atomically, including concurrent requests on separate workers. */
function claim_request_slot(): bool {
	global $wpdb;
	$now      = time();
	$previous = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, COOLDOWN ) );
	if ( null === $previous ) {
		return 1 === $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %d, 'no')",
			$wpdb->options,
			COOLDOWN,
			$now + 10
		) );
	}
	if ( (int) $previous > $now ) {
		return false;
	}
	return 1 === $wpdb->update(
		$wpdb->options,
		array( 'option_value' => (string) ( $now + 10 ) ),
		array( 'option_name' => COOLDOWN, 'option_value' => $previous )
	);
}
