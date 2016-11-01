<?php
namespace adapt\notifications;

defined('ADAPT_STARTED') or die;

class controller_notifications extends \adapt\controller
{

    public function permission_view_test()
    {
        return $this->session->is_logged_in;
    }

    public function view_test()
    {
        $this->content_type = 'application/json';
        $notification = $this->notifications->new_notification();
        $notification_type = new model_notification_type();
        $notification_type->load_by_name('friendship_offered');
        $notification->notification_type_id = $notification_type->notification_type_id;

        $organisation = new model_organisation();
        $organisation->load_by_name('test1');

        $notification->data('to_org_name', $organisation->label);
        $notification->data('to_org_guid', $organisation->guid);
        $notification->data('to_org_id', $organisation->organisation_id);

        $notification->data('from_org_name', $this->organisation->label, '{{ORG_NAME}}');
        $notification->data('from_org_guid', $this->organisation->guid);
        $notification->data('from_org_id', $this->organisation->organisation_id);

        $notification->set_recipients_by_user_ids(\delio\horizon\users\users::get_users_by_permission(['PERM_VIEW_OWN_USER'], 1));
        $notification->save();

        return json_encode($notification->to_hash());
    }
}