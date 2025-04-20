=== Social Bridge ===
Contributors: terriblestudio, chazz0x0
Tags: social media
Tested up to: 6.8
Stable tag: 0.1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Social Bridge integrates WordPress with social media platforms and allows for comment synchronization.

== Description ==

Social Bridge is a WordPress plugin that connects your website with social media platforms, importing comments, likes, and shares from social posts to display them on your WordPress site.

### Features

- Integrates WordPress with Bluesky and Mastodon
- Supports plugin extensions for additional platforms
- Imports comments, likes, and shares from social media posts
- Displays social interactions as WordPress comments
- Shows shares as pingbacks
- Includes a block and shortcode to display user like avatars
- Periodic synchronization via WordPress cron
- Custom meta boxes in post editors to link social media posts

## Configuration

### Bluesky Integration

1. Go to Settings → Social Bridge and click on the Bluesky tab
2. Enter your Bluesky handle (e.g., username.bsky.social)
3. Generate an App Password in your Bluesky account settings and enter it in the plugin settings
4. Save changes

### Mastodon Integration

1. Go to Settings → Social Bridge and click on the Mastodon tab
2. Enter your Mastodon instance URL (e.g., https://mastodon.social)
3. Generate an access token in your Mastodon preferences under Development → Applications
4. Enter the access token in the plugin settings
5. Save changes

## Usage

### Connecting Social Media Posts

1. When creating or editing a post/page, you'll see platform-specific meta boxes in the sidebar
2. Paste the URL of your social media post in the appropriate field
3. Update the post
4. The plugin will automatically sync comments, likes, and shares from the social media post

### Manual Sync

1. Edit a post that has connected social media posts
2. In the Social Interactions meta box, click the Sync button next to a platform
3. The plugin will immediately import any new interactions

### Comment Appearance

In Settings → Social Bridge, you can configure how social media comments appear:

- Fully integrated: Comments look like native WordPress comments
- Styled differently: Comments retain their social media identity but appear in the same section
- Separate section: Social media comments appear below WordPress comments

## Extending the Plugin

Social Bridge supports extensions for additional social media platforms. Developers can create add-on plugins that register new platform integrations using the provided API.

To create an extension:

1. Create a new plugin that depends on Social Bridge
2. Create a class that extends `Social_Bridge_Integration`
3. Implement the required abstract methods
4. Register your integration using the `social_bridge_integrations` filter

Example:

```php
// Register a new integration
function my_plugin_register_integration($integrations) {
    $integrations['twitter'] = new My_Twitter_Integration();
    return $integrations;
}
add_filter('social_bridge_integrations', 'my_plugin_register_integration');
```

== Installation ==

1. Upload the `social-bridge` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Social Bridge to configure the plugin and connect your social media accounts

== Frequently Asked Questions ==

= Will this plugin work with any social media platform? =

The core plugin supports Bluesky and Mastodon. Additional platforms can be added via extension plugins.

= How often are comments synced? =

By default, the plugin syncs comments hourly. You can change this in the plugin settings to sync twice daily, daily, or weekly.

= Does this affect my site's performance? =

The plugin uses WordPress cron for synchronization, so it won't impact your site's frontend performance. The sync tasks run in the background.

= Can I display comments from multiple platforms? =

Yes, the plugin can display comments from all connected platforms on your posts.Yes, the plugin can display comments from all connected platforms on your posts.

== Changelog ==

= 0.1.0 =

Initial release, core comment syncing functionality works but is not feature complete.

Updates:

* Initial release
* Bluesky and Mastodon integrations
* Comment sync functionality
* Partial boost/retweet sync functionality

In the works:

* Improve feature parity across integrations
* Investigate known issues with Likes sync

View the full list of bug reports and feature requests [on Github](https://github.com/terriblestudio/social-bridge/issues).