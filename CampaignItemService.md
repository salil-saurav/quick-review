# QuickReview CampaignItemService Developer Guide

The CampaignItemService class provides developers with programmatic ways to create and manage review campaign items (review URLs) for WordPress posts. This guide covers all available hooks, filters, and methods.

## Table of Contents

-   [Creating Campaign Items](#creating-campaign-items)
-   [Available Filters](#available-filters)
-   [Available Actions](#available-actions)
-   [Helper Methods](#helper-methods)
-   [Error Handling](#error-handling)
-   [Examples](#examples)

## Creating Campaign Items

### Method 1: Using the Action Hook

```php
// Create a campaign item using the action hook
do_action('qr_create_campaign_item', [
    'post_id' => 123,
    'campaign_id' => 456 // Optional
]);
```

### Method 2: Using the Static Method

```php
// Create a campaign item using the static method
$result = \QuickReview\CampaignItemService::create_for_post(123, 456);

if (is_wp_error($result)) {
    // Handle error
    error_log('Failed to create campaign item: ' . $result->get_error_message());
} else {
    // Success - $result contains the reference UUID
    echo 'Created campaign item with reference: ' . $result;
}
```

## Available Filters

### `modify_campaign_item`

Allows modification of the final review URL before it's stored in the database.

**Parameters:**

-   `$review_url` (string): The generated review URL
-   `$uuid` (string): The unique reference UUID
-   `$post_id` (int): The post ID

**Example:**

```php
add_filter('modify_campaign_item', function($review_url, $uuid, $post_id) {
    // Add custom tracking parameters
    $review_url = add_query_arg([
        'utm_source' => 'review_campaign',
        'utm_medium' => 'email',
        'utm_campaign' => 'product_review_' . $post_id
    ], $review_url);

    // Add custom domain for review URLs
    $review_url = str_replace(home_url(), 'https://reviews.yourdomain.com', $review_url);

    return $review_url;
}, 10, 3);
```

## Available Actions

### `qr_create_campaign_item`

Programmatically create a campaign item.

**Parameters:**

-   `$args` (array): Array containing post_id and optional campaign_id

**Example:**

```php
// Simple usage
do_action('qr_create_campaign_item', ['post_id' => 123]);

// With specific campaign
do_action('qr_create_campaign_item', [
    'post_id' => 123,
    'campaign_id' => 456
]);
```

### `qr_after_campaign_item_created`

Fired after a campaign item is successfully created.

**Parameters:**

-   `$result` (array): The creation result containing reference, review_campaign_item, and campaign_id
-   `$args` (array): The original arguments passed to the creation method

**Example:**

```php
add_action('qr_after_campaign_item_created', function($result, $args) {
    // Log the creation
    error_log(sprintf(
        'Campaign item created for post %d with reference %s',
        $args['post_id'],
        $result['reference']
    ));
}, 10, 2);
```

## Helper Methods

### `CampaignItemService::create_for_post()`

A convenient static method for creating campaign items.

```php
/**
 * @param int $post_id The post ID
 * @param int $campaign_id Optional specific campaign ID
 * @return string|WP_Error The reference UUID or WP_Error on failure
 */
$result = \QuickReview\CampaignItemService::create_for_post($post_id, $campaign_id);
```

## Error Handling

The service returns `WP_Error` objects for various failure scenarios:

```php
$result = \QuickReview\CampaignItemService::create_for_post(123);

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();

    switch ($error_code) {
        case 'invalid_post':
            // Handle invalid post ID
            break;
        case 'campaign_item_creation_failed':
            // Handle creation failure
            break;
        default:
            // Handle other errors
            break;
    }
}
```

## Examples

### Example 1: Auto-create Review URLs for New Products

```php
// Auto-create review URLs when a new product is published
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish' && $post->post_type === 'product') {
        do_action('qr_create_campaign_item', ['post_id' => $post->ID]);
    }
}, 10, 3);
```

### Example 2: Bulk Create Review URLs

```php
function create_review_urls_for_category($category_id) {
    $posts = get_posts([
        'category' => $category_id,
        'numberposts' => -1,
        'post_status' => 'publish'
    ]);

    $results = [];

    foreach ($posts as $post) {
        $result = \QuickReview\CampaignItemService::create_for_post($post->ID);

        if (!is_wp_error($result)) {
            $results[] = [
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'reference' => $result
            ];
        }
    }

    return $results;
}
```

### Example 3: Custom Review URL Structure

```php
// Modify review URLs to use a subdomain
add_filter('modify_campaign_item', function($review_url, $uuid, $post_id) {
    // Parse the original URL
    $parsed_url = parse_url($review_url);

    // Create custom review URL structure
    $custom_url = sprintf(
        'https://review.%s/r/%s',
        str_replace(['http://', 'https://', 'www.'], '', home_url()),
        $uuid
    );

    return $custom_url;
}, 10, 3);
```

## Security Considerations

-   The AJAX handlers include permission checks (`current_user_can('manage_options')`)
-   All database inputs are properly sanitized and use prepared statements
-   The service validates post existence before creating campaign items
-   UUIDs are generated with collision detection

## Database Schema

The service works with two main tables:

-   `wp_qr_review_campaign`: Stores campaign information
-   `wp_qr_review_campaign_item`: Stores individual review URLs/items

Make sure these tables exist in your database before using the service.
