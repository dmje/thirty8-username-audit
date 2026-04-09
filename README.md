# Thirty8 Duplicate Users

A WordPress Multisite Network Admin plugin that identifies users sharing the same email address across the network. Read-only — no data is modified.

## What it does

Scans the network user table for duplicate email addresses and presents them grouped by email, showing:

- User ID, username, and registration date
- Total posts and pages authored (across all sites)
- Sites the account has access to
- Which account is the oldest (likely the canonical one)

Summary stats at the top show how many duplicate groups exist and how many accounts need consolidating.

## Installation

1. Copy `thirty8-username-audit.php` into your `/wp-content/plugins/` directory
2. Network Activate it from **Network Admin → Plugins**
3. Access the tool at **Network Admin → Duplicate Users**

## Export

Use the **Export as CSV** button to download a full report, suitable for sharing with clients or using as a working document during a cleanup exercise.

## Notes

- Requires the `manage_network_users` capability
- Post/page counts include published content only, summed across all network sites
- On networks with a large number of sites, the page may be slow to load — this is expected
