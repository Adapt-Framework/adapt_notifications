<?php
namespace adapt\notifications;

defined('ADAPT_STARTED') or die;

class model_notification extends \adapt\model
{
    /**
     * model_notification constructor.
     * @param null $id
     * @param null $data_source
     */
    public function __construct($id = null, $data_source = null)
    {
        parent::__construct('notification', $id, $data_source);
    }

    /**
     * Over-ride the initialiser to auto load children
     */
    public function initialise()
    {
        /* We must initialise first! */
        parent::initialise();

        /* We need to limit what we auto load */
        $this->_auto_load_only_tables = array(
            'notification_recipient',
            'notification_data'
        );

        /* Switch on auto loading */
        $this->_auto_load_children = true;
    }

    /**
     * Sets the recipients list
     * @param array $recipients
     */
    public function set_recipients_by_user_ids(array $recipients)
    {
        foreach ($recipients as $recipient) {
            if (is_numeric($recipient)) {
                $model = new model_notification_recipient();
                $model->user_id = $recipient;
                $model->seen = false;
                $model->dismissed = false;
                $this->add($model);
            }
        }
    }

    /**
     * @param string $key
     * @param null|string $value
     * @param null|string $replace_key
     * @param null|integer $data_type
     * @return \adapt\model|null
     */
    public function data($key, $value = null, $replace_key = null, $data_type = null)
    {
        $children = $this->get();

        if (is_null($value)) {
            foreach($children as $child) {
                if ($child instanceof \adapt\model && $child->table_name == 'notification_data') {
                    if ($child->data_key == $key) {
                        return $child;
                    }
                }
            }

        }else{
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'notification_data') {
                    if ($child->data_key == $key) {
                        $child->data_value = $value;
                        $child->data_type_id = $data_type;
                        $child->replace_key = $replace_key;

                        return null;
                    }
                }
            }

            /* We didn't find the setting, so let create a new one */
            $setting = new model_notification_data();
            $setting->data_key = $key;
            $setting->data_value = $value;
            $setting->data_type_id = $data_type;
            $setting->replace_key = $replace_key;

            $this->add($setting);
        }

        return null;
    }

    /**
     * Saves the record
     * @return bool
     */
    public function save()
    {
        if ($this->is_loaded) {
            // Existing record - no processing to do
            return parent::save();
        }

        // This is a new record - need to do processing
        $children = $this->get();

        // Grab the user IDs for the recipients
        $user_ids = array();
        foreach ($children as $child) {
            if ($child instanceof \adapt\model && $child->table_name == 'notification_recipient') {
                $user_ids[] = $child->user_id;
            }
        }

        // Grab the language packs by the user IDs
        $sql = $this->data_source->sql;
        $sql->select(
                'ntd.label as label',
                'u.user_id as user_id'
            )
            ->from('user', 'u')
            ->join(
                'contact',
                'c',
                new sql_and(
                    new sql_cond('u.contact_id', sql::EQUALS, 'c.contact_id'),
                    new sql_cond('c.date_deleted', sql::IS, new sql_null())
                )
            )
            ->join(
                'notification_type_description',
                'ntd',
                new sql_and(
                    new sql_cond('ntd.language_id', sql::EQUALS, 'c.language_id'),
                    new sql_cond('ntd.date_deleted', sql::IS, new sql_null())
                )
            )
            ->where(
                new sql_and(
                    new sql_cond('u.date_deleted', sql::IS, new sql_null()),
                    new sql_cond('u.user_id', sql::IN, '(' . implode(',', $user_ids) . ')'),
                    new sql_cond('ntd.notification_type_id', sql::EQUALS, $this->notification_type_id)
                )
            );

        $results = $sql->execute()->results();
        $strings = array();
        foreach ($results as $result) {
            $strings[$result['user_id']] = $result['label'];
        }

        // Go through the recipients and set the titles
        foreach ($children as $child) {
            if ($child instanceof \adapt\model && $child->table_name == 'notification_recipient') {
                $string = $strings[$child->user_id];
                foreach ($children as $data) {
                    if ($data instanceof \adapt\model && $data->table_name == 'notification_data' && $data->replace_key) {
                        $string = str_replace($data->replace_key, $data->data_value, $string);
                    }
                }
                $child->title = $string;
            }
        }

        // Save the record
        return parent::save();
    }
}