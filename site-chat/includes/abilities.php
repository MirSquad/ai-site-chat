<?php
/**
 * WordPress Abilities API integration for AI Site Chat.
 * Requires WP 6.9+ (Abilities API). Does nothing on older versions.
 *
 * Read abilities are always registered.
 * Write abilities are only registered when "Enable write abilities" is on
 * in Settings > AI Site Chat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bail silently on WordPress versions that don't have the Abilities API.
if ( ! function_exists( 'wp_register_ability' ) ) {
	return;
}

// -------------------------------------------------------------------------
// Register category.
// -------------------------------------------------------------------------
add_action( 'wp_abilities_api_categories_init', 'site_chat_register_ability_category' );
function site_chat_register_ability_category() {
	wp_register_ability_category( 'site-chat', array(
		'label'       => __( 'AI Site Chat', 'site-chat' ),
		'description' => __( 'Manage the AI Site Chat widget settings and conversation logs.', 'site-chat' ),
	) );
}

// -------------------------------------------------------------------------
// Register abilities.
// -------------------------------------------------------------------------
add_action( 'wp_abilities_api_init', 'site_chat_register_abilities' );
function site_chat_register_abilities() {

	// --- get-settings (always available) ---------------------------------

	wp_register_ability( 'site-chat/get-settings', array(
		'label'       => __( 'Get Settings', 'site-chat' ),
		'description' => __( 'Retrieve AI Site Chat settings. The API key is masked — only the last four characters are returned.', 'site-chat' ),
		'category'    => 'site-chat',
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'enabled'             => array( 'type' => 'boolean' ),
				'api_key'             => array( 'type' => 'string', 'description' => 'Masked API key (last 4 chars only).' ),
				'rate_limit'          => array( 'type' => 'integer', 'description' => 'Max chats per IP per day.' ),
				'custom_instructions' => array( 'type' => 'string' ),
				'post_types'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'log_enabled'         => array( 'type' => 'boolean' ),
				'contact_url'         => array( 'type' => 'string' ),
				'newsletter_url'      => array( 'type' => 'string' ),
			),
		),
		'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'execute_callback'    => function( $input = null ) {
			$api_key = (string) get_option( 'site_chat_api_key', '' );
			return array(
				'enabled'             => (bool) get_option( 'site_chat_enabled', 1 ),
				'api_key'             => $api_key ? '••••' . substr( $api_key, -4 ) : '',
				'rate_limit'          => (int) get_option( 'site_chat_rate_limit', 10 ),
				'custom_instructions' => (string) get_option( 'site_chat_custom_instructions', '' ),
				'post_types'          => (array) get_option( 'site_chat_post_types', array() ),
				'log_enabled'         => (bool) get_option( 'site_chat_log_enabled', 1 ),
				'contact_url'         => (string) get_option( 'site_chat_contact_url', '' ),
				'newsletter_url'      => (string) get_option( 'site_chat_newsletter_url', '' ),
			);
		},
		'meta' => array(
			'mcp'         => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		),
	) );

	// --- get-logs (always available) -------------------------------------

	wp_register_ability( 'site-chat/get-logs', array(
		'label'       => __( 'Get Chat Logs', 'site-chat' ),
		'description' => __( 'Retrieve recent AI Site Chat conversation logs, ordered newest first.', 'site-chat' ),
		'category'    => 'site-chat',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'per_page' => array(
					'type'        => 'integer',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
					'description' => 'Number of log entries to return.',
				),
			),
		),
		'output_schema' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'id'         => array( 'type' => 'integer' ),
					'question'   => array( 'type' => 'string' ),
					'answer'     => array( 'type' => 'string' ),
					'created_at' => array( 'type' => 'string' ),
				),
			),
		),
		'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'execute_callback'    => function( $input = null ) {
			global $wpdb;
			$per_page = isset( $input['per_page'] ) ? min( absint( $input['per_page'] ), 100 ) : 20;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, question, answer, created_at FROM {$wpdb->prefix}site_chat_log ORDER BY created_at DESC LIMIT %d",
					$per_page
				)
			);
			return $rows ?: array();
		},
		'meta' => array(
			'mcp'         => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		),
	) );

	// --- Write abilities (gated by option) --------------------------------

	if ( ! get_option( 'site_chat_write_abilities', false ) ) {
		return;
	}

	wp_register_ability( 'site-chat/update-settings', array(
		'label'       => __( 'Update Settings', 'site-chat' ),
		'description' => __( 'Update one or more AI Site Chat settings. Pass only the fields you want to change. The API key cannot be changed via this ability — use the settings page for that.', 'site-chat' ),
		'category'    => 'site-chat',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'enabled'             => array( 'type' => 'boolean',  'description' => 'Enable or disable the chat widget.' ),
				'rate_limit'          => array( 'type' => 'integer',  'minimum' => 1, 'maximum' => 1000, 'description' => 'Max chats per IP per day.' ),
				'custom_instructions' => array( 'type' => 'string',   'description' => 'Custom system prompt instructions. Max 2000 characters.' ),
				'log_enabled'         => array( 'type' => 'boolean',  'description' => 'Enable or disable conversation logging.' ),
				'contact_url'         => array( 'type' => 'string',   'description' => 'URL for the Contact follow-up CTA.' ),
				'newsletter_url'      => array( 'type' => 'string',   'description' => 'URL for the Newsletter follow-up CTA.' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'message' => array( 'type' => 'string' ),
			),
		),
		'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'execute_callback'    => function( $input = null ) {
			$updated = array();

			if ( isset( $input['enabled'] ) ) {
				update_option( 'site_chat_enabled', (bool) $input['enabled'] );
				$updated[] = 'enabled';
			}
			if ( isset( $input['rate_limit'] ) ) {
				update_option( 'site_chat_rate_limit', absint( $input['rate_limit'] ) );
				$updated[] = 'rate_limit';
			}
			if ( isset( $input['custom_instructions'] ) ) {
				update_option( 'site_chat_custom_instructions', mb_substr( sanitize_textarea_field( $input['custom_instructions'] ), 0, 2000 ) );
				$updated[] = 'custom_instructions';
			}
			if ( isset( $input['log_enabled'] ) ) {
				update_option( 'site_chat_log_enabled', (bool) $input['log_enabled'] );
				$updated[] = 'log_enabled';
			}
			if ( isset( $input['contact_url'] ) ) {
				update_option( 'site_chat_contact_url', esc_url_raw( mb_substr( $input['contact_url'], 0, 500 ) ) );
				$updated[] = 'contact_url';
			}
			if ( isset( $input['newsletter_url'] ) ) {
				update_option( 'site_chat_newsletter_url', esc_url_raw( mb_substr( $input['newsletter_url'], 0, 500 ) ) );
				$updated[] = 'newsletter_url';
			}

			return array(
				'success' => true,
				'updated' => $updated,
				'message' => empty( $updated )
					? __( 'No fields provided — nothing was changed.', 'site-chat' )
					: sprintf(
						/* translators: %s: comma-separated list of updated field names */
						__( 'Updated: %s', 'site-chat' ),
						implode( ', ', $updated )
					),
			);
		},
		'meta' => array(
			'mcp'         => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
		),
	) );
}
