---
title: "Site Chat — Handoff Doc"
doc_type: handoff
project: site-chat
created: 2026-03-28
updated: 2026-04-21
status: active
summary: "Current state snapshot for the Site Chat plugin. Read before any session."
tags: [wordpress, plugin, ai, anthropic, miriamschwab-site, chat]
blog_candidate: false
---

# Site Chat — Handoff Doc

## Current state

**Version:** 2.3.0  
**Status:** Installed and working on miriamschwab.me  
**Last zip:** `site-chat-2.3.0.zip`

Plugin is live, tested, and confirmed working: chat widget, Q&A logging, rate limit email, follow-up CTAs with Contact and Newsletter links, content caching.

---

## The single most important constraint

The Anthropic API key is stored via WP Admin under **Settings > AI Site Chat**. Without it, the widget loads but returns nothing. It is stored as a WordPress option (`site_chat_api_key`), not as a PHP constant.

---

## What's fragile

**The `wp_footer` inline approach.** Widget CSS and JS are output inline via `wp_footer`, not `wp_enqueue_scripts`. This bypasses the Elementor CDN on miriamschwab.me which strips or defers enqueued assets. Do not refactor to enqueued assets without testing on the live site. See Decisions Log — Decision 1.

**Server-side page cache.** The widget JS has PHP values (contact URL, newsletter URL, nonce) baked in at render time. If Elementor's page cache serves stale HTML, those values will be outdated. After changing URL settings, clear the Elementor page cache from WP Admin.

**ACF dependency.** ACF fields are fetched via `get_fields()`. If ACF is deactivated or field keys change, AI responses about ms_media, ms_timeline, ms_event will be silently incomplete — no error is surfaced.

**Context cache.** The content index is cached as a 12-hour transient (`site_chat_context_cache`). Saving any post clears it immediately. If the AI seems unaware of recent content, check whether the transient is stuck — some object caching layers (Redis/Memcached) may not honour `delete_transient` reliably.

---

## What's been tried and ruled out

**`scrollIntoView` inside `position:fixed`:** Doesn't work reliably — browser targets the page body instead of the scroll container. Use direct `offsetTop` calculation with `requestAnimationFrame`.

**`wp_enqueue_scripts` for widget assets:** Fails on miriamschwab.me due to Elementor CDN. See Decisions Log.

**WP 7.0 AI Connectors:** Irrelevant to this use case. See Decisions Log.

---

## Open questions / pending

- Consider writing a blog post about building this plugin (`blog_candidate: true`)
- Monitor Anthropic API costs now that the plugin is in active use

---

## Document index

| Doc | Filename |
|---|---|
| Session Opener | `site-chat-session-opener.md` |
| Handoff (this doc) | `site-chat-handoff.md` |
| Project Context | `site-chat-project-context.md` |
| Changelog | `site-chat-changelog.md` |
| Decisions Log | `site-chat-decisions-log.md` |
