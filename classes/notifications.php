<?php
namespace adapt\notifications;

defined('ADAPT_STARTED') or die;

class notifications extends \adapt\base
{
    public function get_notifications($status)
    {

    }

    public function new_notification()
    {
        $notification = new \adapt\notifications\model_notification();
        $notification->raised_by_user_id = $this->session->user->user_id;
        $notification->actioned = false;
        return $notification;
    }
}