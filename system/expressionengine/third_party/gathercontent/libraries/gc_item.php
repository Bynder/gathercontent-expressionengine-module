<?php

require_once PATH_THIRD.'gathercontent/libraries/gc_channel_fields.php';
class Gc_item extends Gc_channel_fields {

    var $files = array();

    public function import_item()
    {
        $out = array('error' => lang('gathercontent_import_error_1'));
        if(($gc = ee()->input->post('gc')) !== FALSE && isset($gc['item_id']))
        {
            ee()->load->model('field_model');
            ee()->load->library('gc_curl');
            ee()->gc_curl->get_post_types();
            $project_id = ee()->gathercontent_settings->get('project_id');
            $items = ee()->gathercontent_settings->get('saved_items');

            $item_id = $gc['item_id'];

            $file_counter = 0;
            $total_files = 0;
            $files = array(
                'files' => array(),
                'total_files' => 0,
            );
            $save_settings = array();

            $cur_counter = intval(ee()->input->post('cur_counter'));

            $total_entries = intval(ee()->input->post('total'));

            if($cur_counter == 0) {
                ee()->gathercontent_settings->update('media_files', array());
            }

            if(is_array($items) && isset($items[$project_id]) && isset($items[$project_id]['items'][$item_id]))
            {
                extract($items[$project_id]);
                $this->files = ee()->gc_curl->get_files($item_id);

                $item = $items[$item_id];

                if(isset($item->children)) {
                    unset($item->children);
                }

                $config = ee()->gc_curl->get_field_config($item, $this->files);

                $fields = ee()->gc_curl->val($gc,'fields',array());
                $save_settings = array(
                    'post_type' => $gc['post_type'],
                    'overwrite' => $gc['overwrite'],
                    'category' => isset($gc['category']) ? $gc['category'] : '',
                    'fields' => array(),
                );


                $post = array(
                    'entry_id' => $gc['overwrite'],
                    'channel_id' => $gc['post_type'],
                    'title' => $item->name,
                    'status' => 'closed',
                    'category' => array()
                );

                if(isset($gc['parent_id']))
                {
                    $save_settings['structure_parent'] = $gc['parent_id'];
                    $post['structure__parent_id'] = $post['parent_id'] = $gc['parent_id'];
                }

                $old_cats = array_filter(explode(',',$save_settings['category']));
                $cats = array();
                foreach($old_cats as $cat) {
                    if($cat > 0) {
                        $cats[] = $cat;
                    }
                }
                if(count($cats) > 0)
                {
                    $post['category'] = $cats;
                }

                $map_to_array = array();

                foreach($fields as $info)
                {
                    $tab = $info['field_tab'];
                    $map_to = $info['map_to'];
                    $field_name = $info['field_name'];
                    $custom_upload_dir = ee()->gc_curl->val($info, 'custom_upload_dir');
                    $upload_dir = ee()->gc_curl->val($info, 'upload_dir');

                    if($map_to == '_dont_import_')
                    {
                        $save_settings['fields'][$tab.'_'.$field_name] = $map_to;
                        continue;
                    }
                    elseif(isset($config[$tab]) && isset($config[$tab]['elements'][$field_name]))
                    {
                        $field = $config[$tab]['elements'][$field_name];
                    }
                    else
                    {
                        continue;
                    }

                    if(substr($map_to,0,11) == '_cat_group_')
                    {
                        $catids = $this->get_category_ids(substr($map_to,11), $field['value']);
                        if(count($catids))
                        {
                            $post['category'] = array_unique(array_merge($post['category'], $catids));
                        }
                        $save_settings['fields'][$tab.'_'.$field_name] = $map_to;
                    }
                    else
                    {
                        $cur_field = ee()->field_model->get_field($map_to);
                        if($cur_field->num_rows() > 0)
                        {
                            $cur_field = $cur_field->row();
                        }
                        else
                        {
                            $cur_field = new stdClass();
                            $cur_field->field_type = 'text';
                        }

                        if(!empty($custom_upload_dir))
                        {
                            $save_settings['fields'][$tab.'_'.$field_name] = array(
                                'map_to' => $map_to,
                                'upload_val' => $custom_upload_dir,
                            );
                            $upload_dir = $custom_upload_dir;
                        }
                        else
                        {
                            $save_settings['fields'][$tab.'_'.$field_name] = $map_to;
                        }

                        if($field['type'] == 'files')
                        {
                            if(is_array($field['value']) && count($field['value']) > 0)
                            {

                                $new_files = array();
                                foreach($field['value'] as $file)
                                {

                                    $file = (array) $file;
                                    $file['field'] = $map_to;
                                    $file['field_type'] = $cur_field->field_type;
                                    $file['upload_dir'] = $upload_dir;
                                    $file['counter'] = $file_counter;
                                    $new_files[] = $file;
                                }

                                $total_files += count($new_files);
                                $files['files'][] = $new_files;
                                $files['total_files'] = $total_files;

                                $field['value'] = $cur_field->field_type == 'file' ? '' :'#_gc_file_name_'.$file_counter.'#';
                                $file_counter++;
                            }
                            else
                            {
                                $field['value'] = '';
                            }
                        }

                    }


                    if(!isset($map_to_array[$map_to]))
                    {
                        $map_to_array[$map_to] = array();
                    }
                    $map_to_array[$map_to][] = $field['value'];

                }

                foreach($map_to_array as $field => $values) {
                    if(substr($field,0,11) == '_cat_group_')
                    {
                        continue;
                    }
                    $value = '';
                    foreach($values as $val) {
                        $value .= ($value == '' ? '' : "\n");
                        if(is_array($val)) {
                            $value .= implode('|', $val);
                        }
                        else {
                            $value .= $val;
                        }
                    }
                    $post[($field == 'title' ? '': 'field_id_').$field] = $value;
                }

                $new_item_id = $this->save_gathercontent_item($post, $gc['overwrite'], $gc['post_type']);
                $save_settings['overwrite'] = $new_item_id;

                $media = ee()->gathercontent_settings->get('media_files',array());
                if(!isset($media['total_files']))
                {
                    $media['total_files'] = 0;
                }

                if($total_files > 0)
                {

                    $media[$new_item_id] = $files;
                    if(!isset($media['total_files']))
                    {
                        $media['total_files'] = 0;
                    }
                    $media['total_files'] += $total_files;

                    ee()->gathercontent_settings->update('media_files', $media);
                }

                $cur_settings = ee()->gathercontent_settings->get('saved_settings',array());
                if(!is_array($cur_settings))
                {
                    $cur_settings = array();
                }

                if(!isset($cur_settings[$project_id]))
                {
                    $cur_settings[$project_id] = array();
                }

                $cur_settings[$project_id][$item_id] = $save_settings;
                ee()->gathercontent_settings->update('saved_settings', $cur_settings);

                $out = array(
                    'item_id' => $item_id,
                    'success' => true,
                    'item_percent' => ee()->gc_curl->percent(++$cur_counter,$total_entries),
                    'redirect_url' => ($media['total_files'] > 0 ? 'media' : 'finished'),
                    'new_item_id' => $new_item_id,
                    'new_item_html' => '<li><a href="#" data-value="'.$new_item_id.'"><span>'.$post['title'].'</span></a></li>'
                );
            }
            else
            {
                $out = array(
                    'error' => lang('gathercontent_import_error_2'),
                );
            }
        }
        else
        {
            $out = array(
                'error' => lang('gathercontent_import_error_2'),
            );
        }
        return $out;
    }

