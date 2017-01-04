<?php
namespace adapt\notifications;

use adapt\users\model_user;

defined('ADAPT_STARTED') or die;

class controller_notifications extends \adapt\controller
{
    protected $_page_count;
    protected $_row_count;
    protected $_page;
    protected $_items_per_page;
    protected $_offset;

    public function permission_view_api()
    {
        return $this->session->is_logged_in;
    }

    public function permission_view_notifications()
    {
        return $this->session->is_logged_in;
    }

    public function permission_action_mark_seen()
    {
        return $this->session->is_logged_in;
    }

    public function permission_action_mark_dismissed()
    {
        return $this->session->is_logged_in;
    }

    public function permission_action_mark_actioned()
    {
        return $this->session->is_logged_in;
    }

    /**
     * Gets a list of notifications
     * "seen": "true"|"false"
     * "actioned": "true"|"false"
     * @return string
     */
    public function view_notifications()
    {
        $this->content_type = 'application/json';

        // Work out the pages
        $this->sort_out_page();

        // Set up filters
        $where = new sql_and();
        $where->add(new sql_cond('n.date_deleted', sql::IS, new sql_null()));
        $where->add(new sql_cond('nr.date_deleted', sql::IS, new sql_null()));
        $where->add(new sql_cond('nt.date_deleted', sql::IS, new sql_null()));
        $where->add(new sql_cond('nr.user_id', sql::EQUALS, $this->session->user->user_id));

        // Filter seen/unseen
        if (isset($this->request['seen'])) {
            $where->add(new sql_cond('nr.seen', sql::EQUALS, $this->request['seen']));
        }

        // Filter dismissed
        if (isset($this->request['dismissed'])) {
            $where->add(new sql_cond('nr.dismissed', sql::EQUALS, $this->request['dismissed']));
        }

        // Filter actioned
        if (isset($this->request['actioned'])) {
            $where->add(new sql_cond('n.actioned', sql::EQUALS, $this->request['actioned']));
        }

        $select = [
            'notification_id' => 'n.notification_id',
            'raised_by_user_id' => 'n.raised_by_user_id',
            'actioned' => 'n.actioned',
            'actioned_by_user_id' => 'n.actioned_by_user_id',
            'actioned_at' => 'n.actioned_at',
            'body' => 'n.body',
            'body_language_id' => 'n.body_language_id',
            'body_language_code' => 'l.long_code',
            'recipient_user_id' => 'nr.user_id',
            'seen' => 'nr.seen',
            'seen_at' => 'nr.seen_at',
            'title' => 'nr.title',
            'type' => 'nt.name',
        ];

        $sql = $this->data_source->sql;
        $sql->select($select)
            ->from('notification', 'n')
            ->join('notification_type', 'nt', 'notification_type_id')
            ->join('notification_recipient', 'nr', 'notification_id')
            ->left_join(
                'language',
                'l',
                new sql_and(
                    new sql_cond('l.language_id', sql::EQUALS, 'n.body_language_id'),
                    new sql_cond('l.date_deleted', sql::IS, new sql_null())
                )
            )
            ->where($where)
            ->order_by('n.date_created', false);

        // Work out pagination
        $this->calculate_pages($sql);
        $sql->limit($this->_items_per_page, $this->_offset);

        $results = $sql->execute()->results();

        for ($i = 0; $i < count($results); $i++) {
            // Grab the data load
            $sql = $this->data_source->sql;
            $sql->select('*')
                ->from('notification_data')
                ->where(
                    new sql_and(
                        new sql_cond('notification_id', sql::EQUALS, $results[$i]['notification_id']),
                        new sql_cond('date_deleted', sql::IS, new sql_null())
                    )
                );
            $results[$i]['data'] = $sql->execute()->results();

            // Raiser
            if ($results[$i]['raised_by_user_id']) {
                $raiser = new model_user($results[$i]['raised_by_user_id']);
                $raiser = $raiser->contact->to_hash();
                unset($raiser['contact']['date_of_birth']);
                $results[$i]['raised_by_user'] = $raiser;
            } else {
                // Be kind to James
                $results[$i]['raised_by_user'] = [];
            }

            // Actioner
            if ($results[$i]['actioned_by_user_id']) {
                $actioner = new model_user($results[$i]['actioned_by_user_id']);
                $actioner = $actioner->contact->to_hash();
                unset($actioner['contact']['date_of_birth']);
                $results[$i]['actioned_by_user'] = $actioner;
            } else {
                $results[$i]['actioned_by_user'] = [];
            }

            // Recipient
            if ($results[$i]['recipient_user_id']) {
                $recipient = new model_user($results[$i]['recipient_user_id']);
                $recipient = $recipient->contact->to_hash();
                unset($recipient['contact']['date_of_birth']);
                $results[$i]['recipient_user'] = $recipient;
            } else {
                $results[$i]['recipient_user'] = [];
            }
        }

        // Build result
        $output = [
            'total_notifications' => $this->_row_count,
            'total_pages' => $this->_page_count,
            'page' => $this->_page,
            'items_per_page' => $this->_items_per_page,
            'notifications' => $results
        ];

        return json_encode($output);
    }

