# adapt_notifications

This is a generic bundle that will add the ability for the Adapt framework to handle notifications. It provides a data structure and methods to save a notification and view them. You will need to populate the `notification_type` table data from an application specific bundle and handle the creation of notification objects in application specific code. 

In the `notification_data` table, you can populate the `replace_key` field with a search key (such as '{{TITLE}}') and when a notification is raised it will search out the keys in the `notification_type_description.label` field and will replace the search key with the `notification_data.data_value`.