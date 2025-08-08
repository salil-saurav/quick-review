## Campaign and Review Link Creation Workflow

### Questions for Clarification

-   Should we keep the current URL creation system as it is and build a separate campaign creation system, or do you want to modify the existing URL creation system?
-   When creating a campaign, we need to assign a name. Do you have any preferences or guidelines for naming campaigns?

### Proposed Workflow

#### 1. Campaign Creation

-   The "Create Unique Review Link" button will be replaced with a "Create New Campaign" button.
-   Clicking "Create New Campaign" will open a form with the following fields:
    1. **Start Date**
    2. **End Date**
    3. **Status** (default: Draft)
    4. **Post ID** (select from available posts)

#### 2. Campaign Dashboard

-   The main dashboard will display a table with these columns:

    -   S/No
    -   Campaign ID (unique)
    -   Start Date
    -   End Date
    -   Status

-   Clicking on a campaign row will take the admin to that campaign’s dashboard, which includes a table with:
    -   S/No
    -   Reference
    -   Count
    -   Review URL
    -   Copy Button

#### 3. Review Link Creation

-   There will be a "Create Review URL" button within each campaign dashboard.
-   Clicking this button will generate a unique review link with a reference number.

#### 4. Review Tracking

-   When a user submits a review using the generated review link, the reference value will be saved as reference meta data.
-   This reference meta will be used later for review tracking and reporting.

```php

add_filter('qr_review_url', function($review_url, $reference, $post_id) {
    // Example: Add UTM parameters
    $utm_params = [
        'utm_source' => 'quickreview',
        'utm_medium' => 'email',
        'utm_campaign' => 'review_request'
    ];

    $separator = strpos($review_url, '?') !== false ? '&' : '?';
    return $review_url . $separator . http_build_query($utm_params);
}, 10, 3);
```
