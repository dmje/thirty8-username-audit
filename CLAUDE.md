# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A single-file WordPress **Multisite Network Admin plugin** (`thirty8-username-audit.php`) that identifies users with duplicate email addresses across a network. It is **read-only** — purely diagnostic, no data mutations.

- Requires `manage_network_users` capability
- Accessible via Network Admin > Duplicate Users
- Activate via Network Admin > Plugins

## No Build Process

Drop the single PHP file into a WordPress plugins directory. No build step, no dependencies, no Composer, no Node.

## Architecture

Everything lives in `thirty8-username-audit.php` across four functions:

| Function | Role |
|---|---|
| `t8ua_add_network_menu()` | Registers the Network Admin menu item |
| `t8ua_get_duplicate_users()` | Queries the DB for duplicate emails; returns grouped data structure |
| `t8ua_render_page()` | Renders stats, group tables, CSV export button, and inline CSS |
| `t8ua_export_csv()` | Triggered by `?export=csv`; streams a timestamped CSV download |

**Data flow:** `t8ua_get_duplicate_users()` returns an array of groups, each with an `email`, `count`, and `accounts` array (each account has `id`, `login`, `registered`, `sites[]`). `t8ua_render_page()` consumes this structure.

**DB queries** use `$wpdb->prepare()` with `%s` placeholders and a `GROUP BY user_email HAVING COUNT(*) > 1` pattern. All output is escaped with `esc_html()` / `esc_url()`. The plugin guards the CSV export with `current_user_can( 'manage_network_users' )`.

## Conventions

- Prefix all functions, hooks, and option keys with `t8ua_`
- Inline CSS is intentional (avoids enqueue overhead for a single admin page)
- "Oldest" account in each group is marked with a green label (first by registration date)
