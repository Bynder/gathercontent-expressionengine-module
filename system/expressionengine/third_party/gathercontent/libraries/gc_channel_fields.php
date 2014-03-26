<?php

// a bunch of functions taken from controllers/cp/content_publish.php


class Gc_channel_fields {

    protected $_channel_data      = array();
    protected $_channel_fields    = array();
    protected $_errors            = array();
    protected $_assigned_channels = array();
    protected $_smileys_enabled   = FALSE;

    public function __construct()
    {

        if ( ! ee()->cp->allowed_group('can_access_content'))
        {
            show_error(lang('unauthorized_access'));
        }

        ee()->load->library('api');
        ee()->load->library('spellcheck');
        ee()->load->model('channel_model');
        ee()->load->helper(array('typography', 'spellcheck'));
        ee()->cp->get_installed_modules();

        $this->_assigned_channels = ee()->functions->fetch_assigned_channels();
    }

    // --------------------------------------------------------------------

    /**
     * Load channel data
     *
     * @access  private
     * @return  void
     */
    protected function _load_channel_data($channel_id)
    {
        $query = ee()->channel_model->get_channel_info($channel_id);

        if ($query->num_rows() == 0)
        {
            show_error(lang('no_channel_exists'));
        }

        $row = $query->row_array();

        /* -------------------------------------------
        /* 'publish_form_channel_preferences' hook.
        /*  - Modify channel preferences
        /*  - Added: 1.4.1
        */
            if (ee()->extensions->active_hook('publish_form_channel_preferences') === TRUE)
            {
                $row = ee()->extensions->call('publish_form_channel_preferences', $row);
            }
        /*
        /* -------------------------------------------*/

        return $row;
    }

    // --------------------------------------------------------------------

