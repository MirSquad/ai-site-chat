---
title: "Site Chat — Session Opener"
doc_type: session-opener
project: site-chat
created: 2026-03-28
updated: 2026-04-21
status: active
summary: "Paste at the start of every session on the Site Chat plugin. Doc manifest and standing update instructions."
tags: [wordpress, plugin, ai, anthropic, miriamschwab-site, chat]
blog_candidate: true
---

# Site Chat — Session Opener

Paste this at the start of every working session on this project. It is not the handoff doc — it is the doc management layer. It tells you what docs exist and instructs you to produce updated content at session end, automatically.

**To end a session and trigger all doc updates: say "wrap up."**

---

## Project snapshot

A single-file WordPress plugin that adds an AI-powered floating chat widget to any WordPress site. Indexes all site content, answers visitor questions using the Anthropic API. Built originally for miriamschwab.me and designed to be generic and WP.org-ready. Currently at v2.3.0, installed and working on the live site.

---

## Doc manifest

| Doc | Filename | Update trigger |
|---|---|---|
| Session Opener | `site-chat-session-opener.md` | When open items or doc structure change |
| Handoff | `site-chat-handoff.md` | Every session — reflects current state |
| Project Context | `site-chat-project-context.md` | When architecture or technical details change |
| Changelog | `site-chat-changelog.md` | Every session — append a new entry |
| Decisions Log | `site-chat-decisions-log.md` | When a significant decision is made |

All files live in: `Claude Work/dev-work/plugins/ai-site-chat/`

---

## Relevant skills

- `work-docs` — doc management, frontmatter, five-pillar system
- `plugin-builder` — WordPress plugin standards, version bump rules, packaging checklist, capability checks
- `wp-site-builder` — WordPress and Elementor Hosting-specific patterns; update with any gotchas discovered

---

## Open items

- [ ] Consider writing a blog post about building this plugin (blog_candidate: true)
- [ ] Monitor Anthropic API costs now that the plugin is in active use

---

## Standing instructions for Claude

When Miriam says "wrap up," update all changed docs by writing them directly to their files. Do not output doc content as text for Miriam to copy-paste — write the files and confirm what was updated.

Always base wrap-up output on the files read at the start of the session.

1. **Changelog:** Append a new session entry — date, file-level changes (specific, not vague), decisions made, dead ends, what's next.

2. **Handoff doc:** If current state changed (version, status, open items, fragile things), update it.

3. **Decisions log:** If a new architectural decision was made, append a new entry — problem, decision, why, risks, conditions that could change it.

4. **Project context:** If architecture, file structure, or technical details changed, update it.

5. **plugin-builder skill:** If any WordPress or plugin-specific insight was discovered (gotcha, pattern, workaround), output the complete updated skill SKILL.md in full and package it as a `.skill` file for installation.

6. **wp-site-builder skill:** If any WordPress or Elementor Hosting-specific insight was discovered, output the complete updated skill SKILL.md in full and package it as a `.skill` file for installation.

7. **Version bump:** If a new plugin zip was produced this session, confirm the version number was incremented before packaging.

8. **Open items:** Update the open items section above.

9. **Frontmatter:** Update the `updated` field in every doc that changed.

10. **Session opener:** If anything changed, update this file.