    function get_category_ids($cat_group, $text)
    {
        if(is_array($text)) {
            $text = implode('|', $text);
        }
        $cats = strip_tags($text);
        if(strpos($cats, ',') !== false)
        {
            $cats = explode(',', $cats);
        }
        else
        {
            $cats = explode('|', $cats);
        }

        $new_cats = array();
        $cats = array_filter($cats);
        if(count($cats) > 0)
        {
            foreach($cats as $cat_name)
            {
                ee()->db->select('cat_id')
                        ->where('group_id', $cat_group)
                        ->where('cat_name', $cat_name);
                $result = ee()->db->get('categories');
                if($result->num_rows() > 0)
                {
                    $row = $result->row();
                    $new_cats[] = $row->cat_id;
                }
                else
                {


                    $category_data = array(
                        'group_id'          => $cat_group,
                        'cat_name'          => $cat_name,
                        'cat_url_title'     => $this-> _cat_url_title($cat_name, $cat_group),
                        'cat_description'   => '',
                        'cat_image'         => '',
                        'parent_id'         => 0,
                        'cat_order'         => 1,
                        'site_id'           => ee()->config->item('site_id')
                    );

                    ee()->db->insert('categories', $category_data);
                    $new_cats[] = ee()->db->insert_id();
                }
            }
        }

        return $new_cats;
    }

