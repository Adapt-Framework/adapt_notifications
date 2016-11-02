<?php
namespace adapt\notifications;

defined('ADAPT_STARTED') or die;

class model_notification_data extends \adapt\model
{
    public function __construct($id = null, $data_source = null)
    {
        parent::__construct('notification_data', $id, $data_source);
    }
}