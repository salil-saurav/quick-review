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
$result = \QuickReview\CampaignItemService::qr_create_for_post(123, 456);

if (is_wp_error($result)) {
    // Handle error
    error_log('Failed to create campaign item: ' . $result->get_error_message());
} else {
    // Success - $result contains the reference UUID
    echo 'Created campaign item with reference: ' . $result;
}
```

## Available Filters

### `qr_modify_campaign_item`

Allows modification of the final review URL before it's stored in the database.

**Parameters:**

-   `$review_url` (string): The generated review URL
-   `$uuid` (string): The unique reference UUID
-   `$post_id` (int): The post ID

**Example:**

```php
add_filter('qr_modify_campaign_item', function($review_url, $uuid, $post_id) {
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

### Example 3: Custom Review URL Structure

```php
// Modify review URLs to use a subdomain
add_filter('qr_modify_campaign_item', function($review_url, $uuid, $post_id) {
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

Two filters are exposed:

-   `qr_get_campaigns_by_post_ids`
-   `qr_get_campaign_items_by_campaign_id`

Both filters return results directly from the database, with support for pagination, filtering, and error handling.

---

## 1. Fetch Campaigns by Post IDs

**Hook:**

```php
apply_filters('qr_get_campaigns_by_post_ids', $post_ids, $options);

/*
Parameters
$post_ids (array, required)
Array of WordPress post IDs linked to campaigns.

$options (array, optional)
Available keys:

page (int) – Page number (default: 1)
per_page (int) – Number of results per page (default: 10)
offset (int|null) – Manual offset (overrides page)
start_date (string) – Filter by start date (Y-m-d)
end_date (string) – Filter by end date (Y-m-d)
status (string) – Campaign status filter
order_by (string) – Allowed: id, campaign_name, post_id, status, created_at, updated_at, start_date, end_date
order (string) – ASC or DESC (default: DESC)
count_only (bool) – Return total count instead of results (default: false)
*/

$post_ids = [101, 102, 103];
$options = [
    'per_page'   => 5,
    'order_by'   => 'campaign_name',
    'order'      => 'ASC',
    'status'     => 'active'
];

$campaigns = apply_filters('qr_get_campaigns_by_post_ids', $post_ids, $options);

if (is_wp_error($campaigns)) {
    error_log('Error: ' . $campaigns->get_error_message());
} else {
    echo '<pre>';
    print_r($campaigns);
    echo '</pre>';
}
```

## Fetch Campaign Items by Campaign ID

```php

apply_filters('qr_get_campaign_items_by_campaign_id', $campaign_id, $options);
/*
Parameters
$campaign_id (int, required)
ID of the campaign.

$options (array, optional)
Available keys:

page (int) – Page number (default: 1)
per_page (int) – Number of results per page (default: 10)
offset (int|null) – Manual offset (overrides page)
status (string) – Item status filter
start_date (string) – Start date filter (Y-m-d)
end_date (string) – End date filter (Y-m-d)
order_by (string) – Allowed: id, reference, campaign_id, status, created_at, count
order (string) – ASC or DESC (default: DESC)
count_only (bool) – Return only count (default: false)
include_stats (bool) – Include usage stats (count column)
*/

$campaign_id = 25;
$options = [
    'per_page'      => 10,
    'order_by'      => 'created_at',
    'order'         => 'DESC',
    'include_stats' => true
];

$items = apply_filters('qr_get_campaign_items_by_campaign_id', $campaign_id, $options);

if (is_wp_error($items)) {
    error_log('Error: ' . $items->get_error_message());
} else {
    echo '<pre>';
    print_r($items);
    echo '</pre>';
}
```