    function _cat_url_title($cat_name, $cat_group)
    {

        ee()->load->helper('url');

        $url_title = url_title($cat_name, ee()->config->item('word_separator'), TRUE);

        $unique = FALSE;
        $i = 0;

        while ($unique == FALSE)
        {
            $temp = ($i == 0) ? $url_title : $url_title.$i;
            $i++;

            ee()->db->select('cat_id')
                    ->where('group_id', $cat_group)
                    ->where('cat_url_title', $temp);
            $result = ee()->db->get('categories');
            if($result->num_rows() > 0)
            {
                $unique = FALSE;
            }
            else
            {
                $unique = TRUE;
            }
        }

        return $temp;
    }

    function save_gathercontent_item($post, $entry_id, $channel_id)
    {

        $_POST = $post;

        ee()->input->_sanitize_globals();

        ee()->api->instantiate('channel_entries');

        $autosave_entry_id = FALSE;
        $this->_channel_data = $this->_load_channel_data($channel_id);


        $entry_data     = $this->_load_entry_data($channel_id, $entry_id, $autosave_entry_id);
        $field_data     = $this->_set_field_settings($entry_id, $entry_data);
        $entry_id       = $entry_data['entry_id'];

        // Merge in default fields
        $deft_field_data = $this->_setup_default_fields($this->_channel_data, $entry_data);

        $field_data = array_merge($field_data, $deft_field_data);
        $field_data = $this->_setup_field_blocks($field_data, $entry_data);

        foreach($field_data as $name => $data)
        {
            if(($cache = ee()->session->cache(__CLASS__, $name)) !== FALSE)
            {
                $field_data[$name]['field_data'] = $cache;
            }

            if(!isset($_POST[$name])) {
                $_POST[$name] = $field_data[$name]['field_data'];
            }
        }

        if(isset($_POST['structure__template_id']) && is_array($_POST['structure__template_id']))
        {
            $_POST['structure__template_id'] = array_pop($_POST['structure__template_id']);
        }

        if(isset($_POST['structure__listing_channel']))
        {
            $_POST['structure__listing_channel'] = intval($_POST['structure__listing_channel']);
        }

        $data = $_POST;
        if(!isset($data['author']))
        {
            $data['author'] = ee()->session->userdata('member_id');
        }

        $data['cp_call']        = TRUE;
        //$data['author_id']      = ee()->input->post('author');     // @todo double check if this is validated
        $data['revision_post']  = $_POST;

        if(isset($data['structure__parent_id']) && is_array($data['structure__parent_id']))
        {
            $data['structure__parent_id'] = array_pop($data['structure__parent_id']);
        }

        ee()->api_channel_entries->save_entry($data, $channel_id, $entry_id);

        return ee()->api_channel_entries->entry_id;
    }
}
