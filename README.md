# QuickReview - Developer Guide

The `ReviewService` class provides actions and filters to help developers programmatically create, modify, and handle review URLs in WordPress.

This guide covers:

-   Available **Actions**
-   Available **Filters**
-   Example Usage

---

## 📌 Actions

### 1. `qr_create_review_url`

Triggered when you want to create a review URL programmatically **from code**.

**Example:**

```php
/**
 * Programmatically create a review URL for a post
 */
$args = [
    'post_id'     => 123,  // Required: ID of the post
    'campaign_id' => 0     // Optional: Specific campaign ID
];

do_action('qr_create_review_url', $args);

// example case

use QuickReview\ReviewService;

$reference = ReviewService::create_for_post(123);
if (!is_wp_error($reference)) {
    echo "Review Reference: " . esc_html($reference);
} else {
    echo "Error: " . $reference->get_error_message();
}

/**
 * Do something after a review URL is created
 */
add_action('qr_after_review_url_created', function ($result, $args) {
    // $result contains 'reference', 'review_url', 'campaign_id'
    // $args contains the original arguments passed

    error_log("New Review Created: " . $result['reference']);
    error_log("Review URL: " . $result['review_url']);

    // Example: send notification to admin
    wp_mail(
        get_option('admin_email'),
        'New Review URL Created',
        'Review URL: ' . $result['review_url']
    );
}, 10, 2);


/**
 * Append a tracking parameter to every review URL
 */
add_filter('qr_review_url', function ($review_url, $uuid, $post_id) {
    return add_query_arg('utm_source', 'quickreview', $review_url);
}, 10, 3);


use QuickReview\ReviewService;

// 1️⃣ Create a review URL programmatically
$reference = ReviewService::create_for_post(456);

if (!is_wp_error($reference)) {
    // 2️⃣ Modify the URL via filter
    add_filter('qr_review_url', function ($url, $uuid, $post_id) {
        return $url . '&ref=affiliate123';
    }, 10, 3);

    // 3️⃣ React after URL creation
    add_action('qr_after_review_url_created', function ($result) {

    }, 10, 1);
} else {
    error_log('Error creating review URL: ' . $reference->get_error_message());
}

```