    /**
     * Marks a set of notifications as being seen
     */
    public function action_mark_seen()
    {
        // Sanity check
        if (!$this->request['notification_ids'] || !is_array($this->request['notification_ids']) || count($this->request['notification_ids']) == 0) {
            $this->respond('mark_seen', ['status' => 'bad_request', 'status_code' => 400, 'errors' => 'You must provide some notification IDs']);
            return;
        }

        // Integrity check
        foreach ($this->request['notification_ids'] as $id) {
            if (!is_numeric($id)) {
                $this->respond('mark_seen', ['status' => 'bad_request', 'status_code' => 400, 'errors' => 'All IDs provided must be numbers']);
                return;
            }
        }

        // Perform the update
        $sql = $this->data_source->sql;
        $sql->update('notification_recipient')
            ->set('seen', 'true')
            ->set('seen_at', new sql_now())
            ->where(
                new sql_and(
                    new sql_cond('date_deleted', sql::IS, new sql_null()),
                    new sql_cond('seen', sql::EQUALS, 'false'),
                    new sql_cond('seen_at', sql::IS, new sql_null()),
                    new sql_cond('user_id', sql::EQUALS, $this->session->user->user_id),
                    new sql_cond('notification_id', sql::IN, '(' . implode(',', $this->request['notification_ids']) . ')')
                )
            );
        $sql->execute();

        // Check for errors and report
        if (count($sql->errors()) > 0) {
            $this->respond('mark_seen', ['status' => 'failure', 'status_code' => 500, 'errors' => 'SQL server responded with an error', 'output' => $sql->errors(true)]);
        } else {
            $this->respond('mark_seen', ['status' => 'success', 'status_code' => 200, 'message' => 'Notifications were marked as seen']);
        }
    }

    /**
     * Marks a set of notifications as being dismissed
     */
    public function action_mark_dismissed()
    {
        // Sanity check
        if (!$this->request['notification_ids'] || !is_array($this->request['notification_ids']) || count($this->request['notification_ids']) == 0) {
            $this->respond('mark_dismissed', ['status' => 'bad_request', 'status_code' => 400, 'errors' => 'You must provide some notification IDs']);
            return;
        }

        // Integrity check
        foreach ($this->request['notification_ids'] as $id) {
            if (!is_numeric($id)) {
                $this->respond('mark_dismissed', ['status' => 'bad_request', 'status_code' => 400, 'errors' => 'All IDs provided must be numbers']);
                return;
            }
        }

        // Perform the update
        $sql = $this->data_source->sql;
        $sql->update('notification_recipient')
            ->set('dismissed', 'true')
            ->where(
                new sql_and(
                    new sql_cond('date_deleted', sql::IS, new sql_null()),
                    new sql_cond('dismissed', sql::EQUALS, 'false'),
                    new sql_cond('user_id', sql::EQUALS, $this->session->user->user_id),
                    new sql_cond('notification_id', sql::IN, '(' . implode(',', $this->request['notification_ids']) . ')')
                )
            );
        $sql->execute();

        // Check for errors and report
        if (count($sql->errors()) > 0) {
            $this->respond('mark_dismissed', ['status' => 'failure', 'status_code' => 500, 'errors' => 'SQL server responded with an error', 'output' => $sql->errors(true)]);
        } else {
            $this->respond('mark_dismissed', ['status' => 'success', 'status_code' => 200, 'message' => 'Notifications were marked as seen']);
        }
    }

