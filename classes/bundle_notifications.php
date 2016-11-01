<?php
namespace adapt\notifications;

defined('ADAPT_STARTED') or die;

class bundle_notifications extends \adapt\bundle
{
    /**
     * bundle_notifications constructor.
     * @param \adapt\xml $data
     */
    public function __construct($data)
    {
        parent::__construct('notifications', $data);
    }

    public function boot()
    {
        if (parent::boot()) {

            // Attach this to base
            \adapt\base::extend('pget_notifications',
                function ($_this) {
                    $notifications = $_this->store('adapt.notifications');
                    if (!$notifications) {
                        $notifications = new \adapt\notifications\notifications();
                        $_this->store('adapt.notifications', $notifications);
                    }
                    return $notifications;
                }
            );

            return true;
        }
        return false;
    }

    public function install()
    {
        if (parent::install()) {
            return true;
        }
        return false;
    }
}