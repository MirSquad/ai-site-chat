---
title: "Site Chat — Decisions Log"
doc_type: decisions-log
project: site-chat
created: 2026-03-28
updated: 2026-04-21
status: active
summary: "Architectural decisions for the Site Chat plugin — why it's built this way and what must not be changed without understanding this history."
tags: [wordpress, plugin, ai, anthropic, miriamschwab-site, chat]
blog_candidate: false
---

# Site Chat — Decisions Log

This is the most critical doc for handoff. The reasoning here lives nowhere in the code. Its absence causes new sessions to confidently undo hard-won decisions.

---

## Decision 1 — Widget output via `wp_footer` with inline CSS/JS

**Problem:** The chat widget needs CSS and JS on the front end. The default approach is `wp_enqueue_scripts`.

**Decision:** Output everything inline via the `wp_footer` hook. No separate enqueued files.

**What was tried first:** `wp_enqueue_scripts` with separate CSS and JS files referenced via `plugins_url()`. These loaded correctly in a vanilla WP environment but failed silently on miriamschwab.me — the files were routed through Elementor's CDN and either blocked or served with wrong cache headers.

**Why inline `wp_footer` works:** It bypasses the asset pipeline entirely. The HTML, CSS, and JS are output directly into the page footer as a string, not as separately loaded resources.

**Warning:** Do not refactor this to enqueued assets without first understanding how Elementor's CDN is configured on miriamschwab.me. It will appear to work in a local environment and fail on the live site.

---

## Decision 2 — Content caching via transient (revised from "no caching")

**Problem:** The plugin fetches all site content on every chat request to build the AI context. With large content volumes this is slow and DB-heavy.

**Original decision (Session 1):** No caching — acceptable at low traffic for a POC.

**Revised decision (Session 3, v2.3.0):** Cache the assembled context string as a 12-hour WordPress transient (`site_chat_context_cache`). Clear the cache immediately via a `save_post` hook whenever any post is saved.

**Why revised:** Site owner publishes 2–3 posts per week. 12-hour TTL means at most 12 hours of stale context, but in practice the `save_post` invalidation makes staleness a non-issue — any publish or update clears the cache immediately. The performance benefit (DB queries once per cache period instead of per request) is meaningful even at low traffic.

**Conditions that would change this:** If the site moves to an object caching layer (Redis/Memcached) that doesn't honour `delete_transient` reliably, cache invalidation could silently break. In that case, switching to a version-keyed cache (incrementing a counter option on save and appending it to the transient key) would be more robust.

---

## Decision 3 — Haiku model, not Sonnet

**Problem:** Which Anthropic model to use?

**Decision:** `claude-haiku-4-5-20251001`

**Why:** This is a proof of concept. The task is factual retrieval from site content — not nuanced reasoning, creative writing, or complex analysis. Haiku is fast, cheap, and more than capable of this use case. Using Sonnet here would be over-engineering a demo.

**Conditions that would change this:** If response quality is noticeably poor on the live site (wrong answers, missed context, confusing explanations), upgrade to Sonnet. Document the quality difference if switching.

---

## Decision 4 — Did not wait for WP 7.0 AI Connectors

**Problem:** WP 7.0 is releasing April 9, 2026, with a new "AI Connectors" screen and a provider-agnostic PHP API for AI integration. Should we wait for it?

**Decision:** Build now without WP 7.0.

**Why:** After reviewing what WP 7.0 AI Connectors actually does: it provides a PHP API for plugin developers to register AI providers (Anthropic, OpenAI, etc.) so that other plugins can use whichever provider the site admin has configured. It does not provide content indexing, chat UI components, or conversation flows. Using it would mean writing a provider registration layer that adds no user-facing value to this specific plugin, while delaying the project by a month.

**Note for any blog post:** This is worth clarifying publicly. There's likely confusion in the WP community about what WP 7.0 AI features actually are vs. what people might assume they are.

---

## Decision 5 — ACF as a soft dependency via `get_fields()` with graceful degradation

**Problem:** The custom post types ms_media, ms_timeline, and ms_event have ACF fields that contain important context. How should the plugin handle these without making ACF a hard dependency?

**Decision:** Call `get_fields($post_id)` for each CPT item. If it returns false or empty (ACF not active, or fields not configured), skip the ACF data silently and continue with standard WP fields.

**Why:** Makes the plugin portable. A hard `is_plugin_active('advanced-custom-fields/acf.php')` check would work but is brittle — it breaks if ACF Pro is used instead, or if the path changes. `get_fields()` returning false is a clean signal that ACF data isn't available.

**Warning:** If ACF is deactivated or field keys change, AI responses about ms_media, ms_timeline, and ms_event will be incomplete. This fails silently — no error is surfaced to the user or admin. If response quality about those CPTs degrades unexpectedly, check ACF first.

---

## Decision 6 — "Yes please" follow-up handled client-side, no API call

**Problem:** When a visitor clicks "Yes please" after an AI response, the original implementation sent the literal string "Yes please" to the API. The AI had no context for what it was agreeing to, producing confused responses.

**Decision:** Handle "Yes please" entirely in the browser. Remove the follow-up buttons, show a canned local message ("Sure! What else would you like to know?"), and focus the input. No API call.

**Why:** Cheaper, faster, and the AI can't be confused by a context-free affirmation. The user's actual follow-up question is what matters — get them to the input box immediately.
