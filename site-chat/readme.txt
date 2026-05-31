=== AI Site Chat ===
Contributors: miriamschwab
Tags: ai, chat, chatbot, claude, anthropic
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an AI-powered floating chat widget. Visitors can ask questions and get answers based on your published content, powered by Claude.

== Description ==

Site Chat indexes your published posts, pages, and custom post types, then uses the Anthropic API (Claude) to answer visitor questions about your site in a floating chat widget.

**Features**

* Floating chat widget — bottom-right, opens on click
* Answers questions based on your actual site content
* Supports all public post types (posts, pages, and any custom post types)
* ACF field support — string-valued ACF fields are automatically included as context
* Dark mode — respects both `prefers-color-scheme` and `data-theme="dark"`
* Per-IP rate limiting (configurable)
* Enable/disable toggle
* Custom instructions field — add persona, tone, or topic emphasis
* Responsive — collapses to icon-only on small screens
* Accessibility-friendly — ARIA roles, keyboard navigation, focus management

**Requirements**

* An Anthropic API key (get one at [console.anthropic.com](https://console.anthropic.com))

**Third-party service**

This plugin sends your site's published content and visitor questions to the Anthropic API. Please review [Anthropic's privacy policy](https://www.anthropic.com/privacy) and [terms of service](https://www.anthropic.com/legal/consumer-terms) before activating. When conversation logging is enabled (the default), visitor questions and AI answers are stored in your site's database.

== Installation ==

1. Upload the `site-chat` folder to `/wp-content/plugins/`.
2. Activate from **Plugins > Installed Plugins**.
3. Go to **Settings > Site Chat** and enter your Anthropic API key.
4. Check the **Enable chat widget** box and save.
5. Visit your site — the chat button appears in the bottom-right corner.

== Frequently Asked Questions ==

= Where do I get an Anthropic API key? =

Sign up at [console.anthropic.com](https://console.anthropic.com). Usage is billed per token.

= What content does the plugin index? =

All published posts, pages, and public custom post types. Private posts, drafts, and attachments are excluded. If ACF (Advanced Custom Fields) is active, string-valued ACF fields are included automatically.

= Does the plugin store visitor questions or answers? =

Optionally. When **Log conversations** is enabled in Settings > AI Site Chat, questions and AI answers are stored in your site's database and visible only to admins. Logging is on by default — disable it if you prefer not to store visitor conversations.

= Can I customize what the AI says? =

Yes. Use the **Custom Instructions** field in Settings > Site Chat to add persona guidance, tone instructions, or topic emphasis. These are appended to the system prompt.

= What if my site has a lot of content? =

The plugin caps the context sent to the API at 200,000 characters. If your site is very large, the most recently updated content will be included first and older content may be truncated. Developers can also use the `site_chat_context_posts_limit` filter to cap posts per type.

= Can I change the rate limit? =

Yes. The default is 10 requests per IP per hour. Adjust it in **Settings > Site Chat**.

= Does this work with Elementor? =

Yes. The widget CSS and JS are output inline via `wp_footer` to bypass CDN caching that can affect enqueued assets on some hosting configurations.

== Screenshots ==

1. The floating chat widget on the frontend.
2. Settings > Site Chat — API key, rate limit, and custom instructions.

== Changelog ==

= 2.4.0 =
* Fixed: "Cookie check failed" error shown to logged-in users when the page cache serves a stale nonce — nonce now uses a custom action and is sent in the request body instead of the X-WP-Nonce header, bypassing WordPress cookie authentication entirely.

= 2.3.0 =
* Added: Content index caching — site content is cached for 12 hours so database queries run once per cache period rather than on every chat request.
* Added: Cache is automatically cleared whenever a post is saved or published, so new content is available immediately without waiting for the cache to expire.

= 2.2.0 =
* Fixed: AI response scroll now correctly shows the top of the response — replaced `scrollIntoView` (unreliable inside a `position:fixed` widget) with a direct `offsetTop` calculation.
* Changed: Dark mode panel background lightened to `#242424` and border opacity increased, so the panel is visible against dark-background sites.

= 2.1.0 =
* Fixed: "Yes please" / "No, thanks" follow-up buttons no longer collapse the chat panel (click event was bubbling to the document close handler after the button was removed from the DOM).
* Fixed: "Yes please" no longer sends a bare "Yes please" string to the AI. It now shows a local "Sure! What else would you like to know?" message and focuses the input instead.
* Fixed: Conversation logging now works even if the plugin was updated without a full deactivate/reactivate cycle. The log table is created lazily on first admin page load if missing.
* Changed: AI responses now scroll to show the top of the response rather than the bottom, so users can read from the beginning of long answers.
* Added: Privacy policy content registered via `wp_add_privacy_policy_content()` — the built-in WordPress privacy policy generator now includes an AI Site Chat disclosure.
* Added: "View Chat Log" link added directly in the Log conversations settings row (in addition to the existing link at the top of the settings page).

= 2.0.0 =
* Added: Visitor Q&A logging — questions and answers are stored in a custom database table (`{prefix}site_chat_log`) and viewable under Settings > AI Site Chat > Chat Log. Enable/disable in settings.
* Added: Rate limit alert email — when an IP hits the rate limit, the site admin receives a one-time-per-IP-per-hour notification email.
* Added: Follow-up prompt — after every AI response, the widget offers "Yes please" / "No, thanks" buttons. "Yes please" sends a follow-up question; "No, thanks" shows Contact and Newsletter CTA links (configurable in settings).
* Added: Contact URL and Newsletter URL fields in settings for the follow-up CTA links.
* Changed: All display names updated to "AI Site Chat" (plugin name, Settings menu, admin page titles).
* Changed: DB table created on activation via `register_activation_hook` and `dbDelta()`.

= 1.9.0 =
* Added: Markdown rendering in chat responses — bold text, bullet lists, and links are now formatted properly.
* Changed: System prompt now explicitly instructs the AI to link to the specific post URL (not a general archive page) using Markdown link syntax.

= 1.8.0 =
* Changed: Context limit increased from 80,000 to 200,000 characters (Haiku supports a 200K token window).
* Changed: Per-post content capped at 1,500 characters so no single post consumes the entire context budget.

= 1.7.0 =
* Added: Post type selector in settings — choose exactly which content types the AI indexes. Framework types (Elementor templates, ACF config, etc.) are excluded by default.

= 1.6.0 =
* Added: Debug tool in Settings > Site Chat to inspect the exact content index being sent to the AI.
* Changed: Post type discovery now uses show_ui instead of public, to catch CPTs that are admin-visible but not publicly queryable.

= 1.5.0 =
* Added: Elementor content extraction — text stored in Elementor widget data is included when richer than post_content.
* Fixed: ACF flat arrays (multi-select, checkbox fields) are now included in context alongside plain string fields.

= 1.4.0 =
* Changed: AI now prefers internal site URLs over external links when answering questions.

= 1.3.0 =
* Added: URLs in AI responses are now rendered as clickable links (open in new tab).
* Changed: Welcome message is now generic ("Ask me anything about this site") rather than using the site name.

= 1.2.0 =
* Fixed: Nonce verification now uses the `wp_rest` action and `X-WP-Nonce` header — the WordPress standard for REST API requests. The previous approach failed for logged-in users.
* Added: Settings link in the Plugins list page.

= 1.1.0 =
* Added: Custom Instructions field for persona and tone customization.
* Added: Automatic indexing of all public post types (no hardcoded types required).
* Added: Generic ACF support — all string-valued fields included automatically.
* Added: `prefers-color-scheme: dark` support alongside `data-theme="dark"`.
* Added: 80,000-character context cap to prevent oversized API payloads.
* Added: Anthropic API error response handling (distinct from network errors).
* Added: Internationalization — all user-facing strings wrapped for translation.
* Added: `uninstall.php` — cleans up all options and transients on deletion.
* Changed: System prompt now uses site name and description from WordPress settings.
* Changed: Welcome message now includes the site name dynamically.
* Fixed: Removed redundant `is_admin()` check inside `wp_footer` hook.
* Fixed: Removed `apply_filters('the_content')` call outside the loop.
* Fixed: Plugin header now includes all required fields (URI, License, Text Domain, PHP/WP version requirements).
* Fixed: API key length-limited on save.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.3.0 =
Adds content caching — reduces database load significantly. Cache is cleared automatically on post save.

= 2.2.0 =
Fixes AI response scroll position and improves panel visibility in dark mode.

= 2.1.0 =
Fixes follow-up button behavior, conversation logging reliability, and scroll position on long AI responses.

= 2.0.0 =
Adds Q&A logging, rate limit email alerts, and follow-up CTAs. Deactivate and delete the old version before installing — the activation hook must run to create the log database table.

= 1.4.0 =
AI now prefers linking to site pages over external URLs.

= 1.3.0 =
Clickable links in AI responses, generic welcome message.

= 1.2.0 =
Fixes "Invalid request" error for logged-in users. Recommended update.

= 1.1.0 =
Adds custom instructions, generic post type support, dark mode media query, and uninstall cleanup. No breaking changes.

= 1.0.0 =
Initial release.
