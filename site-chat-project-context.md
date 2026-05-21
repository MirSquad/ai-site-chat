---
title: "Site Chat — Project Context"
doc_type: project-context
project: site-chat
created: 2026-03-28
updated: 2026-04-21
status: active
summary: "Full technical architecture for the Site Chat plugin — how it works, what it fetches, how the widget is built."
tags: [wordpress, plugin, ai, anthropic, miriamschwab-site, chat, rest-api, acf]
blog_candidate: false
---

# Site Chat — Project Context

## What this is

A single-file WordPress plugin (`site-chat.php`) that adds a floating AI chat widget to any WordPress site. Visitors can ask questions about the site's content. The plugin fetches all site content, caches it, sends it as context to the Anthropic API, and returns an answer in the widget.

Built originally for miriamschwab.me, designed to be generic and WP.org-ready.

---

## File structure

```
site-chat/
├── site-chat.php       # Everything — REST endpoint, content fetching, widget output
├── readme.txt          # WP.org listing
├── uninstall.php       # Cleanup on deletion
└── languages/          # Empty — ready for translations
```

Single file. No Composer dependencies. No build step.

---

## Architecture

### REST endpoint

`/wp-json/site-chat/v1/ask`  
Method: POST  
Accepts: `{ "question": "..." }`  
Returns: `{ "answer": "..." }`

Protected by WordPress nonce verification. The widget sends a nonce generated at page load via `wp_create_nonce('wp_rest')` in the `X-WP-Nonce` header.

### Content fetching and caching

On chat request, the plugin calls `site_chat_get_context()` which:
1. Checks for a cached transient (`site_chat_context_cache`) — returns immediately if found
2. On cache miss: fetches all published posts for each active post type, extracts content, ACF fields, categories, tags
3. Stores the result as a 12-hour transient

The cache is cleared immediately via a `save_post` hook whenever any post is saved, so new content is never more than one request stale.

Per-post content is capped at 1,500 characters. Total context is capped at 200,000 characters.

### API call

Model: `claude-haiku-4-5-20251001`  
Provider: Anthropic  
Key: WordPress option `site_chat_api_key` (set via Settings > AI Site Chat)  
Max tokens: 512 per response

The full content index is sent as a system message. The visitor's question is the user message.

### Rate limiting

Per-IP rate limiting via WordPress transients.  
Default: 10 requests/hour (configurable in Settings)  
When limit is hit: admin receives a one-time email alert per IP per period.

### Conversation logging

Optional (off by default). When enabled, each Q&A pair is stored in `{prefix}site_chat_log` with a hashed IP and timestamp. Viewable under Settings > AI Site Chat — Chat Log.

Table created via `register_activation_hook` + `dbDelta`. Also created lazily on `admin_init` if missing (covers updates that bypassed the activation hook).

### Widget output

Registered via `wp_footer` hook — **not** `wp_enqueue_scripts`. This bypasses the Elementor CDN on miriamschwab.me which strips/defers enqueued assets. All CSS and JS are output inline.

The widget JS has PHP values baked in at render time (nonce, contact URL, newsletter URL). If the site has server-side page caching, those values reflect the state at last cache-build time — clear page cache after changing settings.

### Follow-up prompt

After every AI response, the widget shows "Yes please" / "No, thanks" buttons.
- **Yes please:** Handled client-side — removes buttons, shows "Sure! What else would you like to know?", focuses input. No API call.
- **No, thanks:** Removes buttons, shows Contact and Newsletter CTA links (if configured in settings). If neither URL is configured, shows a fallback farewell message.

---

## Design system

The widget matches miriamschwab.me exactly:

| Token | Value |
|---|---|
| Font | IBM Plex Mono |
| Accent color | `#B52B00` |
| Dark mode | Detected via `data-theme="dark"` on `<html>` and `prefers-color-scheme: dark` |
| Dark panel bg | `#242424` (lightened from black for visibility against dark site backgrounds) |

---

## Settings

| Option | Default | Purpose |
|---|---|---|
| `site_chat_api_key` | — | Anthropic API key |
| `site_chat_enabled` | true | Show/hide widget |
| `site_chat_rate_limit` | 10 | Max requests per IP per hour |
| `site_chat_post_types` | all show_ui types minus framework types | Which post types to index |
| `site_chat_custom_instructions` | — | Extra instructions appended to system prompt |
| `site_chat_log_enabled` | false | Enable Q&A logging |
| `site_chat_contact_url` | — | Contact CTA link for "No, thanks" follow-up |
| `site_chat_newsletter_url` | — | Newsletter CTA link for "No, thanks" follow-up |

---

## Custom post types (miriamschwab.me)

| CPT | Notes |
|---|---|
| `ms_media` | ACF fields fetched via get_fields() |
| `ms_timeline` | ACF fields fetched via get_fields() |
| `ms_event` | ACF fields fetched via get_fields() |

If get_fields() returns false or empty (ACF inactive, fields not attached), that item's ACF data is silently skipped.

---

## What this project is not

- Not a production-grade chat system — no conversation history, no user accounts
- Not using vector search or embeddings — full content sent as context on every request
- Not dependent on WP 7.0 AI Connectors — that's a PHP API for plugin devs, not relevant here
