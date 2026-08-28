# Changelog

## 2.6.1 — 2026-08-28

- Added: Chat log answers now render their Markdown — bold, italics, inline code, links, headings, lists, blockquotes and rules — instead of showing raw syntax, matching how visitors saw the answer in the chat widget. Collapsed previews show clean plain text.

## 2.6.0 — 2026-08-28

- Added: Chat log entries now expand to their full text. Each question and answer shows a short preview with a "Show full text" toggle that reveals the complete conversation, line breaks and all.
- Fixed: Answers were cut off at 300 characters in the log table with no way to read the rest.
- Changed: Raised the log storage limits so long conversations are kept in full — questions to 2,000 characters and answers to 10,000 (previously 1,000 and 5,000).

## 2.5.8 — 2026-08-10

- Changed: Shortened the Abilities API settings description to a plain summary of what's readable and writable.

## 2.5.7 — 2026-08-05

- Hardening: all chat-widget output is now explicitly escaped, and the conversation-log database queries are safely prepared. WordPress coding-standards cleanup. No changes to behavior.

## 2.5.6 — 2026-07-23

- Fixed: Raw Markdown appearing in chat answers. A link wrapped in bold (`**[title](url)**`) rendered its Markdown source as literal text, because bold content was inserted as plain text instead of being parsed. Bold, italic and link text are now rendered recursively.
- Added: Markdown headings, numbered lists, nested lists, italics, inline code, blockquotes and horizontal rules are now rendered instead of appearing as raw Markdown.
- Changed: System prompt now asks for lighter formatting — no headings, tables or code blocks — since answers appear in a narrow chat bubble.

## 2.5.5 — 2026-07-15

- Fixed: Chat no longer returns "Invalid request" on CDN/full-page-cached sites. The request nonce was baked into cached HTML that edge caches hold for days, but WordPress nonces expire in 12-24 hours, so the stale nonce failed verification. Removed the nonce entirely — this is a public, read-only, unauthenticated endpoint where a nonce adds no CSRF protection; the IP rate limiter remains the abuse defence.

## 2.5.4

- Fixed: `site-chat/get-settings` ability now declares an empty `input_schema`. Its absence caused MCP clients' ability-introspection calls to fail with a schema-validation error before the ability could ever be invoked (execution itself was unaffected).

## 2.5.3

- Changed: `site-chat/update-settings` ability now always registered (no opt-in checkbox required) and marked `destructive: true`, so compliant AI tools must prompt for confirmation before running it.
- Removed: "Enable write abilities" checkbox and `site_chat_write_abilities` option.

## 2.5.2 — 2026-06-01

- Fixed: `$input = null` default in abilities execute callbacks for PHP 8 compatibility.

## 2.5.1

- Fixed: `meta.mcp.public` key in abilities registration.

## 2.5.0

- Added: WordPress Abilities API integration (`site-chat/get-settings`, `site-chat/get-logs`, `site-chat/update-settings`).
- Added: "Enable write abilities" checkbox in settings.

## 2.4.0 — 2026-05-31

- Fixed: "Cookie check failed" error shown to logged-in users when the page cache serves a stale nonce — nonce now uses a custom action and is sent in the request body instead of the X-WP-Nonce header, bypassing WordPress cookie authentication entirely.

## 2.3.0 — 2026-05-21

- Added: Content index caching — site content is cached for 12 hours so database queries run once per cache period rather than on every chat request.
- Added: Cache is automatically cleared whenever a post is saved or published, so new content is available immediately without waiting for the cache to expire.

## 2.2.0

- Fixed: AI response scroll now correctly shows the top of the response — replaced `scrollIntoView` (unreliable inside a `position:fixed` widget) with a direct `offsetTop` calculation.
- Changed: Dark mode panel background lightened to `#242424` and border opacity increased, so the panel is visible against dark-background sites.

## 2.1.0

