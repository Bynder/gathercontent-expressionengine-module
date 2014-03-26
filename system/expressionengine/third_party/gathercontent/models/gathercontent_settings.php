<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GatherContent Import Module
 *
 * @package     GatherContent Import
 * @author      Mathew Chapman
 * @copyright   Copyright (c) 2013 GatherContent
 * @link        http://www.gathercontent.com
 */

class Gathercontent_settings extends CI_Model {

    var $settings = array();

    var $site_id = 1;

    function __construct()
    {
        parent::__construct();
        $this->site_id = ee()->config->item('site_id');
    }

    public function get($option_name, $default = '')
    {
        if(isset($this->settings[$option_name]))
        {
            return $this->settings[$option_name];
        }
        else
        {
            $val = $this->_get($option_name);
            if($val === FALSE)
                $this->settings[$option_name] = $default;
            else
                $this->settings[$option_name] = $val;
            return $this->settings[$option_name];
        }
    }

    public function get_array($option_name)
    {
        $val = $this->get($option_name);
        if(!is_array($val))
        {
            return FALSE;
        }
        return $val;
    }

    private function _get($option_name)
    {
        ee()->db->select('option_value')
                ->where('site_id', $this->site_id)
                ->where('option_name', $option_name);
        $result = ee()->db->get('gathercontent_settings');
        if($result->num_rows() > 0)
        {
            $row = $result->result();
            $val = $row[0]->option_value;
            $val = base64_decode($val);
            $val = @unserialize($val);
            if(!is_array($val))
            {
                $val = $row[0]->option_value;
            }
            return $val;
        }
        return FALSE;
    }

    public function update($key, $val=NULL)
    {
        if(is_null($val))
        {
            foreach($key as $name => $val)
            {
                $this->update($name, $val);
            }
        }
        else
        {
            $this->settings[$key] = $val;
            if(is_array($val))
            {
                $val = base64_encode(serialize($val));
            }
            ee()->db->select('option_value')
                    ->where('site_id', $this->site_id)
                    ->where('option_name', $key);
            if(ee()->db->count_all_results('gathercontent_settings') > 0)
            {
                $data = array('option_value' => $val);
                $where = array(
                    'site_id' => $this->site_id,
                    'option_name' => $key,
                );
                ee()->db->update('gathercontent_settings', $data, $where);
            }
            else
            {
                $where = array(
                    'site_id' => $this->site_id,
                    'option_name' => $key,
                    'option_value' => $val,
                );
                ee()->db->insert('gathercontent_settings', $where);
            }
        }
    }

    function get_parent_page_id($parent_id, $selected_pages, $cur_settings)
    {
        $new_parent_id = 0;
        if(isset($cur_settings['structure_parent']))
        {
            $new_parent_id = $cur_settings['structure_parent'];
        }
        else
        {
            if($parent_id > 0)
            {
                if(in_array($parent_id, $selected_pages))
                {
                    $new_parent_id = '_imported_page_';
                }
                else
                {
                    $cur_settings = $this->get('saved_settings', array());
                    if(isset($cur_settings[$this->settings['project_id']]) &&
                        isset($cur_settings[$this->settings['project_id']][$parent_id]))
                    {
                        $parent_id = $cur_settings[$this->settings['project_id']][$parent_id]['overwrite'];

                    }
                }
            }
        }

        return $new_parent_id;
    }
}
