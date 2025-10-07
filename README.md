# DragonZapAffiliateWordPressPlugin

WordPress plugin that integrates with the Dragon Zap Affiliate API.

## Features

* Store and validate your Dragon Zap Affiliate API credentials from the WordPress admin settings page.
* Automatically append a "Recommended Courses" widget to single blog posts using the Dragon Zap Affiliate product search.
* Register both a classic widget and a block editor widget so the related courses list can be repositioned in any widget area.

## Related Courses Widget

The plugin analyses the current blog post title, tags, and categories to query the Dragon Zap Affiliate API for up to three matching courses. Results are cached for 12 hours per post to minimise API calls and are refreshed automatically when the post is updated.

Widget output includes the course title, featured image, price, and a short description with affiliate tracking links. Styling is provided via the bundled `assets/css/related-courses.css` file and adapts to the context when displayed in content or a sidebar.

In block-based themes or the Widgets screen, add the **Dragon Zap Related Courses** block to any widget area. The block provides simple controls to toggle and customise the widget heading, and renders the same content as the classic widget on the front end.
