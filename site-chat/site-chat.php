<?php
/**
 * Plugin Name:       AI Site Chat
 * Plugin URI:        https://miriamschwab.me/plugins/site-chat
 * Description:       Adds an AI-powered floating chat widget to your site. Visitors can ask questions and get answers based on your published content, powered by Claude.
 * Version:           2.4.0
 * Author:            Miriam Schwab
 * Author URI:        https://miriamschwab.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       site-chat
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SITE_CHAT_VERSION', '2.4.0' );
define( 'SITE_CHAT_MAX_CONTEXT_CHARS', 200000 );
define( 'SITE_CHAT_MAX_POST_CONTENT_CHARS', 1500 );

// ---------------------------------------------------------------------------
// Activation / deactivation
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, 'site_chat_activate' );
register_deactivation_hook( __FILE__, 'site_chat_deactivate' );

function site_chat_deactivate() {
	delete_transient( 'site_chat_context_cache' );
}

function site_chat_activate() {
	global $wpdb;
	$table   = $wpdb->prefix . 'site_chat_log';
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		question text NOT NULL,
		answer text NOT NULL,
		ip_hash varchar(32) NOT NULL DEFAULT '',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// ---------------------------------------------------------------------------
// i18n
// ---------------------------------------------------------------------------

add_action( 'init', function () {
	load_plugin_textdomain( 'site-chat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'AI Site Chat', 'site-chat' ),
		__( 'AI Site Chat', 'site-chat' ),
		'manage_options',
		'site-chat',
		'site_chat_settings_page'
	);
	add_options_page(
		__( 'AI Site Chat — Chat Log', 'site-chat' ),
		__( 'AI Site Chat Log', 'site-chat' ),
		'manage_options',
		'site-chat-log',
		'site_chat_log_page'
	);
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=site-chat' ) ) . '">' . esc_html__( 'Settings', 'site-chat' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

add_filter( 'plugin_row_meta', function( $links, $file ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}
	$links[] = '<a href="' . esc_url( 'https://miriamschwab.me/plugins/site-chat' ) . '" target="_blank">' . esc_html__( 'Visit plugin site', 'site-chat' ) . '</a>';
	return $links;
}, 10, 2 );

// Ensure DB table exists — runs dbDelta once per version bump, covers installs that
// bypassed the activation hook (e.g. manual file replacement during updates).
add_action( 'admin_init', function () {
	if ( get_option( 'site_chat_db_version' ) !== SITE_CHAT_VERSION ) {
		site_chat_activate();
		update_option( 'site_chat_db_version', SITE_CHAT_VERSION );
	}
} );

add_action( 'admin_init', function () {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	wp_add_privacy_policy_content(
		'AI Site Chat',
		wp_kses_post(
			sprintf(
				/* translators: %s: link to Anthropic privacy policy */
				__( 'When a visitor submits a question via the AI Site Chat widget, the question and the AI-generated answer may be stored in your site\'s database if the "Log conversations" setting is enabled. Logs are visible only to site administrators and are never shared with third parties. The visitor\'s IP address is stored only as a one-way hash (MD5) for rate-limiting purposes. Questions and site content are sent to the Anthropic API to generate answers; see %s for details.', 'site-chat' ),
				'<a href="https://www.anthropic.com/privacy" target="_blank" rel="noopener">Anthropic\'s privacy policy</a>'
			)
		)
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'site_chat', 'site_chat_api_key', [
		'sanitize_callback' => function ( $val ) {
			return mb_substr( sanitize_text_field( $val ), 0, 200 );
		},
	] );
	register_setting( 'site_chat', 'site_chat_enabled', [
		'sanitize_callback' => 'rest_sanitize_boolean',
	] );
	register_setting( 'site_chat', 'site_chat_rate_limit', [
		'sanitize_callback' => 'absint',
	] );
	register_setting( 'site_chat', 'site_chat_custom_instructions', [
		'sanitize_callback' => function ( $val ) {
			return mb_substr( sanitize_textarea_field( $val ), 0, 2000 );
		},
	] );
	register_setting( 'site_chat', 'site_chat_post_types', [
		'sanitize_callback' => function ( $val ) {
			if ( ! is_array( $val ) ) {
				return [];
			}
			return array_values( array_map( 'sanitize_key', $val ) );
		},
	] );
	register_setting( 'site_chat', 'site_chat_log_enabled', [
		'sanitize_callback' => 'rest_sanitize_boolean',
	] );
	register_setting( 'site_chat', 'site_chat_contact_url', [
		'sanitize_callback' => function ( $val ) {
			return esc_url_raw( mb_substr( $val, 0, 500 ) );
		},
	] );
	register_setting( 'site_chat', 'site_chat_newsletter_url', [
		'sanitize_callback' => function ( $val ) {
			return esc_url_raw( mb_substr( $val, 0, 500 ) );
		},
	] );
} );

/**
 * Post type slugs that are never useful as chat context.
 * Used to build the default selection — users can override via the settings page.
 */
function site_chat_excluded_by_default() {
	return [
		'attachment', 'elementor_library', 'e-floating-buttons', 'e-landing-page',
		'acf-taxonomy', 'acf-post-type', 'acf-field-group', 'acf-field',
		'angie_snippet', 'wp_block', 'wp_template', 'wp_template_part',
		'wp_navigation', 'wp_font_face', 'wp_font_family',
	];
}

/**
 * Returns the post types that should be indexed, respecting the saved setting.
 * On first use (no saved value), returns all show_ui types minus known non-content types.
 */
