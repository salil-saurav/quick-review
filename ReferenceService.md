# QuickReview ReferenceService - Developer Guide: Actions & Filters

This guide covers the available WordPress actions and filters in the QuickReview ReferenceService, allowing developers to extend and customize the reference system functionality.

## Available Actions

### 1. `qr_create_reference`

**Triggered when:** A new reference is being created for a comment.

**Parameters:**

-   `$comment_id` (int) - The comment ID
-   `$reference` (string) - The reference code

**Usage:** Use this action to create a reference programmatically or hook into the reference creation process.

### 2. `qr_after_reference_created`

**Triggered when:** After a reference has been successfully created and saved.

**Parameters:**

-   `$comment_id` (int) - The comment ID
-   `$reference` (string) - The reference code
-   `$campaign_data` (array) - Campaign data including:
    -   `reference` - The reference code
    -   `campaign_id` - Campaign ID
    -   `campaign_name` - Campaign name
    -   `item_status` - Item status
    -   `campaign_status` - Campaign status
    -   `start_date` - Campaign start date
    -   `end_date` - Campaign end date
    -   `post_id` - Associated post ID

**Usage:** Hook into this action for post-processing after successful reference creation.

### 3. `qr_reference_creation_failed`

**Triggered when:** Reference creation fails due to validation or other errors.

**Parameters:**

-   `$comment_id` (int) - The comment ID
-   `$reference` (string) - The attempted reference code
-   `$error_message` (string) - The error message

**Usage:** Hook into this action for error handling and logging.

## Helper Functions

### `qr_create_reference(int $comment_id, string $reference): bool`

```php
<?php

// Example 2: Using the convenience function
function convenience_function($comment_id) {

    // Generate UUID with custom max attempts
    $reference = 'reference_uuid';

    if (is_wp_error($reference)) {
        return [
            'success' => false,
            'error'   => $reference->get_error_message()
        ];
    }

    // Use the convenience function which internally calls do_action
    $result = qr_create_reference($comment_id, $reference);

    return [
        'success'   => $result,
        'reference' => $reference,
        'comment_id' => $comment_id
    ];
}
```

A global function to create references programmatically.

**Returns:** `true` on success, `false` on failure.

### Static Methods

-   `ReferenceService::create_reference(int $comment_id, string $reference): bool`
-   `ReferenceService::get_comment_reference_data(int $comment_id)`
-   `ReferenceService::is_reference_valid(string $reference): bool`

## Practical Examples

## Security Considerations

-   Always sanitize and validate data when hooking into these actions
-   Be cautious with user-provided data in the reference codes
-   Consider rate limiting for actions that might be triggered frequently
-   Log suspicious activities for security monitoring

This guide provides the foundation for extending QuickReview's reference system. The actions and helper functions allow for flexible integration with existing systems and custom functionality.