- Fixed: "Yes please" / "No, thanks" follow-up buttons no longer collapse the chat panel (click event was bubbling to the document close handler after the button was removed from the DOM).
- Fixed: "Yes please" no longer sends a bare "Yes please" string to the AI. It now shows a local "Sure! What else would you like to know?" message and focuses the input instead.
- Fixed: Conversation logging now works even if the plugin was updated without a full deactivate/reactivate cycle. The log table is created lazily on first admin page load if missing.
- Changed: AI responses now scroll to show the top of the response rather than the bottom, so users can read from the beginning of long answers.
- Added: Privacy policy content registered via `wp_add_privacy_policy_content()` — the built-in WordPress privacy policy generator now includes an AI Site Chat disclosure.
- Added: "View Chat Log" link added directly in the Log conversations settings row (in addition to the existing link at the top of the settings page).

## 2.0.0

- Added: Visitor Q&A logging — questions and answers are stored in a custom database table (`{prefix}site_chat_log`) and viewable under Settings > AI Site Chat > Chat Log. Enable/disable in settings.
- Added: Rate limit alert email — when an IP hits the rate limit, the site admin receives a one-time-per-IP-per-hour notification email.
- Added: Follow-up prompt — after every AI response, the widget offers "Yes please" / "No, thanks" buttons. "Yes please" sends a follow-up question; "No, thanks" shows Contact and Newsletter CTA links (configurable in settings).
- Added: Contact URL and Newsletter URL fields in settings for the follow-up CTA links.
- Changed: All display names updated to "AI Site Chat" (plugin name, Settings menu, admin page titles).
- Changed: DB table created on activation via `register_activation_hook` and `dbDelta()`.

## 1.9.0

- Added: Markdown rendering in chat responses — bold text, bullet lists, and links are now formatted properly.
- Changed: System prompt now explicitly instructs the AI to link to the specific post URL (not a general archive page) using Markdown link syntax.

## 1.8.0

- Changed: Context limit increased from 80,000 to 200,000 characters (Haiku supports a 200K token window).
- Changed: Per-post content capped at 1,500 characters so no single post consumes the entire context budget.

## 1.7.0

- Added: Post type selector in settings — choose exactly which content types the AI indexes. Framework types (Elementor templates, ACF config, etc.) are excluded by default.

## 1.6.0

- Added: Debug tool in Settings > Site Chat to inspect the exact content index being sent to the AI.
- Changed: Post type discovery now uses `show_ui` instead of `public`, to catch CPTs that are admin-visible but not publicly queryable.

## 1.5.0

- Added: Elementor content extraction — text stored in Elementor widget data is included when richer than post_content.
- Fixed: ACF flat arrays (multi-select, checkbox fields) are now included in context alongside plain string fields.

## 1.4.0

- Changed: AI now prefers internal site URLs over external links when answering questions.

## 1.3.0

- Added: URLs in AI responses are now rendered as clickable links (open in new tab).
- Changed: Welcome message is now generic ("Ask me anything about this site") rather than using the site name.

## 1.2.0

- Fixed: Nonce verification now uses the `wp_rest` action and `X-WP-Nonce` header — the WordPress standard for REST API requests. The previous approach failed for logged-in users.
- Added: Settings link in the Plugins list page.

## 1.1.0

- Added: Custom Instructions field for persona and tone customization.
- Added: Automatic indexing of all public post types (no hardcoded types required).
- Added: Generic ACF support — all string-valued fields included automatically.
- Added: `prefers-color-scheme: dark` support alongside `data-theme="dark"`.
- Added: 80,000-character context cap to prevent oversized API payloads.
- Added: Anthropic API error response handling (distinct from network errors).
- Added: Internationalization — all user-facing strings wrapped for translation.
- Added: `uninstall.php` — cleans up all options and transients on deletion.
- Changed: System prompt now uses site name and description from WordPress settings.
- Changed: Welcome message now includes the site name dynamically.
- Fixed: Removed redundant `is_admin()` check inside `wp_footer` hook.
- Fixed: Removed `apply_filters('the_content')` call outside the loop.
- Fixed: Plugin header now includes all required fields (URI, License, Text Domain, PHP/WP version requirements).
- Fixed: API key length-limited on save.

## 1.0.0

- Initial release.
