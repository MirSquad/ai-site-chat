# AI Site Chat WordPress Plugin

A WordPress plugin that adds an AI-powered floating chat widget to your site. Visitors can ask questions and get answers based on your published content, powered by Claude (Anthropic).

## Why

Most AI chat solutions for WordPress are complex, expensive, or require external services to index your content. This plugin takes a simpler approach: it reads your existing WordPress content — posts, pages, custom post types, ACF fields — caches it, and sends it as context to the Anthropic API on each conversation. No embeddings, no vector databases, no external indexing services. Just your content and a language model.

## Features

- **Floating chat widget** with dark mode support
- **Automatic content indexing** — fetches all published content including custom post types, Elementor content, and ACF fields
- **Smart caching** — 12-hour transient cache, automatically cleared when any post is saved
- **Per-IP rate limiting** with configurable threshold and email alerts to the admin
- **Conversation logging** (opt-in) with admin-viewable log page
- **Follow-up prompts** — "Yes please" / "No, thanks" buttons after each response, with configurable Contact and Newsletter CTA links
- **Markdown rendering** in chat responses with clickable links
- **Post type selector** — choose exactly which content types to include in the AI's context
- **Custom instructions** — append your own instructions to the system prompt
- **Privacy policy** content auto-registered with WordPress

## How it works

1. On page load, the widget is injected via `wp_footer` with inline CSS/JS
2. When a visitor asks a question, it hits the REST endpoint at `/wp-json/site-chat/v1/ask`
3. The plugin fetches the cached content index (or builds it on first request)
4. The full index is sent as a system message to Claude Haiku, with the visitor's question as the user message
5. The AI response is streamed back and rendered in the widget with Markdown formatting

Content is capped at 1,500 characters per post and 200,000 characters total to stay within model context limits.

## Installation

1. Download or clone this repository
2. Copy the `site-chat` folder into `wp-content/plugins/`
3. Activate the plugin in WordPress
4. Go to **Settings > AI Site Chat** and enter your Anthropic API key
5. The chat widget will appear on your site immediately

You'll need an [Anthropic API key](https://console.anthropic.com/) — the plugin uses Claude Haiku, which is the most cost-effective model for this use case.

## Settings

| Setting | Default | Description |
|---------|---------|-------------|
| API Key | — | Your Anthropic API key |
| Enabled | On | Show/hide the chat widget |
| Rate Limit | 10/hr | Max requests per visitor per hour |
| Post Types | All | Which content types to include |
| Custom Instructions | — | Extra instructions for the AI |
| Logging | Off | Enable conversation logging |
| Contact URL | — | CTA link shown after conversation |
| Newsletter URL | — | CTA link shown after conversation |

## Requirements

- WordPress 6.0+
- PHP 7.4+
- An Anthropic API key

## License

GPL-2.0-or-later

## WordPress Abilities API

This plugin exposes abilities for the [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/) (WordPress 6.9+), making it manageable by AI agents via the [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin.

### Requirements

- WordPress 6.9+
- [MCP Adapter plugin](https://github.com/WordPress/mcp-adapter)

### Available abilities

| Ability | Access | Description |
|---|---|---|
| `site-chat/get-settings` | Always on | Returns all plugin settings. The API key is masked — only the last 4 characters are returned |
| `site-chat/get-logs` | Always on | Returns recent chat conversations ordered newest first. Accepts `per_page` (1–100, default 20) |
| `site-chat/update-settings` | Write (opt-in) | Updates one or more settings. Pass only the fields you want to change. The API key cannot be changed via this ability |

**Updatable fields via `update-settings`:** `enabled`, `rate_limit`, `custom_instructions`, `log_enabled`, `contact_url`, `newsletter_url`

### Enabling write abilities

Write abilities are disabled by default. To enable them, go to **Settings > AI Site Chat** and check **Enable write abilities** under the Abilities API row.