    /**
     * Marks a notification as actioned
     */
    public function action_mark_actioned()
    {
        // Sanity check
        if (!$this->request['notification_ids'] || !is_array($this->request['notification_ids']) || count($this->request['notification_ids']) == 0) {
            $this->respond('mark_actioned', ['status' => 'bad_request', 'status_code' => 400, 'errors' => 'You must provide some notification IDs']);
            return;
        }

        // Integrity check
        foreach ($this->request['notification_ids'] as $id) {
            // Simple numeric check
            if (!is_numeric($id)) {
                $this->respond('mark_actioned', ['status' => 'bad_request', 'status_code' => 400, 'errors' => 'All IDs provided must be numbers']);
                return;
            }
        }

        // Load a list of outstanding notifications for *this* user - will prevent touching of other people's notifications
        $sql = $this->data_source->sql;
        $sql->select('n.notification_id as n_id')
            ->from('notification', 'n')
            ->join('notification_recipient', 'nr', 'notification_id')
            ->where(
                new sql_and(
                    new sql_cond('n.date_deleted', sql::IS, new sql_null()),
                    new sql_cond('nr.date_deleted', sql::IS, new sql_null()),
                    new sql_cond('nr.user_id', sql::EQUALS, $this->session->user->user_id),
                    new sql_cond('n.actioned', sql::EQUALS, 'false')
                )
            );
        $result = $sql->execute()->results();

        // Flatten array
        $n_ids = array();
        foreach ($result as $id) {
            $n_ids[] = $id['n_id'];
        }

        // Run the update
        $sql = $this->data_source->sql;
        $sql->update('notification')
            ->set('actioned', 'true')
            ->set('actioned_by_user_id', $this->session->user->user_id)
            ->set('actioned_at', new sql_now())
            ->where(
                new sql_and(
                    new sql_cond('date_deleted', sql::IS, new sql_null()),
                    new sql_cond('actioned_at', sql::IS, new sql_null()),
                    new sql_cond('actioned', sql::EQUALS, 'false'),
                    new sql_cond('notification_id', sql::IN, '(' . implode(',', $this->request['notification_ids']) . ')'),
                    new sql_cond('notification_id', sql::IN, '(' . implode(',', $n_ids) . ')')
                )
            );
        $sql->execute();

        // Check for errors and report
        if (count($sql->errors()) > 0) {
            $this->respond('mark_actioned', ['status' => 'failure', 'status_code' => 500, 'errors' => 'SQL server responded with an error', 'output' => $sql->errors(true)]);
        } else {
            $this->respond('mark_actioned', ['status' => 'success', 'status_code' => 200, 'message' => 'Notifications were marked as actioned']);
        }
    }

    /**
     * View for returning results as an API response
     * @return string
     */
    public function view_api()
    {
        $this->content_type = 'application/json';

        // Check for a response object
        if ($this->response) {
            $last = 0;
            foreach ($this->response as $response) {
                if (isset($response['status']) && is_numeric($response['status']) && $response['status'] > $last) {
                    $last = $response['status'];
                }
            }

            // Check for a flat object
            if (isset($this->response['status']) && is_numeric($this->response['status']) && $this->response['status'] > $last) {
                $last = $this->response['status'];
            }

            // Set the highest response code found
            if ($last > 0) {
                http_response_code($last);
            }

            // Return the response
            return json_encode($this->response);
        }

        // If no response has been found, return empty
        return '[]';
    }

    /**
     * Sets the page requested and page size
     */
    protected function sort_out_page()
    {
        if (isset($this->request['page']) && is_numeric($this->request['page'])) {
            $this->_page = $this->request['page'];
        } else {
            $this->_page = 1;
        }

        if (isset($this->request['items_per_page']) && is_numeric($this->request['items_per_page'])) {
            $this->_items_per_page = $this->request['items_per_page'];
        } else {
            $this->_items_per_page = 20;
        }
    }

    /**
     * Works out the page offset
     * @param \adapt\sql $sql
     */
    protected function calculate_pages($sql)
    {
        $count = $this->data_source->sql;
        $count->select('count(*) as c')
            ->from($sql, 'c');

        $this->_row_count = $count->execute()->results()[0]['c'];

        if ($this->_row_count > 0 && $this->_items_per_page > 0) {
            $this->_page_count = ceil($this->_row_count / $this->_items_per_page);
        } else {
            $this->_page_count = 0;
        }

        $this->_offset = ($this->_items_per_page * ($this->_page - 1));
    }
}