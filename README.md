# DragonZapAffiliateWordPressPlugin

WordPress plugin that integrates with the Dragon Zap Affiliate API.

## Features

* Store and validate your Dragon Zap Affiliate API credentials from the WordPress admin settings page.
* Optionally append a "Recommended Courses" widget to single blog posts using the Dragon Zap Affiliate product search.
* Register both a classic widget and a block editor widget so the related courses list can be repositioned or removed entirely.

## Related Courses Widget

The plugin analyses the current blog post title, tags, and categories to query the Dragon Zap Affiliate API for up to three matching courses. Results are cached for 12 hours per post to minimise API calls and are refreshed automatically when the post is updated.

Widget output includes the course title, featured image, price, and a short description with affiliate tracking links. Styling is provided via the bundled `assets/css/related-courses.css` file and adapts to the context when displayed in content or a sidebar. You can customise the widget appearance (colours, visibility of images/descriptions/prices, and additional CSS classes) from either the Widgets screen or the block inspector controls.

In block-based themes or the Widgets screen, add the **Dragon Zap Related Courses** block to any widget area. The block provides controls to toggle the heading, switch on or off specific course details, adjust colours, and supply custom CSS classes while rendering the same content as the classic widget on the front end. A new setting in **Settings → Dragon Zap Affiliate** lets you disable the automatic placement so you can position the widget or block exactly where you need it.
