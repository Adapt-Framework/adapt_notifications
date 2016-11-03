# adapt_notifications

This is a generic bundle that will add the ability for the Adapt framework to handle notifications. It provides a data structure and methods to save a notification and view them. You will need to populate the `notification_type` table data from an application specific bundle and handle the creation of notification objects in application specific code. 

In the `notification_data` table, you can populate the `replace_key` field with a search key (such as '{{TITLE}}') and when a notification is raised it will search out the keys in the `notification_type_description.label` field and will replace the search key with the `notification_data.data_value`.

## To raise a notification

You can either;

Simply create one in code with

`$notification = new model_notification();`

Or use the helper provided in the `notifications.php` file, which is loaded into `$this->notifications` at application boot.

`$notification = $this->notifications->new_notification();`

This second method will fill in some sensible defaults for you.

## Adding application-specific handling

Due to how Adapt can be extended, it might be useful to add application-specific logic in your application code. Consider the following, from the `boot()` method of one of our application bundles;

    // Add extension methods on to the notification
    // Opportunity
    \adapt\notifications\model_notification::extend('add_opportunity',
        function ($_this, $opportunity) {
            $_this->data('opportunity_guid', $opportunity->guid);
            $_this->data('opportunity_id', $opportunity->opportunity_id);
            $_this->data('opportunity_name', $opportunity->label, '{{OPP_NAME}}');
        }
    );