# The Subscriber Purge

**Contributors:** emrikol \
**Tags:** spam, subscribers, users, cleanup, automation \
**Requires at least:** 6.9 \
**Tested up to:** 6.9 \
**Requires PHP:** 8.4 \
**Stable tag:** 1.0.0 \
**License:** GPL-2.0-or-later \
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A simple WordPress plugin that automatically purges inactive subscriber accounts to help keep user spam down.

## Description

The Subscriber Purge automatically removes inactive subscriber accounts from your WordPress site to help combat spam registrations.

### Features

* **Automatic Purging**: Runs every 15 minutes via WordPress cron to remove inactive subscriber accounts (one user per run, oldest-first)
* **Configurable Inactivity Period**: Set how many days (1-365) a subscriber can be inactive before purging (default: 30 days)
* **Comment-Based Filtering**: Only purges subscribers who have never made any comments
* **Email Notifications**: Optional email notifications sent to users before their account is deleted
* **Simple Admin Interface**: Easy-to-use settings page in WordPress admin dashboard

### How It Works

1. The plugin is activated and sets up a 15-minute cron job
2. Every 15 minutes, the plugin checks subscriber accounts
3. It identifies subscribers who:
   * Have been registered for more than the configured number of days
   * Have never made any comments
4. The oldest matching account (one per run) is automatically deleted
5. If email notifications are enabled, the affected user receives a notification email before deletion

### Notes

* This plugin only affects subscriber accounts; administrators, editors, authors, and contributors are never deleted
* Only subscribers with zero comments are deleted
* The purge runs automatically every 15 minutes (one user per run, oldest-first)
* All settings are stored in the WordPress options table

## Installation

1. Upload the `the-subscriber-purge` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Subscriber Purge to configure the plugin

## Frequently Asked Questions

### Will this delete active users?

No. Only subscriber accounts with zero comments that have been registered longer than your configured inactivity period will be deleted.

### Can I stop the automatic purging?

Yes, simply deactivate the plugin. The cron job will be automatically removed.

### Does this delete other user roles?

No. Only subscribers are affected. Administrators, editors, authors, and contributors are never touched.

## Settings

### Days Inactive Before Purge

Set the number of days (1-365) after which inactive subscriber accounts will be deleted. Default: 30 days. An account is considered "inactive" if the subscriber has no comments.

### Send Email Notifications

Enable/disable email notifications sent to users before their account is deleted. When enabled, users receive an explanation of why their account was removed. Default: Enabled.

### Notify Admin on Purge

Enable/disable email notifications sent to the site administrator when a user is purged. When enabled, admins receive detailed information about the purged account. Default: Enabled.

## Changelog

### 1.0.0

* Initial release
* Automatic purging of inactive subscribers
* Configurable inactivity period
* Optional email notifications
* Admin settings page

## Support

For issues or questions, you're on your own. Good luck!

This plugin is provided as-is under the GPL-2.0-or-later license.