function site_chat_active_post_types() {
	$saved = get_option( 'site_chat_post_types', null );
	if ( null !== $saved ) {
		return (array) $saved;
	}
	$all = array_merge(
		[ 'post', 'page' ],
		get_post_types( [ 'show_ui' => true, '_builtin' => false ], 'names' )
	);
	return array_values( array_diff( $all, site_chat_excluded_by_default() ) );
}

function site_chat_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Site Chat', 'site-chat' ); ?></h1>
		<p>
			<?php esc_html_e( 'Adds a floating AI chat widget that answers visitor questions based on your published content.', 'site-chat' ); ?>
			<?php printf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=site-chat-log' ) ), esc_html__( 'View Chat Log →', 'site-chat' ) ); ?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'site_chat' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable chat widget', 'site-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="site_chat_enabled" value="1"
								<?php checked( 1, get_option( 'site_chat_enabled', 1 ) ); ?> />
							<?php esc_html_e( 'Show chat widget on the frontend', 'site-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Anthropic API Key', 'site-chat' ); ?></th>
					<td>
						<input type="password" name="site_chat_api_key"
							value="<?php echo esc_attr( get_option( 'site_chat_api_key', '' ) ); ?>"
							class="regular-text" autocomplete="off" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: URL to Anthropic console */
								esc_html__( 'Your Anthropic API key. Get one at %s.', 'site-chat' ),
								'<a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rate Limit', 'site-chat' ); ?></th>
					<td>
						<input type="number" name="site_chat_rate_limit"
							value="<?php echo absint( get_option( 'site_chat_rate_limit', 10 ) ); ?>"
							min="1" max="200" style="width:80px" />
						<label> <?php esc_html_e( 'requests per IP per hour', 'site-chat' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Content to Index', 'site-chat' ); ?></th>
					<td>
						<?php
						$all_types   = array_merge(
							[ 'post', 'page' ],
							get_post_types( [ 'show_ui' => true, '_builtin' => false ], 'names' )
						);
						$all_types   = array_values( array_diff( $all_types, [ 'attachment' ] ) );
						$active      = site_chat_active_post_types();
						foreach ( $all_types as $slug ) {
							$obj   = get_post_type_object( $slug );
							$label = $obj ? $obj->labels->name : $slug;
							?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox"
									name="site_chat_post_types[]"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( in_array( $slug, $active, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
								<span style="color:#888;font-size:12px;">(<?php echo esc_html( $slug ); ?>)</span>
							</label>
							<?php
						}
						?>
						<p class="description"><?php esc_html_e( 'Choose which post types the AI can draw on when answering questions. Uncheck framework types like Elementor templates or ACF configuration — they consume context budget without adding useful content.', 'site-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Custom Instructions', 'site-chat' ); ?></th>
					<td>
						<textarea name="site_chat_custom_instructions" class="large-text" rows="4"
							><?php echo esc_textarea( get_option( 'site_chat_custom_instructions', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Optional. Additional instructions for the AI — tone, persona, topics to emphasize, or anything it should know beyond the site content. Added to the end of the system prompt.', 'site-chat' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log conversations', 'site-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="site_chat_log_enabled" value="1"
								<?php checked( 1, get_option( 'site_chat_log_enabled', 1 ) ); ?> />
							<?php esc_html_e( 'Save visitor questions and AI answers to the chat log', 'site-chat' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Logs are stored in your database and visible only to admins. Disable if you prefer not to store visitor questions.', 'site-chat' ); ?>
							<a href="<?php echo esc_url( admin_url( 'options-general.php?page=site-chat-log' ) ); ?>"><?php esc_html_e( 'View Chat Log →', 'site-chat' ); ?></a>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Follow-up: Contact URL', 'site-chat' ); ?></th>
					<td>
						<input type="url" name="site_chat_contact_url"
							value="<?php echo esc_attr( get_option( 'site_chat_contact_url', '' ) ); ?>"
							class="regular-text" placeholder="https://example.com/contact/" />
						<p class="description"><?php esc_html_e( 'Optional. When a visitor says they\'re done chatting, a "Contact us" button will appear linking here.', 'site-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Follow-up: Newsletter URL', 'site-chat' ); ?></th>
					<td>
						<input type="url" name="site_chat_newsletter_url"
							value="<?php echo esc_attr( get_option( 'site_chat_newsletter_url', '' ) ); ?>"
							class="regular-text" placeholder="https://example.com/newsletter/" />
						<p class="description"><?php esc_html_e( 'Optional. When a visitor says they\'re done chatting, a "Subscribe to newsletter" button will appear linking here.', 'site-chat' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<hr />
		<h2><?php esc_html_e( 'Debug: Content Index', 'site-chat' ); ?></h2>
		<p><?php esc_html_e( 'Shows the exact content being sent to the AI as context. Use this to verify your posts and custom fields are being indexed correctly.', 'site-chat' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'site_chat_debug', 'site_chat_debug_nonce' ); ?>
			<input type="hidden" name="site_chat_action" value="debug_context" />
			<?php submit_button( __( 'View Content Index', 'site-chat' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
		if (
			isset( $_POST['site_chat_action'] ) &&
			'debug_context' === $_POST['site_chat_action'] &&
			isset( $_POST['site_chat_debug_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['site_chat_debug_nonce'] ) ), 'site_chat_debug' )
		) {
			$context    = site_chat_get_context();
			$char_count = mb_strlen( $context );
			$post_types = site_chat_active_post_types();
			echo '<p><strong>';
			printf(
				/* translators: 1: character count, 2: limit */
				esc_html__( 'Context size: %1$s characters (limit: %2$s)', 'site-chat' ),
				esc_html( number_format( $char_count ) ),
				esc_html( number_format( SITE_CHAT_MAX_CONTEXT_CHARS ) )
			);
			echo '</strong></p>';
			echo '<p>' . esc_html__( 'Post types being indexed: ', 'site-chat' ) . '<code>' . esc_html( implode( ', ', $post_types ) ) . '</code></p>';
			echo '<textarea readonly style="width:100%;height:400px;font-family:monospace;font-size:12px;">' . esc_textarea( $context ) . '</textarea>';
		}
		?>
		<hr />
		<h2><?php esc_html_e( 'Third-Party Service Disclosure', 'site-chat' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: link to Anthropic privacy policy */
				esc_html__( 'When a visitor asks a question, this plugin sends your site\'s published content and the visitor\'s question to the Anthropic API. By activating AI Site Chat you agree to %s.', 'site-chat' ),
				'<a href="https://www.anthropic.com/privacy" target="_blank" rel="noopener">' . esc_html__( "Anthropic's privacy policy", 'site-chat' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}

function site_chat_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table   = $wpdb->prefix . 'site_chat_log';
	$per_page = 50;
	$page    = max( 1, isset( $_GET['logpage'] ) ? absint( $_GET['logpage'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
	$offset  = ( $page - 1 ) * $per_page;
	$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Site Chat — Chat Log', 'site-chat' ); ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=site-chat' ) ); ?>"><?php esc_html_e( '← Settings', 'site-chat' ); ?></a></p>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'No conversations logged yet.', 'site-chat' ); ?></p>
		<?php else : ?>
			<p><?php printf( esc_html__( '%d conversations logged.', 'site-chat' ), $total ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:140px"><?php esc_html_e( 'Date', 'site-chat' ); ?></th>
						<th><?php esc_html_e( 'Question', 'site-chat' ); ?></th>
						<th><?php esc_html_e( 'Answer', 'site-chat' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td style="white-space:nowrap"><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $row->question ); ?></td>
						<td style="font-size:12px"><?php echo esc_html( mb_substr( $row->answer, 0, 300 ) ); ?><?php echo mb_strlen( $row->answer ) > 300 ? '…' : ''; ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$total_pages = ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div style="margin-top:12px">';
				for ( $i = 1; $i <= $total_pages; $i++ ) {
					$url = add_query_arg( [ 'page' => 'site-chat-log', 'logpage' => $i ], admin_url( 'options-general.php' ) );
					if ( $i === $page ) {
						echo '<strong style="margin-right:6px">' . absint( $i ) . '</strong>';
					} else {
						echo '<a href="' . esc_url( $url ) . '" style="margin-right:6px">' . absint( $i ) . '</a>';
					}
				}
				echo '</div>';
			}
			?>
		<?php endif; ?>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// Content gathering
// ---------------------------------------------------------------------------

/**
 * Recursively extracts plain text from Elementor's _elementor_data JSON.
 * Elementor stores page content as JSON in post meta rather than post_content,
 * so post_content is often empty for Elementor-built pages.
 */
function site_chat_extract_elementor_text( $post_id ) {
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $raw ) {
		return '';
	}
	$elements = json_decode( $raw, true );
	if ( ! is_array( $elements ) ) {
		return '';
	}
	$text = site_chat_walk_elementor( $elements );
	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

function site_chat_walk_elementor( array $elements ) {
	// Common Elementor widget settings keys that contain visible text.
	$text_keys = [ 'editor', 'title', 'description', 'caption', 'text', 'html', 'content', 'heading' ];
	$text      = '';
	foreach ( $elements as $el ) {
		if ( ! empty( $el['settings'] ) && is_array( $el['settings'] ) ) {
			foreach ( $text_keys as $key ) {
				if ( ! empty( $el['settings'][ $key ] ) && is_string( $el['settings'][ $key ] ) ) {
					$text .= ' ' . wp_strip_all_tags( $el['settings'][ $key ] );
				}
			}
		}
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$text .= site_chat_walk_elementor( $el['elements'] );
		}
	}
	return $text;
}

function site_chat_get_context() {
	$cached = get_transient( 'site_chat_context_cache' );
	if ( false !== $cached ) {
		return $cached;
	}

	$parts      = [];
	$post_types = site_chat_active_post_types();

	foreach ( $post_types as $type ) {
		$type_obj   = get_post_type_object( $type );
		$type_label = $type_obj ? $type_obj->labels->singular_name : $type;

		/**
		 * Filters the maximum posts fetched per post type when building the AI context index.
		 * Set to a lower number on large sites to prevent memory exhaustion or timeouts.
		 * Default -1 fetches all published posts of that type.
		 *
		 * @param int    $limit     Posts per page. -1 for all.
		 * @param string $post_type The post type being queried.
		 */
		$per_type_limit = (int) apply_filters( 'site_chat_context_posts_limit', -1, $type );

		$posts = get_posts( [
			'post_type'        => $type,
			'post_status'      => 'publish',
			'posts_per_page'   => $per_type_limit,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		] );

		if ( empty( $posts ) ) {
			continue;
		}

		foreach ( $posts as $post ) {
			$entry = '[' . $type_label . '] ' . $post->post_title;

			if ( 'post' === $type ) {
				$entry .= ' (' . get_the_date( 'F j, Y', $post ) . ')';
			}

			$entry .= "\n";

			$permalink = get_permalink( $post->ID );
			if ( $permalink ) {
				$entry .= 'URL: ' . $permalink . "\n";
			}

			$content = wp_strip_all_tags( $post->post_content );
			$content = trim( preg_replace( '/\s+/', ' ', $content ) );

			// Elementor stores content as JSON in post meta rather than post_content.
			// Use the Elementor-extracted text when it is richer than post_content.
			$elementor_text = site_chat_extract_elementor_text( $post->ID );
			if ( strlen( $elementor_text ) > strlen( $content ) ) {
				$content = $elementor_text;
			}

			// Cap per-post content so no single post consumes the entire context budget.
			$content = mb_substr( $content, 0, SITE_CHAT_MAX_POST_CONTENT_CHARS );

			if ( $content ) {
				$entry .= 'Content: ' . $content . "\n";
			}

			if ( $post->post_excerpt ) {
				$entry .= 'Excerpt: ' . trim( $post->post_excerpt ) . "\n";
			}

			if ( 'post' === $type ) {
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$entry .= 'Categories: ' . implode( ', ', wp_list_pluck( $cats, 'name' ) ) . "\n";
				}
				$tags = get_the_tags( $post->ID );
				if ( $tags ) {
					$entry .= 'Tags: ' . implode( ', ', wp_list_pluck( $tags, 'name' ) ) . "\n";
				}
			}

			// ACF fields — include strings and flat arrays (e.g. multi-select, checkbox).
			if ( function_exists( 'get_fields' ) ) {
				$acf_fields = get_fields( $post->ID );
				if ( $acf_fields && is_array( $acf_fields ) ) {
					foreach ( $acf_fields as $field_key => $field_value ) {
						$field_label = ucwords( str_replace( [ '_', '-' ], ' ', $field_key ) );
						if ( is_string( $field_value ) && '' !== $field_value ) {
							$entry .= $field_label . ': ' . $field_value . "\n";
						} elseif ( is_array( $field_value ) && ! empty( $field_value ) ) {
							// Flat array of scalars (checkbox, multi-select, etc.).
							$flat = array_filter( $field_value, 'is_scalar' );
							if ( count( $flat ) === count( $field_value ) ) {
								$entry .= $field_label . ': ' . implode( ', ', $flat ) . "\n";
							}
						}
					}
				}
			}

			$parts[] = $entry;
		}
	}

	$context = implode( "\n---\n", $parts );

	// Safety cap: prevent oversized payloads if content grows significantly.
	$context = mb_substr( $context, 0, SITE_CHAT_MAX_CONTEXT_CHARS );

	set_transient( 'site_chat_context_cache', $context, 12 * HOUR_IN_SECONDS );

	return $context;
}

// Clear context cache whenever a post is saved so new content appears immediately.
add_action( 'save_post', function ( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	delete_transient( 'site_chat_context_cache' );
} );

// ---------------------------------------------------------------------------
// REST endpoint
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
	register_rest_route( 'site-chat/v1', '/ask', [
		'methods'             => 'POST',
		'callback'            => 'site_chat_handle_ask',
		'permission_callback' => '__return_true',
		'args'                => [
			'question' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $val ) {
					return is_string( $val ) && strlen( trim( $val ) ) >= 1 && strlen( $val ) <= 500;
				},
			],
		],
	] );
} );

function site_chat_handle_ask( WP_REST_Request $request ) {

	// Custom nonce passed in the request body (not X-WP-Nonce header) so WordPress does
	// not attempt cookie authentication via rest_cookie_check_errors(), which would return
	// "Cookie check failed" when a cached page serves a stale wp_rest nonce to a logged-in
	// user. Rate limiting below is the primary abuse defence.
	$nonce = sanitize_text_field( (string) $request->get_param( 'nonce' ) );
	if ( ! wp_verify_nonce( $nonce, 'site_chat_ask' ) ) {
		return new WP_Error( 'forbidden', __( 'Invalid request.', 'site-chat' ), [ 'status' => 403 ] );
	}

	if ( ! get_option( 'site_chat_enabled', 1 ) ) {
		return new WP_Error( 'disabled', __( 'Chat is currently disabled.', 'site-chat' ), [ 'status' => 503 ] );
	}

	$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$rate_key = 'site_chat_rl_' . md5( $ip );
	$limit    = max( 1, (int) get_option( 'site_chat_rate_limit', 10 ) );
	$count    = (int) get_transient( $rate_key );

	if ( $count >= $limit ) {
		// Alert admin once per IP per rate-limit period.
		$alert_key = 'site_chat_rl_alerted_' . md5( $ip );
		if ( ! get_transient( $alert_key ) ) {
			set_transient( $alert_key, 1, HOUR_IN_SECONDS );
			wp_mail(
				get_option( 'admin_email' ),
				sprintf( '[%s] AI Site Chat: rate limit reached', get_bloginfo( 'name' ) ),
				sprintf( "The AI Site Chat rate limit was reached on %s.\n\nLimit: %d requests/hour\nIP hash: %s", home_url(), $limit, md5( $ip ) )
			);
		}
		return new WP_Error( 'rate_limited', __( 'Too many requests. Please try again later.', 'site-chat' ), [ 'status' => 429 ] );
	}

	set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

	$api_key = get_option( 'site_chat_api_key', '' );
	if ( ! $api_key ) {
		return new WP_Error( 'misconfigured', __( 'Chat is not configured yet.', 'site-chat' ), [ 'status' => 503 ] );
	}

	$question    = $request->get_param( 'question' );
	$context     = site_chat_get_context();
	$site_name   = get_bloginfo( 'name' );
	$site_desc   = get_bloginfo( 'description' );
	$custom      = (string) get_option( 'site_chat_custom_instructions', '' );

	$system = sprintf( 'You are a helpful assistant for the website "%s".', $site_name );

	if ( $site_desc ) {
		$system .= ' ' . sprintf( 'The site is described as: %s.', $site_desc );
	}

	$system .= ' Answer questions about the site and its content.'
		. ' Be conversational, concise, and direct. You may use Markdown formatting (bold, bullet lists, links).'
		. ' Only answer based on the site content provided below.'
		. ' If a question is not covered by the site content, say so briefly.'
		. ' Never make things up.'
		. ' When linking to content, always use the exact URL listed for that specific post or page in the site index below, formatted as a Markdown link [title](URL).'
		. ' Never link to a general section or archive page when the specific post URL is available.'
		. ' Never link to external sites.'
		. ' Always end your response with exactly this sentence on its own line: "Can I help you with anything else?"';

	if ( $custom ) {
		$system .= "\n\n" . $custom;
	}

	$system .= "\n\nSITE CONTENT:\n\n" . $context;

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 30,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( [
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 512,
			'system'     => $system,
			'messages'   => [
				[ 'role' => 'user', 'content' => $question ],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'upstream_error', __( 'Could not reach AI service.', 'site-chat' ), [ 'status' => 502 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! empty( $body['error'] ) ) {
		return new WP_Error( 'upstream_error', __( 'AI service returned an error.', 'site-chat' ), [ 'status' => 502 ] );
	}

	if ( empty( $body['content'][0]['text'] ) ) {
		return new WP_Error( 'upstream_error', __( 'Unexpected response from AI service.', 'site-chat' ), [ 'status' => 502 ] );
	}

	$answer = $body['content'][0]['text'];

	if ( get_option( 'site_chat_log_enabled', 1 ) ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'site_chat_log',
			[
				'question'   => mb_substr( $question, 0, 1000 ),
				'answer'     => mb_substr( $answer, 0, 5000 ),
				'ip_hash'    => md5( $ip ),
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);
	}

	return rest_ensure_response( [ 'answer' => $answer ] );
}

// ---------------------------------------------------------------------------
// Frontend widget — inline output via wp_footer (bypasses CDN asset caching)
// ---------------------------------------------------------------------------

add_action( 'wp_footer', function () {
	if ( ! get_option( 'site_chat_enabled', 1 ) ) {
		return;
	}

	$nonce            = wp_create_nonce( 'site_chat_ask' );
	$rest_url         = esc_url( rest_url( 'site-chat/v1/ask' ) );
	$welcome_text     = esc_js( __( 'Ask me anything about this site', 'site-chat' ) );
	$contact_url      = esc_js( get_option( 'site_chat_contact_url', '' ) );
	$newsletter_url   = esc_js( get_option( 'site_chat_newsletter_url', '' ) );
	$label_contact    = esc_js( __( 'Contact us', 'site-chat' ) );
	$label_newsletter = esc_js( __( 'Subscribe to newsletter', 'site-chat' ) );
	?>

<div id="sc-widget" role="complementary" aria-label="<?php esc_attr_e( 'Chat with this site', 'site-chat' ); ?>">

	<button id="sc-toggle" aria-label="<?php esc_attr_e( 'Open site chat', 'site-chat' ); ?>" aria-expanded="false" aria-controls="sc-panel">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
		<span id="sc-toggle-label"><?php esc_html_e( 'Ask about this site', 'site-chat' ); ?></span>
	</button>

	<div id="sc-panel" hidden role="dialog" aria-label="<?php esc_attr_e( 'Site chat', 'site-chat' ); ?>" aria-modal="false">
		<div id="sc-header">
			<span><?php esc_html_e( '// ask me anything', 'site-chat' ); ?></span>
			<button id="sc-close" aria-label="<?php esc_attr_e( 'Close chat', 'site-chat' ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
			</button>
		</div>
		<div id="sc-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Chat messages', 'site-chat' ); ?>"></div>
		<div id="sc-input-row">
			<input id="sc-input" type="text"
				placeholder="<?php esc_attr_e( 'Ask a question...', 'site-chat' ); ?>"
				maxlength="500"
				aria-label="<?php esc_attr_e( 'Your question', 'site-chat' ); ?>"
				autocomplete="off" />
			<button id="sc-send" aria-label="<?php esc_attr_e( 'Send question', 'site-chat' ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><line x1="22" y1="2" x2="11" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
			</button>
		</div>
	</div>

</div>

<style>
#sc-widget {
	position: fixed;
	bottom: 24px;
	right: 24px;
	z-index: 9990;
	font-family: 'IBM Plex Mono', monospace;
	font-size: 13px;
	line-height: 1.5;
}

/* ---- Toggle button ---- */
#sc-toggle {
	display: flex;
	align-items: center;
	gap: 9px;
	background: #0A0A0A;
	color: #FAFAF8;
	border: none;
	border-radius: 40px;
	padding: 11px 18px 11px 14px;
	cursor: pointer;
	font-family: 'IBM Plex Mono', monospace;
	font-size: 12px;
	letter-spacing: 0.02em;
	box-shadow: 0 4px 20px rgba(0,0,0,0.2);
	transition: background 0.18s, transform 0.12s;
	white-space: nowrap;
}
#sc-toggle:hover  { background: #B52B00; transform: translateY(-1px); }
#sc-toggle:focus-visible { outline: 2px solid #B52B00; outline-offset: 2px; }

[data-theme="dark"] #sc-toggle              { background: #F0F0EE; color: #0A0A0A; }
[data-theme="dark"] #sc-toggle:hover        { background: #FF8C5A; color: #0A0A0A; }
@media (prefers-color-scheme: dark) {
	#sc-toggle     { background: #F0F0EE; color: #0A0A0A; }
	#sc-toggle:hover { background: #FF8C5A; color: #0A0A0A; }
}

/* ---- Panel ---- */
#sc-panel {
	position: absolute;
	bottom: calc(100% + 10px);
	right: 0;
	width: 340px;
	background: #FAFAF8;
	border: 1px solid rgba(0,0,0,0.1);
	border-radius: 12px;
	box-shadow: 0 10px 48px rgba(0,0,0,0.14);
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
#sc-panel[hidden] { display: none; }

[data-theme="dark"] #sc-panel {
	background: #242424;
	border-color: rgba(255,255,255,0.18);
	box-shadow: 0 10px 48px rgba(0,0,0,0.5);
}
@media (prefers-color-scheme: dark) {
	#sc-panel {
		background: #242424;
		border-color: rgba(255,255,255,0.18);
		box-shadow: 0 10px 48px rgba(0,0,0,0.5);
	}
}

/* ---- Header ---- */
#sc-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 13px 14px;
	border-bottom: 1px solid rgba(0,0,0,0.08);
	font-size: 11px;
	letter-spacing: 0.06em;
	color: #B52B00;
}
[data-theme="dark"] #sc-header {
	border-color: rgba(255,255,255,0.08);
	color: #FF8C5A;
}
@media (prefers-color-scheme: dark) {
	#sc-header {
		border-color: rgba(255,255,255,0.08);
		color: #FF8C5A;
	}
}
#sc-close {
	background: none;
	border: none;
	cursor: pointer;
	color: inherit;
	padding: 2px;
	opacity: 0.6;
	transition: opacity 0.15s;
	line-height: 0;
}
#sc-close:hover       { opacity: 1; }
#sc-close:focus-visible { outline: 2px solid #B52B00; outline-offset: 2px; border-radius: 2px; }

/* ---- Messages ---- */
#sc-messages {
	padding: 14px;
	min-height: 160px;
	max-height: 280px;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 10px;
	scroll-behavior: smooth;
}
.sc-welcome {
	font-size: 12px;
	color: #0A0A0A;
	opacity: 0.4;
	text-align: center;
	padding: 16px 0 6px;
}
[data-theme="dark"] .sc-welcome { color: #FAFAF8; }
@media (prefers-color-scheme: dark) { .sc-welcome { color: #FAFAF8; } }

.sc-msg {
	max-width: 90%;
	padding: 9px 12px;
	border-radius: 8px;
	font-size: 13px;
	line-height: 1.55;
	word-wrap: break-word;
}
.sc-user {
	background: #0A0A0A;
	color: #FAFAF8;
	align-self: flex-end;
	border-bottom-right-radius: 2px;
}
[data-theme="dark"] .sc-user { background: #EBEBEB; color: #0A0A0A; }
@media (prefers-color-scheme: dark) { .sc-user { background: #EBEBEB; color: #0A0A0A; } }

.sc-ai {
	background: rgba(0,0,0,0.05);
	color: #0A0A0A;
	align-self: flex-start;
	border-bottom-left-radius: 2px;
}
[data-theme="dark"] .sc-ai { background: rgba(255,255,255,0.08); color: #F0F0EE; }
@media (prefers-color-scheme: dark) { .sc-ai { background: rgba(255,255,255,0.08); color: #F0F0EE; } }

.sc-ai a { color: #B52B00; text-decoration: underline; word-break: break-all; }
.sc-ai a:hover { text-decoration: none; }
[data-theme="dark"] .sc-ai a { color: #FF8C5A; }
@media (prefers-color-scheme: dark) { .sc-ai a { color: #FF8C5A; } }
.sc-ai p { margin: 0 0 6px; }
.sc-ai p:last-child { margin-bottom: 0; }
.sc-ai ul { margin: 4px 0 6px 16px; padding: 0; }
.sc-ai li { margin-bottom: 2px; }
.sc-ai strong { font-weight: 600; }

.sc-error {
	background: rgba(181,43,0,0.08);
	color: #B52B00;
	align-self: flex-start;
	border-bottom-left-radius: 2px;
	font-size: 12px;
}
[data-theme="dark"] .sc-error { background: rgba(255,140,90,0.1); color: #FF8C5A; }
@media (prefers-color-scheme: dark) { .sc-error { background: rgba(255,140,90,0.1); color: #FF8C5A; } }

/* ---- Typing indicator ---- */
.sc-typing {
	display: flex;
	gap: 4px;
	align-items: center;
	padding: 10px 12px;
	background: rgba(0,0,0,0.05);
	border-radius: 8px;
	border-bottom-left-radius: 2px;
	align-self: flex-start;
}
[data-theme="dark"] .sc-typing { background: rgba(255,255,255,0.08); }
@media (prefers-color-scheme: dark) { .sc-typing { background: rgba(255,255,255,0.08); } }
.sc-typing span {
	width: 5px;
	height: 5px;
	border-radius: 50%;
	background: #0A0A0A;
	opacity: 0.3;
	animation: sc-bounce 1s ease-in-out infinite;
}
[data-theme="dark"] .sc-typing span { background: #FAFAF8; }
@media (prefers-color-scheme: dark) { .sc-typing span { background: #FAFAF8; } }
.sc-typing span:nth-child(2) { animation-delay: 0.16s; }
.sc-typing span:nth-child(3) { animation-delay: 0.32s; }
@keyframes sc-bounce {
	0%, 80%, 100% { transform: translateY(0); opacity: 0.3; }
	40%           { transform: translateY(-5px); opacity: 0.8; }
}

/* ---- Input row ---- */
#sc-input-row {
	display: flex;
	gap: 8px;
	padding: 11px 14px;
	border-top: 1px solid rgba(0,0,0,0.08);
}
[data-theme="dark"] #sc-input-row { border-color: rgba(255,255,255,0.08); }
@media (prefers-color-scheme: dark) { #sc-input-row { border-color: rgba(255,255,255,0.08); } }

#sc-input {
	flex: 1;
	background: rgba(0,0,0,0.04);
	border: 1px solid rgba(0,0,0,0.12);
	border-radius: 6px;
	padding: 8px 10px;
	font-family: 'IBM Plex Mono', monospace;
	font-size: 12px;
	color: #0A0A0A;
	outline: none;
	transition: border-color 0.15s;
}
#sc-input::placeholder      { opacity: 0.45; }
#sc-input:focus             { border-color: #B52B00; }
[data-theme="dark"] #sc-input {
	background: rgba(255,255,255,0.06);
	border-color: rgba(255,255,255,0.12);
	color: #F0F0EE;
}
[data-theme="dark"] #sc-input:focus { border-color: #FF8C5A; }
@media (prefers-color-scheme: dark) {
	#sc-input {
		background: rgba(255,255,255,0.06);
		border-color: rgba(255,255,255,0.12);
		color: #F0F0EE;
	}
	#sc-input:focus { border-color: #FF8C5A; }
}

#sc-send {
	background: #0A0A0A;
	color: #FAFAF8;
	border: none;
	border-radius: 6px;
	padding: 8px 11px;
	cursor: pointer;
	line-height: 0;
	transition: background 0.15s;
	flex-shrink: 0;
}
#sc-send:hover           { background: #B52B00; }
#sc-send:disabled        { opacity: 0.35; cursor: default; }
#sc-send:focus-visible   { outline: 2px solid #B52B00; outline-offset: 2px; }
[data-theme="dark"] #sc-send       { background: #F0F0EE; color: #0A0A0A; }
[data-theme="dark"] #sc-send:hover { background: #FF8C5A; }
@media (prefers-color-scheme: dark) {
	#sc-send       { background: #F0F0EE; color: #0A0A0A; }
	#sc-send:hover { background: #FF8C5A; }
}

/* ---- Follow-up buttons ---- */
.sc-followup { display:flex; gap:6px; flex-wrap:wrap; padding:2px 0 4px; }
.sc-followup-btn {
	background: rgba(0,0,0,0.06); color: #0A0A0A; border: none; border-radius: 20px;
	padding: 5px 12px; font-family: 'IBM Plex Mono', monospace; font-size: 11px;
	cursor: pointer; transition: background 0.15s;
}
.sc-followup-btn:hover { background: #0A0A0A; color: #FAFAF8; }
[data-theme="dark"] .sc-followup-btn { background: rgba(255,255,255,0.1); color: #F0F0EE; }
[data-theme="dark"] .sc-followup-btn:hover { background: #F0F0EE; color: #0A0A0A; }
@media (prefers-color-scheme: dark) {
	.sc-followup-btn { background: rgba(255,255,255,0.1); color: #F0F0EE; }
	.sc-followup-btn:hover { background: #F0F0EE; color: #0A0A0A; }
}
.sc-cta-btn {
	display:inline-block; background: #B52B00; color: #FAFAF8; text-decoration: none;
	border-radius: 20px; padding: 5px 12px; font-family: 'IBM Plex Mono', monospace;
	font-size: 11px; transition: background 0.15s;
}
.sc-cta-btn:hover { background: #8f2000; color: #FAFAF8; text-decoration: none; }
[data-theme="dark"] .sc-cta-btn { background: #FF8C5A; color: #0A0A0A; }
[data-theme="dark"] .sc-cta-btn:hover { background: #e07040; color: #0A0A0A; }
@media (prefers-color-scheme: dark) {
	.sc-cta-btn { background: #FF8C5A; color: #0A0A0A; }
	.sc-cta-btn:hover { background: #e07040; color: #0A0A0A; }
}

/* ---- Mobile ---- */
@media (max-width: 420px) {
	#sc-widget          { right: 14px; bottom: 14px; }
	#sc-panel           { width: calc(100vw - 28px); }
	#sc-toggle-label    { display: none; }
	#sc-toggle          { padding: 12px; border-radius: 50%; }
}
</style>

<script>
(function () {
	var widget      = document.getElementById('sc-widget');
	var toggle      = document.getElementById('sc-toggle');
	var panel       = document.getElementById('sc-panel');
	var closeBtn    = document.getElementById('sc-close');
	var input       = document.getElementById('sc-input');
	var sendBtn     = document.getElementById('sc-send');
	var messages    = document.getElementById('sc-messages');
	var welcomeText = '<?php echo $welcome_text; ?>';

	var isOpen    = false;
	var isLoading = false;
	var welcomed  = false;

	function openPanel() {
		isOpen = true;
		panel.hidden = false;
		toggle.setAttribute('aria-expanded', 'true');
		if ( ! welcomed ) {
			welcomed = true;
			var el = document.createElement('div');
			el.className = 'sc-welcome';
			el.textContent = welcomeText;
			messages.appendChild(el);
		}
		setTimeout(function () { input.focus(); }, 60);
	}

	function closePanel() {
		isOpen = false;
		panel.hidden = true;
		toggle.setAttribute('aria-expanded', 'false');
		toggle.focus();
	}

	// Renders inline Markdown: **bold**, [text](url), bare https:// URLs.
	function renderInline(el, text) {
		var pattern = /\*\*(.+?)\*\*|\[([^\]]+)\]\((https?:\/\/[^)]+)\)|(https?:\/\/[^\s]+)/g;
		var last = 0, match;
		while ( (match = pattern.exec(text)) !== null ) {
			if (match.index > last) el.appendChild(document.createTextNode(text.slice(last, match.index)));
			if (match[1] !== undefined) {
				var s = document.createElement('strong');
				s.textContent = match[1];
				el.appendChild(s);
			} else if (match[2] !== undefined) {
				var a = document.createElement('a');
				a.href = match[3]; a.textContent = match[2];
				a.target = '_blank'; a.rel = 'noopener noreferrer';
				el.appendChild(a);
			} else {
				var url = match[4].replace(/[.,;:!?)"']+$/, '');
				var a2 = document.createElement('a');
				a2.href = url; a2.textContent = url;
				a2.target = '_blank'; a2.rel = 'noopener noreferrer';
				el.appendChild(a2);
				var trail = match[4].slice(url.length);
				if (trail) el.appendChild(document.createTextNode(trail));
			}
			last = match.index + match[0].length;
		}
		if (last < text.length) el.appendChild(document.createTextNode(text.slice(last)));
	}

	// Renders Markdown blocks: bullet lists, blank-line paragraphs, inline formatting.
	function renderMarkdown(el, text) {
		var lines = text.split('\n');
		var list = null;
		for (var i = 0; i < lines.length; i++) {
			var line = lines[i];
			if (/^[-*] /.test(line)) {
				if ( ! list) { list = document.createElement('ul'); el.appendChild(list); }
				var li = document.createElement('li');
				renderInline(li, line.replace(/^[-*] /, ''));
				list.appendChild(li);
			} else {
				list = null;
				if (line.trim() === '') continue;
				var p = document.createElement('p');
				renderInline(p, line);
				el.appendChild(p);
			}
		}
	}

	var contactUrl      = '<?php echo $contact_url; ?>';
	var newsletterUrl   = '<?php echo $newsletter_url; ?>';
	var labelContact    = '<?php echo $label_contact; ?>';
	var labelNewsletter = '<?php echo $label_newsletter; ?>';

	function addMsg(text, type) {
		var el = document.createElement('div');
		el.className = 'sc-msg sc-' + type;
		if (type === 'ai') {
			renderMarkdown(el, text);
		} else {
			el.textContent = text;
		}
		messages.appendChild(el);
		if (type === 'user') {
			messages.scrollTop = messages.scrollHeight;
		} else {
			requestAnimationFrame(function() {
				messages.scrollTop = el.offsetTop - messages.offsetTop;
			});
		}
		return el;
	}

	function showFollowUp() {
		var wrap = document.createElement('div');
		wrap.className = 'sc-followup';

		var yesBtn = document.createElement('button');
		yesBtn.className = 'sc-followup-btn';
		yesBtn.textContent = '<?php echo esc_js( __( 'Yes please', 'site-chat' ) ); ?>';

		var noBtn = document.createElement('button');
		noBtn.className = 'sc-followup-btn';
		noBtn.textContent = '<?php echo esc_js( __( 'No, thanks', 'site-chat' ) ); ?>';

		yesBtn.addEventListener('click', function (e) {
			e.stopPropagation();
			wrap.remove();
			addMsg('<?php echo esc_js( __( 'Sure! What else would you like to know?', 'site-chat' ) ); ?>', 'ai');
			input.focus();
		});

		noBtn.addEventListener('click', function (e) {
			e.stopPropagation();
			wrap.innerHTML = '';
			if (contactUrl) {
				var a = document.createElement('a');
				a.href = contactUrl; a.textContent = labelContact;
				a.className = 'sc-cta-btn'; a.target = '_blank'; a.rel = 'noopener noreferrer';
				wrap.appendChild(a);
			}
			if (newsletterUrl) {
				var b = document.createElement('a');
				b.href = newsletterUrl; b.textContent = labelNewsletter;
				b.className = 'sc-cta-btn'; b.target = '_blank'; b.rel = 'noopener noreferrer';
				wrap.appendChild(b);
			}
			if ( ! contactUrl && ! newsletterUrl) {
				wrap.remove();
			}
			messages.scrollTop = messages.scrollHeight;
		});

		wrap.appendChild(yesBtn);
		wrap.appendChild(noBtn);
		messages.appendChild(wrap);
		messages.scrollTop = messages.scrollHeight;
	}

	function showTyping() {
		var el = document.createElement('div');
		el.className = 'sc-typing';
		el.id = 'sc-typing';
		el.innerHTML = '<span></span><span></span><span></span>';
		messages.appendChild(el);
		messages.scrollTop = messages.scrollHeight;
	}

	function removeTyping() {
		var el = document.getElementById('sc-typing');
		if (el) el.remove();
	}

	function send() {
		var q = input.value.trim();
		if ( ! q || isLoading) return;

		isLoading = true;
		sendBtn.disabled = true;
		input.value = '';

		addMsg(q, 'user');
		showTyping();

		fetch('<?php echo $rest_url; ?>', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ question: q, nonce: '<?php echo esc_js( $nonce ); ?>' })
		})
		.then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
		.then(function (r) {
			removeTyping();
			if (r.ok) {
				addMsg(r.data.answer, 'ai');
				showFollowUp();
			} else {
				addMsg(r.data.message || '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'site-chat' ) ); ?>', 'error');
			}
		})
		.catch(function () {
			removeTyping();
			addMsg('<?php echo esc_js( __( 'Network error. Please try again.', 'site-chat' ) ); ?>', 'error');
		})
		.finally(function () {
			isLoading = false;
			sendBtn.disabled = false;
			input.focus();
		});
	}

	toggle.addEventListener('click', function () { isOpen ? closePanel() : openPanel(); });
	closeBtn.addEventListener('click', closePanel);
	sendBtn.addEventListener('click', send);
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && ! e.shiftKey) { e.preventDefault(); send(); }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && isOpen) closePanel();
	});
	document.addEventListener('click', function (e) {
		if (isOpen && ! widget.contains(e.target)) closePanel();
	});
})();
</script>

	<?php
} );
