# QuickReview CampaignService

## Table of Contents

-   [Creating Campaigns](#creating-campaigns)
-   [Available Actions](#available-actions)
-   [Helper Methods](#helper-methods)
-   [Error Handling](#error-handling)
-   [Examples](#examples)
-   [Campaign Data Structure](#campaign-data-structure)

## Creating Campaigns

### Method 1: Using the Action Hook

```php
// Create a campaign using the action hook
do_action('qr_create_campaign', [
    'campaign_name' => 'Summer Product Review Campaign',
    'start_date' => '2024-06-01',
    'end_date' => '2024-08-31',
    'status' => 'active',
    'post_id' => 123
]);
```

### Method 2: Using the Static Method

```php
// Create a campaign using the static method
try {
    $campaign_id = \QuickReview\CampaignService::create([
        'campaign_name' => 'Holiday Review Campaign',
        'start_date'    => '2024-12-01',
        'end_date'      => '2024-12-31',
        'status'        => 'active',
        'post_id'       => 456
    ]);

    echo 'Created campaign with ID: ' . $campaign_id;
} catch (Exception $e) {
    error_log('Failed to create campaign: ' . $e->getMessage());
}
```

## Available Actions

### `qr_create_campaign`

Programmatically create a campaign.

**Parameters:**

-   `$args` (array): Array containing campaign details

**Required Fields:**

-   `campaign_name` (string): Name of the campaign
-   `start_date` (string): Campaign start date (Y-m-d format)
-   `status` (string): Campaign status ('active', 'draft', 'inactive')
-   `post_id` (int): The WordPress post ID

**Optional Fields:**

-   `end_date` (string): Campaign end date (Y-m-d format)

**Example:**

```php
// Basic campaign creation
do_action('qr_create_campaign', [
    'campaign_name' => 'Q4 Product Reviews',
    'start_date'    => '2024-10-01',
    'end_date'      => '2024-12-31',
    'status'        => 'active',
    'post_id'       => 789
]);

// Campaign without end date (ongoing)
do_action('qr_create_campaign', [
    'campaign_name' => 'Ongoing Product Feedback',
    'start_date'    => date('Y-m-d'),
    'status'        => 'active',
    'post_id'       => 101
]);
```

### `qr_after_campaign_created`

Fired after a campaign is successfully created.

**Parameters:**

-   `$campaign_id` (int): The ID of the created campaign
-   `$args` (array): The original arguments passed to the creation method

**Example:**

```php
add_action('qr_after_campaign_created', function($campaign_id, $args) {
    // Log campaign creation
    error_log(sprintf(
        'Campaign "%s" created with ID %d for post %d',
        $args['campaign_name'],
        $campaign_id,
        $args['post_id']
    ));

}, 10, 2);
```

## Helper Methods

### `CampaignService::create()`

A static method for creating campaigns programmatically.

```php
/**
 * @param array $args Campaign data
 * @return int Campaign ID
 * @throws Exception On validation or creation failure
 */
$campaign_id = \QuickReview\CampaignService::create($args);
```

## Error Handling

The service throws `Exception` objects for various failure scenarios:

```php
try {
    $campaign_id = \QuickReview\CampaignService::create([
        'campaign_name' => 'Test Campaign',
        'start_date' => '2024-01-01',
        'status' => 'active',
        'post_id' => 123
    ]);
} catch (Exception $e) {
    $error_code = $e->getCode();
    $error_message = $e->getMessage();

    switch ($error_code) {
        case 400:
            // Validation error
            error_log('Campaign validation failed: ' . $error_message);
            break;
        case 500:
            // Database error
            error_log('Campaign creation failed: ' . $error_message);
            break;
        default:
            error_log('Unknown error: ' . $error_message);
            break;
    }
}
```

## Campaign Data Structure

### Required Fields

-   **campaign_name** (string): Display name for the campaign
-   **start_date** (string): Campaign start date in 'Y-m-d' format
-   **status** (string): Current status ('active', 'draft', 'inactive')
-   **post_id** (int): WordPress post ID this campaign belongs to

### Optional Fields

-   **end_date** (string): Campaign end date in 'Y-m-d' format (null for ongoing campaigns)

### Database Schema

The campaigns are stored in the `{prefix}_qr_review_campaign` table with these columns:

-   `id` (int): Primary key
-   `campaign_name` (varchar): Campaign name
-   `start_date` (date): Start date
-   `end_date` (date): End date (nullable)
-   `status` (varchar): Current status
-   `post_id` (int): Associated post ID
-   `created_at` (datetime): Creation timestamp

## Security Considerations

-   AJAX handlers include nonce verification and permission checks
-   All inputs are properly sanitized using WordPress functions
-   Database operations use prepared statements to prevent SQL injection
-   Duplicate detection prevents accidental campaign duplication
-   Date validation ensures proper format and logical date ranges