    /**
     * Member has access
     *
     * @return  void
     */
    function _load_entry_data($channel_id, $entry_id = FALSE, $autosave_entry_id = FALSE)
    {
        $result = array(
            'title'     => $this->_channel_data['default_entry_title'],
            'url_title' => $this->_channel_data['url_title_prefix'],
            'entry_id'  => 0
        );

        if ($entry_id OR $autosave_entry_id)
        {
            ee()->load->model('channel_entries_model');

            $query = ee()->channel_entries_model->get_entry($entry_id, $channel_id, $autosave_entry_id);

            if ( ! $query->num_rows())
            {
                show_error(lang('no_channel_exists'));
            }

            $result = $query->row_array();

            if ($autosave_entry_id)
            {
                $res_entry_data = unserialize($result['entry_data']);

                // overwrite and add to this array with entry_data
                foreach ($res_entry_data as $k => $v)
                {
                    $result[$k] = $v;
                }

                // if the autosave was a new entry, kill the entry id
                if ($result['original_entry_id'] == 0)
                {
                    $result['autosave_entry_id'] = $entry_id;
                    $result['entry_id'] = 0;
                }

                unset($result['entry_data']);
                unset($result['original_entry_id']);
            }

            $version_id = ee()->input->get_post('version_id');

            if ($result['versioning_enabled'] == 'y'
                && is_numeric($version_id))
            {
                $vquery = ee()->db->select('version_data')
                                    ->where('entry_id', $entry_id)
                                    ->where('version_id', $version_id)
                                    ->get('entry_versioning');

                if ($vquery->num_rows() === 1)
                {
                    $vdata = unserialize($vquery->row('version_data'));

                    // Legacy fix for revisions where the entry_id in the array was saved as 0
                    $vdata['entry_id'] = $entry_id;

                    $result = array_merge($result, $vdata);
                }
            }
        }

        // -------------------------------------------
        // 'publish_form_entry_data' hook.
        //  - Modify entry's data
        //  - Added: 1.4.1
            if (ee()->extensions->active_hook('publish_form_entry_data') === TRUE)
            {
                $result = ee()->extensions->call('publish_form_entry_data', $result);
            }
        // -------------------------------------------

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Setup channel field settings
     *
     * @access  private
     * @return  void
     */
    protected function _set_field_settings($entry_id, $entry_data)
    {
        ee()->api->instantiate('channel_fields');

        // Get Channel fields in the field group
        $channel_fields = ee()->channel_model->get_channel_fields($this->_channel_data['field_group']);


        $field_settings = array();

        foreach ($channel_fields->result_array() as $row)
        {
            $field_fmt      = $row['field_fmt'];
            $field_dt       = '';
            $field_data     = '';

            if ($entry_id === 0)
            {
                // Bookmarklet perhaps?
                if (($field_data = ee()->input->get('field_id_'.$row['field_id'])) !== FALSE)
                {
                    $field_data = ee()->_bm_qstr_decode($this->input->get('tb_url')."\n\n".$field_data );
                }
            }
            else
            {
                $field_data = (isset($entry_data['field_id_'.$row['field_id']])) ? $entry_data['field_id_'.$row['field_id']] : $field_data;
                $field_dt   = (isset($entry_data['field_dt_'.$row['field_id']])) ? $entry_data['field_dt_'.$row['field_id']] : 'y';
                $field_fmt  = (isset($entry_data['field_ft_'.$row['field_id']])) ? $entry_data['field_ft_'.$row['field_id']] : $field_fmt;
            }

            $settings = array(
                'field_instructions'    => trim($row['field_instructions']),
                'field_text_direction'  => ($row['field_text_direction'] == 'rtl') ? 'rtl' : 'ltr',
                'field_fmt'             => $field_fmt,
                'field_dt'              => $field_dt,
                'field_data'            => $field_data,
                'field_name'            => 'field_id_'.$row['field_id'],
            );

            $ft_settings = array();

            if (isset($row['field_settings']) && strlen($row['field_settings']))
            {
                $ft_settings = unserialize(base64_decode($row['field_settings']));
            }

            $settings = array_merge($row, $settings, $ft_settings);
            ee()->api_channel_fields->set_settings($row['field_id'], $settings);

            $field_settings[$settings['field_name']] = $settings;
        }

        return $field_settings;
    }

    // --------------------------------------------------------------------

    /**
     * Setup Default Fields
     *
     * This method sets up Default fields that are required on the entry page.
     *
     * @todo    Make field_text_directions configurable
     * @return  array
     */
    protected function _setup_default_fields($channel_data, $entry_data)
    {
        $title = (ee()->input->get_post('title')) ? ee()->input->get_post('title') : $entry_data['title'];

        if ($this->_channel_data['default_entry_title'] != '' && $title == '')
        {
            $title = $this->_channel_data['default_entry_title'];
        }

        $deft_fields = array(
            'title'         => array(
                'field_id'              => 'title',
                'field_label'           => lang('title'),
                'field_required'        => 'y',
                'field_data'            => $title,
                'field_show_fmt'        => 'n',
                'field_instructions'    => '',
                'field_text_direction'  => 'ltr',
                'field_type'            => 'text',
                'field_maxl'            => 100
            ),
            'url_title'     => array(
                'field_id'              => 'url_title',
                'field_label'           => lang('url_title'),
                'field_required'        => 'n',
                'field_data'            => (ee()->input->get_post('url_title') == '') ? $entry_data['url_title'] : ee()->input->get_post('url_title'),
                'field_fmt'             => 'xhtml',
                'field_instructions'    => '',
                'field_show_fmt'        => 'n',
                'field_text_direction'  => 'ltr',
                'field_type'            => 'text',
                'field_maxl'            => 75
            ),
            'entry_date'    => array(
                'field_id'              => 'entry_date',
                'field_label'           => lang('entry_date'),
                'field_required'        => 'y',
                'field_type'            => 'date',
                'field_text_direction'  => 'ltr',
                'field_data'            => (isset($entry_data['entry_date'])) ? $entry_data['entry_date'] : '',
                'field_fmt'             => 'text',
                'field_instructions'    => '',
                'field_show_fmt'        => 'n',
                'always_show_date'      => 'y',
                'default_offset'        => 0,
                'selected'              => 'y',
            ),
            'expiration_date' => array(
                'field_id'              => 'expiration_date',
                'field_label'           => lang('expiration_date'),
                'field_required'        => 'n',
                'field_type'            => 'date',
                'field_text_direction'  => 'ltr',
                'field_data'            => (isset($entry_data['expiration_date'])) ? $entry_data['expiration_date'] : '',
                'field_fmt'             => 'text',
                'field_instructions'    => '',
                'field_show_fmt'        => 'n',
                'default_offset'        => 0,
                'selected'              => 'y',
            )
        );

        // comment expiry here.
        if (isset(ee()->cp->installed_modules['comment']) && $this->_channel_data['comment_system_enabled'] == 'y')
        {
            $deft_fields['comment_expiration_date'] = array(
                'field_id'              => 'comment_expiration_date',
                'field_label'           => lang('comment_expiration_date'),
                'field_required'        => 'n',
                'field_type'            => 'date',
                'field_text_direction'  => 'ltr',
                'field_data'            => (isset($entry_data['comment_expiration_date'])) ? $entry_data['comment_expiration_date'] : '',
                'field_fmt'             => 'text',
                'field_instructions'    => '',
                'field_show_fmt'        => 'n',
                'default_offset'        => $this->_channel_data['comment_expiration'] * 86400,
                'selected'              => 'y',
            );
        }

        foreach ($deft_fields as $field_name => $f_data)
        {
            ee()->api_channel_fields->set_settings($field_name, $f_data);
        }

        return $deft_fields;
    }

    // --------------------------------------------------------------------

    /**
     * Setup Field Blocks
     *
     * This function sets up default fields and field blocks
     *
     * @param   array
     * @param   array
     * @return  array
     */
    protected function _setup_field_blocks($field_data, $entry_data)
    {
        $categories     = $this->_build_categories_block($entry_data);
        $options        = $this->_build_options_block($entry_data);
        $revisions      = $this->_build_revisions_block($entry_data);
        $third_party    = $this->_build_third_party_blocks($entry_data);

        return array_merge(
            $field_data,
            $categories,
            $options,
            $revisions,
            $third_party
        );
    }

    // --------------------------------------------------------------------

    /**
     * Categories Block
     * Taken from libraries/publish.php
     */
    private function _build_categories_block($entry_data)
    {
        $cat_group_ids = $this->_channel_data['cat_group'];
        $entry_id = $entry_data['entry_id'];
        $selected_categories = (isset($entry_data['category'])) ? $entry_data['category'] : NULL;
        $default_category = $this->_channel_data['deft_category'];
        
        ee()->load->library('api');
        ee()->api->instantiate('channel_categories');

        $default    = array(
            'string_override'       => lang('no_categories'),
            'field_id'              => 'category',
            'field_name'            => 'category',
            'field_label'           => lang('categories'),
            'field_required'        => 'n',
            'field_type'            => 'multiselect',
            'field_text_direction'  => 'ltr',
            'field_data'            => '',
            'field_fmt'             => 'text',
            'field_instructions'    => '',
            'field_show_fmt'        => 'n',
            'selected'              => 'n',
            'options'               => array()
        );

        // No categories? Easy peasy
        if ( ! $cat_group_ids)
        {
            return array('category' => $default);
        }
        else if ( ! is_array($cat_group_ids))
        {
            if (strstr($cat_group_ids, '|'))
            {
                $cat_group_ids = explode('|', $cat_group_ids);
            }
            else
            {
                $cat_group_ids = array($cat_group_ids);
            }
        }

        ee()->api->instantiate('channel_categories');

        $catlist    = array();
        $categories = array();

        // Figure out selected categories
        if ( ! count($_POST) && ! $entry_id && $default_category)
        {
            // new entry and a default exists
            $catlist = $default_category;
        }
        elseif (count($_POST) > 0)
        {
            $catlist = array();

            if (isset($_POST['category']) && is_array($_POST['category']))
            {
                foreach ($_POST['category'] as $val)
                {
                    $catlist[$val] = $val;
                }
            }
        }
        elseif ( ! isset($selected_categories) AND $entry_id !== 0)
        {
            if ($file)
            {
                ee()->db->from(array('categories c', 'file_categories p'));
                ee()->db->where('p.file_id', $entry_id);
            }
            else
            {
                ee()->db->from(array('categories c', 'category_posts p'));
                ee()->db->where('p.entry_id', $entry_id);
            }

            ee()->db->select('c.cat_name, p.*');
            ee()->db->where_in('c.group_id', $cat_group_ids);
            ee()->db->where('c.cat_id = p.cat_id');

            $qry = ee()->db->get();

            foreach ($qry->result() as $row)
            {
                $catlist[$row->cat_id] = $row->cat_id;
            }
        }
        elseif (is_array($selected_categories))
        {
            foreach ($selected_categories as $val)
            {
                $catlist[$val] = $val;
            }
        }

        // Figure out valid category options
        ee()->api_channel_categories->category_tree($cat_group_ids, $catlist);

        if (count(ee()->api_channel_categories->categories) > 0)
        {
            // add categories in again, over-ride setting above
            foreach (ee()->api_channel_categories->categories as $val)
            {
                $categories[$val['3']][] = $val;
            }
        }


        // If the user can edit categories, we'll go ahead and
        // show the links to make that work
        $edit_links = FALSE;

        if (ee()->session->userdata('can_edit_categories') == 'y')
        {
            $link_info = ee()->api_channel_categories->fetch_allowed_category_groups($cat_group_ids);

            if (is_array($link_info) && count($link_info))
            {
                $edit_links = array();

                foreach ($link_info as $val)
                {
                    $edit_links[] = array(
                        'url' => BASE.AMP.'C=admin_content'.AMP.'M=category_editor'.AMP.'group_id='.$val['group_id'],
                        'group_name' => $val['group_name']
                    );
                }
            }
        }

        // Load in necessary lang keys
        ee()->lang->loadfile('admin_content');
        ee()->javascript->set_global(array(
            'publish.lang' => array(
                'update'        => lang('update'),
                'edit_category' => lang('edit_category')
            )
        ));

        // EE.publish.lang.update_category

        // Build the mess
        $data = compact('categories', 'edit_links');

        $default['options']         = $categories;
        $default['string_override'] = '';

        return array('category' => $default);
    }

    // --------------------------------------------------------------------

    /**
     * Options Block
     *
     *
     *
     */
    private function _build_options_block($entry_data)
    {
        // sticky, comments, dst
        // author, channel, status
        $settings           = array();

        $show_comments      = FALSE;
        $show_sticky        = FALSE;
        $show_dst           = FALSE;

        $selected = (isset($entry_data['sticky']) && $entry_data['sticky'] == 'y') ? TRUE : FALSE;
        $selected = (ee()->input->post('sticky') == 'y') ? TRUE : $selected;

        $checks = '<label>'.form_checkbox('sticky', 'y', set_value('sticky', $selected), 'class="checkbox"').' '.lang('sticky').'</label>';

        // Allow Comments?
        if (isset(ee()->cp->installed_modules['comment']) && $this->_channel_data['comment_system_enabled'] == 'y')
        {
            // Figure out selected categories
            if ( ! count($_POST) && ! $entry_data['entry_id'] && $this->_channel_data['deft_comments'])
            {
                $selected = ($this->_channel_data['deft_comments'] == 'y') ? 1 : '';
            }
            else
            {
                $selected = (isset($entry_data['allow_comments']) && $entry_data['allow_comments'] == 'y') ? TRUE : FALSE;
                $selected = (ee()->input->post('allow_comments') == 'y') ? TRUE : $selected;
            }

            $checks .= '<label>'.form_checkbox('allow_comments', 'y', $selected, 'class="checkbox"').' '.lang('allow_comments').'</label>';

        }

        // Options Field
        $settings['options'] = array(
            'field_id'              => 'options',
            'field_required'        => 'n',
            'field_label'           => lang('options'),
            'field_data'            => '',
            'field_instructions'    => '',
            'field_text_direction'  => 'ltr',
            'field_pre_populate'    => 'n',
            'field_type'            => 'checkboxes',
            'field_list_items'      => array(),
            'string_override'       => ''
        );


        ee()->api_channel_fields->set_settings('options', $settings['options']);


        $settings['author']     = $this->_build_author_select($entry_data);
        $settings['new_channel']    = $this->_build_channel_select();
        $settings['status']     = $this->_build_status_select($entry_data);

        return $settings;
    }

    // --------------------------------------------------------------------

    /**
     * Build Author Vars
     *
     * @param   array
     */
    protected function _build_author_select($entry_data)
    {
        ee()->load->model('member_model');

        // Default author
        $author_id = (isset($entry_data['author_id'])) ? $entry_data['author_id'] : ee()->session->userdata('member_id');

        $menu_author_options = array();
        $menu_author_selected = $author_id;

        $qry = ee()->db->select('username, screen_name')
                        ->get_where('members', array('member_id' => (int) $author_id));

        if ($qry->num_rows() > 0)
        {
            $menu_author_options[$author_id] = ($qry->row('screen_name')  == '')
                ? $qry->row('username') : $qry->row('screen_name');
        }

        // Next we'll gather all the authors that are allowed to be in this list
        $author_list = ee()->member_model->get_authors();

        $channel_id = (isset($entry_data['channel_id'])) ? $entry_data['channel_id'] : ee()->input->get('channel_id');

        // We'll confirm that the user is assigned to a member group that allows posting in this channel
        if ($author_list->num_rows() > 0)
        {
            foreach ($author_list->result() as $row)
            {
                if (isset($this->session->userdata['assigned_channels'][$channel_id]))
                {
                    $menu_author_options[$row->member_id] = ($row->screen_name == '') ? $row->username : $row->screen_name;
                }
            }
        }

        $settings = array(
            'author'    => array(
                'field_id'              => 'author',
                'field_label'           => lang('author'),
                'field_required'        => 'n',
                'field_instructions'    => '',
                'field_type'            => 'select',
                'field_pre_populate'    => 'n',
                'field_text_direction'  => 'ltr',
                'field_list_items'      => $menu_author_options,
                'field_data'            => $menu_author_selected
            )
        );

        ee()->api_channel_fields->set_settings('author', $settings['author']);
        return $settings['author'];
    }

    // --------------------------------------------------------------------

    /**
     * Build Channel Select Options Field
     *
     * @return  array
     */
    private function _build_channel_select()
    {
        $menu_channel_options   = array();
        $menu_channel_selected  = '';

        $query = ee()->channel_model->get_channel_menu(
                                                        $this->_channel_data['status_group'],
                                                        $this->_channel_data['cat_group'],
                                                        $this->_channel_data['field_group']
                                                    );

        if ($query->num_rows() > 0)
        {
            foreach ($query->result_array() as $row)
            {
                if (ee()->session->userdata('group_id') == 1 OR
                    in_array($row['channel_id'], $this->_assigned_channels))
                {
                    if (isset($_POST['new_channel']) && is_numeric($_POST['new_channel']) &&
                        $_POST['new_channel'] == $row['channel_id'])
                    {
                        $menu_channel_selected = $row['channel_id'];
                    }
                    elseif ($this->_channel_data['channel_id'] == $row['channel_id'])
                    {
                        $menu_channel_selected =  $row['channel_id'];
                    }

                    $menu_channel_options[$row['channel_id']] = form_prep($row['channel_title']);
                }
            }
        }

        $settings = array(
            'new_channel'   => array(
                'field_id'              => 'new_channel',
                'field_label'           => lang('channel'),
                'field_required'        => 'n',
                'field_instructions'    => '',
                'field_type'            => 'select',
                'field_pre_populate'    => 'n',
                'field_text_direction'  => 'ltr',
                'field_list_items'      => $menu_channel_options,
                'field_data'            => $menu_channel_selected
            )
        );

        ee()->api_channel_fields->set_settings('new_channel', $settings['new_channel']);
        return $settings['new_channel'];
    }

    // --------------------------------------------------------------------

    /**
     * Build Status Select
     *
     * @return  array
     */
    private function _build_status_select($entry_data)
    {
        ee()->load->model('status_model');

        // check the logic here...
        if ( ! isset($this->_channel_data['deft_status']) && $this->_channel_data['deft_status'] == '')
        {
            $this->_channel_data['deft_status'] = 'open';
        }

        $entry_data['status'] = (isset($entry_data['status']) && $entry_data['status'] != 'NULL') ? $entry_data['status'] : $this->_channel_data['deft_status'];

        $no_status_access       = array();
        $menu_status_options    = array();
        $menu_status_selected   = $entry_data['status'];

        if (ee()->session->userdata('group_id') !== 1)
        {
            $query = $this->get_disallowed_statuses(ee()->session->userdata('group_id'));

            if ($query->num_rows() > 0)
            {
                foreach ($query->result_array() as $row)
                {
                    $no_status_access[] = $row['status_id'];
                }
            }
        }

        if ( ! isset($this->_channel_data['status_group']))
        {
            if (ee()->session->userdata('group_id') == 1)
            {
                // if there is no status group assigned,
                // only Super Admins can create 'open' entries
                $menu_status_options['open'] = lang('open');
            }

            $menu_status_options['closed'] = lang('closed');
        }
        else
        {
            $query = ee()->status_model->get_statuses($this->_channel_data['status_group']);

            if ($query->num_rows())
            {
                $no_status_flag = TRUE;
                $vars['menu_status_options'] = array();

                foreach ($query->result_array() as $row)
                {
                    // pre-selected status
                    if ($entry_data['status'] == $row['status'])
                    {
                        $menu_status_selected = $row['status'];
                    }

                    if (in_array($row['status_id'], $no_status_access))
                    {
                        continue;
                    }

                    $no_status_flag = FALSE;
                    $status_name = ($row['status'] == 'open' OR $row['status'] == 'closed') ? lang($row['status']) : $row['status'];
                    $menu_status_options[form_prep($row['status'])] = form_prep($status_name);
                }

                // Were there no statuses?
                // If the current user is not allowed to submit any statuses we'll set the default to closed
                if ($no_status_flag === TRUE)
                {
                    $menu_status_options['closed'] = lang('closed');
                    $menu_status_selected = 'closed';
                }
            }
        }

        $settings = array(
            'status'    => array(
                'field_id'              => 'status',
                'field_label'           => lang('status'),
                'field_required'        => 'n',
                'field_instructions'    => '',
                'field_type'            => 'select',
                'field_pre_populate'    => 'n',
                'field_text_direction'  => 'ltr',
                'field_list_items'      => $menu_status_options,
                'field_data'            => $menu_status_selected
            )
        );

        ee()->api_channel_fields->set_settings('status', $settings['status']);
        return $settings['status'];
    }

    // --------------------------------------------------------------------

    /**
     * Get Disallowed Statuses
     *
     * @access  public
     * @param   int
     * @return  array
     */
    function get_disallowed_statuses($group_id = '')
    {
        ee()->db->where('statuses.status_id = '.ee()->db->dbprefix('status_no_access.status_id'));
        ee()->db->where('status_no_access.member_group', $group_id);

        return ee()->db->get('status_no_access, statuses');
    }

    /**
     * Get Statuses
     *
     * @access  public
     * @param   int
     * @return  array
     */
    function get_statuses($group_id = '', $channel_id = '')
    {
        if ($group_id != '')
        {
            ee()->db->where('group_id', $group_id);
        }

        ee()->db->from('statuses');

        if ($channel_id != '')
        {
            ee()->db->select('statuses.status_id, statuses.status');
            ee()->db->join('status_groups sg', 'sg.group_id = statuses.group_id', 'left');
            ee()->db->join('channels c', 'c.status_group = sg.group_id', 'left');
            ee()->db->where('c.channel_id', $channel_id);
        }
        else
        {
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->order_by('status_order');
        }

        return ee()->db->get();
    }

    // --------------------------------------------------------------------

    /**
     * Build Revisions Block
     *
     * @param   array
     * @return  array
     */
    private function _build_revisions_block($entry_data)
    {

        $settings['revisions'] = array(
            'field_id'              => 'revisions',
            'field_label'           => lang('revisions'),
            'field_name'            => 'revisions',
            'field_required'        => 'n',
            'field_type'            => 'checkboxes',
            'field_text_direction'  => 'ltr',
            'field_data'            => '',
            'field_fmt'             => 'text',
            'field_instructions'    => '',
            'field_show_fmt'        => '',
            'string_override'       => ''
        );

        return $settings;
    }

    // --------------------------------------------------------------------

    /**
     * Build Third Party tab blocks
     *
     * This method assembles tabs from modules that include a publish tab
     *
     * @param   array
     * @return  array
     */
    private function _build_third_party_blocks($entry_data)
    {
        $module_fields = ee()->api_channel_fields->get_module_fields(
                                                        $this->_channel_data['channel_id'],
                                                        $entry_data['entry_id']
                                                    );
        $settings = array();

        if ($module_fields && is_array($module_fields))
        {
            foreach ($module_fields as $tab => $v)
            {
                foreach ($v as $val)
                {
                    $settings[$val['field_id']] = $val;

                    // So 3rd party module tab fields get their data on autosave
                    if (isset($entry_data[$val['field_id']]))
                    {
                        $settings[$val['field_id']]['field_data'] = $entry_data[$val['field_id']];
                    }

                    ee()->api_channel_fields->set_settings($val['field_id'], $val);
                }
            }
        }

        return $settings;
    }

    // --------------------------------------------------------------------


}
