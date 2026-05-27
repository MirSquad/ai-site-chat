---
title: "Site Chat — Changelog"
doc_type: changelog
project: site-chat
created: 2026-03-28
updated: 2026-05-25
status: active
summary: "Session-by-session history of the Site Chat plugin: what changed, decisions made, dead ends, and what's next."
tags: [wordpress, plugin, ai, anthropic, miriamschwab-site, chat]
blog_candidate: false
---

# Site Chat — Changelog

Append a new entry at the end of every session.

---

## Session 1 — 2026-03-28

### What was built

- `site-chat.php` v1.0.0 — complete single-file WordPress plugin, delivered as a zip
- REST endpoint at `/wp-json/site-chat/v1/ask` with nonce auth and per-IP rate limiting (transient-based, 10 req/hr)
- Content fetching: all published posts, pages, and CPTs (ms_media, ms_timeline, ms_event) with ACF fields via `get_fields()` and graceful degradation
- Anthropic API integration using `claude-haiku-4-5-20251001`
- Floating chat widget output via `wp_footer` with inline CSS/JS (IBM Plex Mono, --accent-text: #B52B00, dark mode support)
- WP Admin settings page (Settings > Site Chat) for API key entry

### Decisions made

- Model: Haiku over Sonnet — cost efficiency for a POC; the context is factual site content, not nuanced reasoning
- Widget delivery via wp_footer with inline output — not wp_enqueue_scripts — because miriamschwab.me routes static assets through an Elementor CDN (see Decisions Log)
- No content caching — acceptable at current traffic volume, and caching adds complexity to a POC
- ACF as a soft dependency — get_fields() with graceful degradation, no hard is_plugin_active() check required

### Dead ends

- **WP 7.0 AI Connectors:** Considered waiting for WP 7.0 (April 9, 2026). Determined it's irrelevant — the WP 7.0 AI API is for plugin developers to register providers, not for building chat UIs or content indexing. Month of delay, zero benefit.
- **Separate enqueued assets:** Tried wp_enqueue_scripts first. Failed due to Elementor CDN routing on miriamschwab.me. Switched to inline wp_footer output.

### What's next

- Install the plugin on miriamschwab.me and enter the API key
- Test the REST endpoint and widget on the live site
- Verify ACF fields are returning correctly for ms_media, ms_timeline, ms_event
- Consider a blog post about the project

---

## Session 2 — 2026-04-03

### What was built

Full WP.org readiness pass and progressive feature development through v1.1.0–v2.0.0. Plugin installed and tested live on miriamschwab.me throughout.

- **v1.1.0:** WP.org standards applied — full plugin header, readme.txt, uninstall.php, i18n, output escaping, nonce verification, capability checks, sanitization with length limits. Generic ACF support (all string fields). Dark mode via `prefers-color-scheme`. 80K context cap.
- **v1.2.0:** Fixed nonce/REST auth — switched to `wp_rest` action with `X-WP-Nonce` header (the WP standard). Previous approach failed for logged-in users. Added Settings link in plugin list via `plugin_action_links_` filter.
- **v1.3.0:** Clickable links in responses (open in new tab). Generic welcome message.
- **v1.4.0:** AI prefers internal site URLs over external links.
- **v1.5.0:** Elementor content extraction from `_elementor_data` post meta. ACF flat array support (checkbox, multi-select fields).
- **v1.6.0:** Debug tool to inspect the full content index. CPT discovery switched from `public` to `show_ui` to catch admin-visible types.
- **v1.7.0:** Post type selector in settings — admins can choose exactly which types to index.
- **v1.8.0:** Context limit raised from 80K to 200K characters. Per-post content capped at 1,500 characters so no single post consumes the entire budget.
- **v1.9.0:** Markdown rendering in chat responses — DOM-safe `renderMarkdown()` / `renderInline()` functions. System prompt instructs AI to use specific post URLs in Markdown link format.
- **v2.0.0:** Plugin renamed to "AI Site Chat" everywhere. Q&A logging to DB table (opt-in, admin-viewable log page). Rate limit email alert (one per IP per period via transient deduplication). Follow-up prompt after each response ("Yes please" / "No, thanks") with configurable Contact URL and Newsletter URL CTAs. DB table created via `register_activation_hook` + `dbDelta`. Privacy disclosure on settings page.

### Decisions made

- API key stored in WP Admin settings (not PHP constant) — more accessible to non-developers
- Logging is opt-in (off by default) — admin must enable it; avoids privacy surface for sites that don't need it
- Plugin confirmed generic — no hardcoded references to miriamschwab.me

### Dead ends / bugs found during testing

- **Context budget:** Blog posts with full content consumed the 80K budget before CPT content was reached. Fixed by raising limit to 200K and adding per-post cap.
- **"Invalid request" for logged-in users:** Root cause — nonce action was user-ID-bound but REST API ignores auth cookie without `X-WP-Nonce` header. Fixed with standard `wp_rest` nonce pattern.
- **PressConf post missing from context:** Resolved by the 200K + 1,500-char-cap combination.

### What's next

- Test Q&A logging, rate limit email, and follow-up CTAs on live site
- Enter Contact URL and Newsletter URL in settings
- Decide on content caching

---

## Session 3 — 2026-04-21

### What was built

Bug fixes and two feature additions through v2.1.0–v2.3.0. All confirmed working on live site.

- **v2.1.0:** Privacy policy content registered via `wp_add_privacy_policy_content()`. Lazy DB table creation on `admin_init` (fixes installs that bypassed the activation hook). "Yes please" now handled client-side — shows a local "Sure! What else would you like to know?" response, no API call, AI no longer confused by bare "Yes please" input. `stopPropagation()` added to both follow-up buttons (clicking either detached the button from the DOM before the document click handler ran, causing `widget.contains(e.target)` to return false and closing the panel). "View Chat Log" link added in the Log conversations settings row.
- **v2.2.0:** Scroll fixed — `scrollIntoView` is unreliable inside `position:fixed` widgets (browser may scroll the page body instead of the container); replaced with direct `offsetTop` calculation via `requestAnimationFrame`. Dark mode panel background lightened from `#161616` to `#242424`; border opacity raised from 0.09 to 0.18 so panel is visible against dark-background sites.
- **v2.3.0:** Content index cached as a 12-hour transient — DB queries run once per cache period instead of on every chat request. `save_post` hook immediately clears the cache when any post is saved, so new content appears without waiting for TTL expiry.

### Decisions made

- **Caching added** (reverses Decision 2): 12-hour transient + `save_post` invalidation. The `save_post` hook makes stale content a non-issue — any publish or update clears the cache immediately.
- **"Yes please" is local, not an API call:** Cleaner UX, cheaper, avoids AI receiving a context-free message.

### Dead ends

- **`scrollIntoView({ behavior: 'smooth', block: 'start' })`:** Appeared correct but doesn't work reliably inside a `position:fixed` element — browser targets the page body. Replaced with `requestAnimationFrame(() => messages.scrollTop = el.offsetTop - messages.offsetTop)`.
- **"No thanks" CTAs not showing:** Two causes: (1) missing `stopPropagation` caused panel to close; (2) Elementor Hosting's server-side page cache served old HTML with empty URL strings after settings were saved. Fix: install 2.1.0 + clear Elementor page cache from WP Admin.

### What's next

- Consider writing a blog post about building this plugin (blog_candidate: true)
- Monitor Anthropic API costs now that the plugin is in active use

---

## Session 4 — 2026-05-25

### WP.org readiness and code quality audit

Targeted fixes for WP.org readiness, bulk query safety, and readme accuracy. No new user-visible features.

**Deactivation hook added:**
- `register_deactivation_hook` now calls `site_chat_deactivate()` which deletes the `site_chat_context_cache` transient
- Prevents a stale cache from persisting after the plugin is deactivated and re-activated on a new version

**`plugin_row_meta` filter added:**
- Adds a "Visit plugin site" link to the plugin row in the Plugins list, pointing to `https://miriamschwab.me/plugins/site-chat`

**Bulk query — developer filter added:**
- `get_posts()` in `site_chat_get_context()` now respects `apply_filters('site_chat_context_posts_limit', -1, $type)`
- Allows developers to cap posts-per-type without editing core plugin code

**Nonce/cache comment added:**
- Inline comment documents why cached nonces work for unauthenticated visitors (WP nonce validation is not session-specific for logged-out users; rate limiting is the primary abuse defence)

**`readme.txt` FAQ corrected:**
- "Does the plugin store visitor questions?" — corrected from "No" wording to "Optionally. When Log conversations is enabled..." (logging was added in v2.0.0)
- Context size: updated from 80,000 to 200,000 characters and mentioned the `site_chat_context_posts_limit` filter
- Removed stale "No visitor data is stored" clause from the third-party service disclosure

**`.gitignore` updated:**
- Replaced single-name pillar doc patterns with wildcards (`*-handoff.md`, `*-changelog.md`, etc.)

### Decisions made

- Version not bumped this session (all compliance/quality fixes, no user-visible behaviour change). Bump to 2.4.0 before next packaging.

### What's next

- Bump version to 2.4.0 before next zip is packaged
- Blog post consideration unchanged (blog_candidate: true)
- Monitor Anthropic API costs
