<?php

require_once PATH_THIRD.'gathercontent/libraries/gc_functions.php';
class Gc_curl extends Gc_functions {

    var $has_acf = FALSE;
    var $page_count = 0;
    var $cat_groups = array();
    var $allows_tags = array();
    var $field_groups = array();
    var $page_ids = array();
    var $error = '';
    var $has_structure = NULL;
    var $sql;
    var $ids_used = array();
    var $data = array();

    function has_structure()
    {
        if(!is_null($this->has_structure))
        {
            return $this->has_structure;
        }
        $this->has_structure = FALSE;

        ee()->cp->get_installed_modules();
        if(isset(ee()->cp->installed_modules['structure']))
        {
            if(file_exists(PATH_THIRD.'structure/sql.structure.php'))
            {
                require_once PATH_THIRD.'structure/sql.structure.php';
                $this->sql = new Sql_structure();
                $this->has_structure = TRUE;
            }
        }
        return $this->has_structure;
    }

    function get_ee_field_settings($row)
    {
        $arr = @unserialize(base64_decode($row->field_settings));
        if(!is_array($arr))
        {
            $arr = array();
        }
        return $arr;
    }

    function get_field_config($obj,$files=array())
    {
        if($obj->config != '')
        {
            $config = json_decode(base64_decode($obj->config));
            $new_config = array();
            $total_fields = 0;
            if($this->foreach_safe($config))
            {

                foreach($config as $tab_pane)
                {

                    $new_fields = array();

                    if($this->foreach_safe($tab_pane->elements))
                    {
                        foreach($tab_pane->elements as $element)
                        {

                            switch($element->type)
                            {
                                case 'text':
                                    $val = $element->value;
                                    if(!$element->plain_text)
                                    {
                                        $val = preg_replace_callback('#\<p\>(.+?)\<\/p\>#s',
                                            create_function(
                                                '$matches',
                                                'return "<p>".str_replace(array("\n","\r\n","\r"), " ", $matches[1])."</p>";'
                                            ), $val);
                                        $val = str_replace('</ul><',"</ul>\n<", $val);
                                        $val = preg_replace('/\s*<\//m', '</', $val);
                                        $val = preg_replace('/<\/p>\s*<p>/m', "</p>\n<p>", $val);
                                        $val = preg_replace('/<\/p>\s*</m', "</p>\n<", $val);
                                        $val = preg_replace('/<p>\s*<\/p>/m','<p>&nbsp;</p>',$val);
                                        $val = str_replace(array('<ul><li','</li><li>', '</li></ul>'), array("<ul>\n\t<li","</li>\n\t<li>", "</li>\n</ul>"), $val);

                                        $val = preg_replace('/<mark[^>]*>/i', '', $val);
                                        $val = preg_replace('/<\/mark>/i', '', $val);
                                    }
                                    break;

                                case 'choice_radio':
                                    $val = '';
                                    foreach($element->options as $idx => $option)
                                    {
                                        if($option->selected)
                                        {
                                            if(isset($option->value))
                                            {
                                                $val = $option->value;
                                            }
                                            else {
                                                $val = $option->label;
                                            }
                                        }
                                    }
                                    break;

                                case 'choice_checkbox':
                                    $val = array();
                                    foreach($element->options as $option)
                                    {
                                        if($option->selected)
                                        {
                                            $val[] = $option->label;
                                        }
                                    }
                                    break;

                                case 'files':
                                    $val = $this->val($files, $element->name, array());
                                    break;

                                default:
                                    continue 2;
                                    break;

                            }
                            $new_fields[$element->name] = array(
                                'name' => $element->name,
                                'label' => $element->label,
                                'type' => $element->type,
                                'value' => $val,
                            );
                        }
                    }

                    $total_fields += count($new_fields);

                    $new_config[strtolower( $tab_pane->name )] = array(
                        'label' => $tab_pane->label,
                        'elements' => $new_fields,
                    );
                }
            }

            $new_config['field_count'] = $total_fields;

            return $new_config;
        }
        return array();
    }

    function get_files($page_id)
    {
        $files = $this->get('get_files_by_page',array('id'=>$page_id));
        if($files && isset($files->files) && $this->foreach_safe($files->files))
        {
            foreach($files->files as $file)
            {
                if(!isset($this->files[$file->page_id]))
                    $this->files[$file->page_id] = array();
                if(!isset($this->files[$file->page_id][$file->field]))
                    $this->files[$file->page_id][$file->field] = array();
                $this->files[$file->page_id][$file->field][] = $file;
            }
        }
        if(isset($this->files[$page_id]))
        {
            return $this->files[$page_id];
        }
        return array();
    }

    function get_projects()
    {
        $projects = $this->get('get_projects');

        $newprojects = array();
        if($projects && is_object($projects) && is_array($projects->projects))
        {
            foreach($projects->projects as $project)
            {
                $newprojects[$project->id] = array(
                    'name' => $project->name,
                    'page_count' => $project->page_count
                );
            }
            asort($newprojects);
        }
        $this->data['projects'] = $newprojects;
    }

    function get_states()
    {
        $states = $this->get('get_custom_states_by_project',array('id'=>$this->option('project_id')));
        $new_states = array();
        $count = 5;
        if($states && $this->foreach_safe($states->custom_states))
        {
            foreach($states->custom_states as $state)
            {
                $new_states[$state->id] = (object) array(
                    'name' => $state->name,
                    'color_id' => $state->color_id,
                    'position' => $state->position
                );
                $count--;
            }
            uasort($new_states,array(&$this,'sort_pages'));
        }
        $this->data['states'] = $new_states;
    }

    function get_projects_dropdown()
    {
        $html = '';
        $url = $this->plugin_url.AMP.'method=set_project_id'.AMP.'project_id=';
        $project_id = $this->option('project_id');
        $title = '';
        if($this->foreach_safe($this->data['projects']))
        {
            foreach($this->data['projects'] as $id => $info)
            {
                if($id == $project_id)
                {
                    $title = $info['name'];
                }
                else
                {
                    $html .= '
                    <li>
                        <a href="'.$url.$id.'">'.$info['name'].'</a>
                    </li>';
                }
            }
            if($html != '')
                $html = $this->dropdown_html('<span>'.$title.'</span>',$html);
        }
        $this->data['projects_dropdown'] = $html;
    }

    function get_state_dropdown()
    {
        $html = '
            <li>
                <a data-custom-state-name="All" href="#change-state"><span class="page-status"></span>  '.lang('gathercontent_all').'</a>
            </li>';
        if($this->foreach_safe($this->data['states']))
        {
            foreach($this->data['states'] as $id => $state)
            {
                $html .= '
                <li>
                    <a data-custom-state-name="'.$state->name.'" data-custom-state-id="'.$id.'" href="#change-state"><span class="page-status page-state-color-'.$state->color_id.'"></span> '.$state->name.'</a>
                </li>';
            }
        }
        $this->data['state_dropdown'] = $this->dropdown_html('<i class="icon-filter"></i> <span>'.lang('gathercontent_all').'</span>',$html);
    }

    function _post_type_item($row, $type=FALSE, $show_type=FALSE)
    {
        if(!empty($row['cat_group']))
        {
            $cats = array_filter(explode('|', $row['cat_group']));
            foreach($cats as $cat)
            {
                if(!isset($this->cat_groups[$cat]))
                {
                    $results = ee()->category_model->get_category_group_name($cat);
                    if($results->num_rows > 0)
                    {
                        $group = $results->result_array();
                        $this->cat_groups[$cat] = array(
                            'channels' => array($row['channel_id']),
                            'title' => $group[0]['group_name'],
                        );
                    }
                }
                else
                {
                    $this->cat_groups[$cat]['channels'][] = $row['channel_id'];
                }
            }
        }
        return '
<li>
    <a data-value="'.$row['channel_id'].'"'.($show_type === TRUE ? ($type !== FALSE ? ' data-structure-type="'.$type.'"':'') : '').' href="#">'.$row['channel_title'].'</a>
</li>';

    }

    function get_post_types()
    {
        $html = '';
        $new_post_types = array();
        $field_groups = array();
        $default = '';

        $prefix = ee()->db->dbprefix;

        $sql = "SELECT ec.channel_id, ec.channel_title, ec.field_group, ec.cat_group
            FROM {$prefix}channels AS ec
            WHERE ec.site_id = '".$this->site_id."'
            ORDER BY ec.channel_title";
        if($this->has_structure())
        {
            $sql = "SELECT ec.channel_id, ec.channel_title, ec.field_group, ec.cat_group, esc.type
                FROM {$prefix}channels AS ec
                LEFT JOIN {$prefix}structure_channels as esc USING (channel_id)
                WHERE ec.site_id = '".$this->site_id."'
                ORDER BY ec.channel_title";
        }
        $results = ee()->db->query($sql);

        if ($results->num_rows > 0)
        {
            // Format the array nicely
            $channel_data = array();
            foreach($results->result_array() as $row)
            {
                $type = FALSE;
                if(isset($row['type']))
                {
                    $type = $row['type'];
                    if(empty($type))
                    {
                        $type = 'unmanaged';
                    }
                    $html .= $this->_post_type_item($row,$type, TRUE);
                }
                else
                {
                    $html .= $this->_post_type_item($row);
                }

                $new_post_types[$row['channel_id']] = $row['channel_title'];
                $field_groups[$row['channel_id']] = is_null($row['field_group']) ? 0 : $row['field_group'];
            }
        }
        $this->post_types = $new_post_types;
        $this->field_groups = $field_groups;
        $this->data['post_types_dropdown'] = $html;
        $this->default_post_type = $default;
    }

    function get_pages($save_pages=FALSE)
    {
        $pages = $this->get('get_pages_by_project',array('id'=>$this->option('project_id')));
        $original = array();
        $new_pages = array();
        $parent_array = array();
        $meta_pages = array();
        if($pages && is_array($pages->pages))
        {
            foreach($pages->pages as $page)
            {
                $original[$page->id] = $page;
                $parent_id = $page->parent_id;
                if(!isset($parent_array[$parent_id]))
                {
                    $parent_array[$parent_id] = array();
                }
                $parent_array[$parent_id][$page->id] = $page;

                $this->page_count++;
            }
            foreach($parent_array as $parent_id => $page_array)
            {
                $array = $page_array;
                uasort($array,array(&$this,'sort_pages'));
                $parent_array[$parent_id] = $array;
            }
            if(isset($parent_array[0]))
            {
                foreach($parent_array[0] as $id => $page)
                {
                    $new_pages[$id] = $page;
                    $new_pages[$id]->children = $this->sort_recursive($parent_array,$id);
                }
            }
        }
        $this->pages = $new_pages;
        $this->original_array = $original;
        $this->meta_pages = $meta_pages;
        if($save_pages)
        {
            $project_id = ee()->gathercontent_settings->get('project_id');
            $saved_pages = ee()->gathercontent_settings->get('saved_pages', array());
            if(!is_array($saved_pages))
            {
               $saved_pages = array();
            }
            $saved_pages[$project_id] = array('pages' => $original, 'meta' => $meta_pages);
            ee()->gathercontent_settings->update('saved_pages', $saved_pages);
        }
    }

    function page_overwrite_dropdown()
    {
        $html = '';
        if($this->has_structure())
        {
            $types = array();
            $tmp = $this->sql->get_structure_channels('listing');
            if(is_array($tmp) && count($tmp) > 0)
            {
                foreach($tmp as $listing)
                {
                    $types[$listing['channel_id']] = 'listing';
                }
            }

            $tmp = $this->sql->get_structure_channels('asset');
            if(is_array($tmp) && count($tmp) > 0)
            {
                foreach($tmp as $asset)
                {
                    $types[$asset['channel_id']] = 'asset';
                }
            }

            $html .= $this->_get_tree_list();

            $page_ids = implode(',', $this->page_ids);
            $prefix = ee()->db->dbprefix;
            $sql = "SELECT entry_id, channel_id, title FROM ".$prefix."channel_titles WHERE site_id=".$this->site_id;
            count($this->page_ids) > 0 && $sql .= " AND entry_id NOT IN(".$page_ids.")";
            $sql .= " ORDER BY channel_id ASC, title ASC";
            $result = ee()->db->query($sql);
            if($result->num_rows() > 0)
            {
                foreach($result->result() as $row)
                {
                    $type = isset($types[$row->channel_id]) ? $types[$row->channel_id] : 'unmanaged';
                    $html .= '
        <li data-post-type="'.$row->channel_id.'" data-structure-type="'.$type.'">
            <a href="#" data-value="'.$row->entry_id.'">'.$row->title.'</a>
        </li>';
                }
            }
        }
        else
        {
            $prefix = ee()->db->dbprefix;
            $sql = "SELECT entry_id, channel_id, title FROM ".$prefix."channel_titles WHERE site_id=".$this->site_id;
            $sql .= " ORDER BY channel_id ASC, title ASC";
            $result = ee()->db->query($sql);
            if($result->num_rows() > 0)
            {
                foreach($result->result() as $row)
                {
                    $html .= '
        <li data-post-type="'.$row->channel_id.'">
            <a href="#" data-value="'.$row->entry_id.'">'.$row->title.'</a>
        </li>';
                }
            }
        }
        if($html != '')
            $html = '
            <li class="divider"></li>'.$html;

        $html = '
            <li>
                <a href="#" data-value="0">'.lang('gathercontent_new_entry').'</a>
            </li>'.$html;
        $this->data['overwrite_select'] = $html;
    }

    function get_structure_parents()
    {
        $html = '';
        if($this->has_structure())
        {
            $html = '<li>
                <a href="#" data-value="0">'.lang('gathercontent_none').'</a>
            </li>
            <li>
                <a href="#" data-value="_imported_page_">'.lang('gathercontent_imported_page').'</a>
            </li>
            <li class="divider"></li>'.$this->_get_tree_list();
        }
        $this->data['structure_parent'] = $html;
    }

    function _get_tree_list()
    {
        if(isset($this->data['structure_tree_list']))
        {
            return $this->data['structure_tree_list'];
        }

        $html = '';
        if($this->has_structure())
        {
            $tree = $this->sql->get_data();
            $ul_open = FALSE;
            $last_page_depth = 0;
            $i = 1;
            foreach ($tree as $eid => $page)
            {
                $this->page_ids[] = $page['entry_id'];
                $li_open = '<li data-post-type="'.$page['channel_id'].'" data-structure-type="page">';

                $title_str = '';
                if ($page['depth'] > $last_page_depth)
                {
                    $markup = "<ul class='page-list";

                    $markup .= "'>".$li_open."\n";

                    $ul_open = TRUE;
                }
                elseif ($i == 1)
                {
                    $markup = $li_open."\n";
                }
                elseif ($page['depth'] < $last_page_depth)
                {
                    $back_to = $last_page_depth - $page['depth'];
                    $markup  = "\n</li>";
                    $markup .= str_repeat("\n</ul>\n</li>\n", $back_to);
                    $markup .= $li_open."\n";
                    $ul_open = false;
                }
                else
                {
                    $markup = "\n</li>\n".$li_open."\n";
                }
                if($page['depth'] > 0)
                {
                    $title_str = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $page['depth']);
                    $title_str .= '↳';
                }
                $html .= $markup.'<a href="#" data-value="'.$page['entry_id'].'">'.$title_str.'<span>'.$page['title'].'</span></a>';

                $last_page_depth = $page['depth']; $i++;
            }

            $html  .= "\n</li>";
            $html .= str_repeat("</ul>\n</li>\n", $last_page_depth);
        }
        $this->data['structure_tree_list'] = $html;
        return $this->data['structure_tree_list'];
    }

    function map_to_dropdown()
    {
        $html = '
            <li data-post-type="all" class="live_filter">';
            $data = array(
                'type' => 'text',
                'class' => 'live_filter',
                'placeholder' => lang('gathercontent_search'),
            );
            $html .= form_input($data).'
            </li>
            <li data-post-type="all" data-search="'.lang('gathercontent_title').'">
                <a href="#" data-value="title" class="show-upload-select">'.lang('gathercontent_title').'</a>
            </li>';
        foreach($this->cat_groups as $id => $info)
        {
            $html .= '
            <li data-post-type="|'.implode('|',$info['channels']).'|" data-search="'.$this->attr($info['title']).'">
                <a href="#" data-value="_cat_group_'.$id.'" class="show-upload-select">'.$info['title'].'</a>
            </li>';
        }

        ee()->load->model('field_model');
        foreach($this->post_types as $id => $title)
        {
            if($this->field_groups[$id] > 0)
            {
                $custom_fields = ee()->field_model->get_fields($this->field_groups[$id], array('site_id' => $this->site_id));
                if($custom_fields->num_rows() > 0)
                {
                    foreach($custom_fields->result() as $row)
                    {
                        $show_upload_select = true;
                        $extra = '';
                        if($row->field_type == 'file')
                        {
                            $data = $this->get_ee_field_settings($row);
                            if(isset($data['allowed_directories']) &&
                             ($data['allowed_directories'] != 'all' || $data['allowed_directories'] > 0))
                            {
                                $show_upload_select = false;
                                $extra = ' data-upload-dir="'.$data['allowed_directories'].'"';
                            }
                            else
                            {
                                $this->data['show_upload_select'] = true;
                            }

                        }
                        $html .= '
            <li data-post-type="|'.$id.'|" data-search="'.$this->attr($row->field_label).'">
                <a href="#" class="'.($show_upload_select? 'show':'hide').'-upload-select" data-value="'.$row->field_id.'"'.$extra.'>'.$row->field_label.'</a>
            </li>';
                    }
                }
            }
        }
        $html .= '
            <li class="divider" data-post-type="all"></li>
            <li data-post-type="all" data-search="'.$this->attr(lang('gathercontent_dont_import')).'">
                <a href="#" data-value="_dont_import_" class="hide-upload-select">'.lang('gathercontent_dont_import').'</a>
            </li>';
        $this->data['map_to_select'] = $html;
    }

    function categories_dropdown()
    {
        $html = '';
        $channel_ids = array();
        foreach($this->cat_groups as $id => $info)
        {
            $results = ee()->category_model->get_channel_categories($id);
            if($results->num_rows > 0)
            {
                $channel_ids += $info['channels'];
                $types = '|'.implode('|',$info['channels']).'|';
                $html .= '
            <li data-post-type="'.$types.'">
                <label>'.$info['title'].'</label>
            </li>';
                foreach($results->result_array() as $row)
                {
                    $html .= '
            <li data-post-type="'.$types.'">
                <a href="#" data-value="'.$row['cat_id'].'">'.$row['cat_name'].'</a>
            </li>';
                }
            }
        }
        $this->data['category_select'] = $html;
    }

    function upload_dropdown()
    {
        if(!isset($this->data['show_upload_select']))
        {
            $this->data['upload_select'] = '';
        }

        ee()->load->model('file_upload_preferences_model');

        $html = '';

        $uploads = ee()->file_upload_preferences_model->get_file_upload_preferences();
        foreach($uploads as $id => $row)
        {
            $html .= '
            <li>
                <a href="#" data-value="'.$id.'">'.$row['name'].'</a>
            </li>';

        }
        $this->data['upload_select'] = $html;
    }

    function dropdown_html($val,$html,$input=FALSE,$real_val='')
    {
        return '
        <div class="btn-group has_input">
            <a class="btn dropdown-toggle" data-toggle="dropdown" href="#">
                '.$val.'
                <span class="caret"></span>'.($input!==FALSE?'<input type="hidden" name="'.$input.'" value="'.$this->attr($real_val).'" />':'').'
            </a>
            <ul class="dropdown-menu">
                '.$html.'
            </ul>
        </div>';
    }

    function generate_settings($array,$index=-1,$show_settings=FALSE)
    {
        $out = '';
        $index++;
        $selected = $this->option('selected_pages');
        if(!$this->foreach_safe($selected))
            $selected = array();
        foreach($array as $id => $page)
        {
            if($show_settings && !in_array($id, $selected))
            {
                if(isset($page->children) && count($page->children) > 0)
                    $out .= $this->generate_settings($page->children,$index,$show_settings);
                continue;
            }
            $checked = $show_settings;
            $cur_settings = array();
            if(isset($this->data['saved_settings'][$id]))
                $cur_settings = $this->data['saved_settings'][$id];
            $add = '';

            $parent_id = $page->parent_id;

            $config = $this->get_field_config($page);

            $field_count = $this->val($config, 'field_count', 0);

            $show_fields = true;
            if($show_settings && $field_count == 0)
            {
                $show_fields = false;
            }
            $out .= '
                <tr class="gc_page'.($checked?' checked':'').'" data-page-state="'.$page->custom_state_id.'">
                    <td class="gc_status"><span class="page-status page-state-color-'.$this->data['states'][$page->custom_state_id]->color_id.'"></span></td>
                    <td class="gc_pagename">';


            if($index > 0)
            {
                for($i=0; $i<$index; $i++)
                    $out .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                $out .= '↳';
            }

            $out .= ' <label for="import_'.$id.'">'.$page->name.'</label></td>
                    <td class="gc_checkbox">'.($show_fields === TRUE ?'<input type="checkbox" name="import_'.$id.'" id="import_'.$id.'" value="'.$id.'"'.($checked?' checked="checked"':'').' /><input type="hidden" name="page_id[]" value="'.$id.'" />':'').'</td>
                </tr>';

            if($show_settings)
            {
                if($show_fields === TRUE)
                {
                    $parent_id_value = ee()->gathercontent_settings->get_parent_page_id($parent_id, $selected, $cur_settings);
                    $add = '
                    <tr class="gc_table_row" data-page-id="'.$id.'" data-parent-id="'.$parent_id.'">
                        <td colspan="3" class="gc_settings_container">
                            <div>
                                <div class="gc_settings_header gc_cf">
                                    <div class="gc_cf">
                                        <div class="gc_setting gc_import_as" id="gc_import_as_'.$id.'">
                                            <label>'.lang('gathercontent_import_as').' </label>
                                            '.$this->dropdown_html('<span></span>',$this->data['post_types_dropdown'],'gc[post_type][]',$this->val($cur_settings,'post_type')).'
                                        </div>
                                        <div class="gc_setting gc_import_to" id="gc_import_to_'.$id.'">
                                            <label>'.lang('gathercontent_import_to').' </label>
                                            '.$this->dropdown_html('<span></span>',$this->data['overwrite_select'],'gc[overwrite][]',$this->val($cur_settings,'overwrite')).'
                                        </div>'.($this->data['category_select'] != ''?'
                                        <div class="gc_setting gc_category" id="gc_category_'.$id.'">
                                            <label>'.lang('gathercontent_category').' </label>
                                            '.$this->dropdown_html('<span>'.lang('gathercontent_choose_category').'</span>',$this->data['category_select'],'gc[category][]',$this->val($cur_settings,'category','-1')).'
                                        </div>':'').($this->data['structure_parent'] != ''?'
                                        <div class="gc_setting gc_parent" id="gc_parent_'.$id.'">
                                            <label>'.lang('gathercontent_parent').' </label>
                                            '.$this->dropdown_html('<span></span>',$this->data['structure_parent'],'gc[structure_parent][]',$parent_id_value).'
                                        </div>':'').'
                                    </div>
                                    <div class="gc_setting repeat_config">
                                        <label>'.lang('gathercontent_repeat').' <input type="checkbox" id="gc_repeat_'.$id.'" name="gc[repeat_'.$id.']" value="Y" /></label>
                                    </div>
                                </div>
                                <div class="gc_settings_fields" id="gc_fields_'.$id.'">';

                                $field_settings = $this->val($cur_settings,'fields',array());

                                if(count($field_settings) > 0)
                                {
                                    foreach($field_settings as $name => $map_to)
                                    {
                                        $upload_val = '';
                                        if(is_array($map_to))
                                        {
                                            extract($map_to);
                                        }
                                        list($tab,$field_name) = explode('_',$name,2);
                                        if(isset($config[$tab]) && isset($config[$tab]['elements'][$field_name]))
                                        {
                                            $add .= $this->field_settings($id, $config[$tab]['elements'][$field_name], $tag, $config[$tab]['label'], $map_to, $upload_val);
                                            unset($config[$tab]['elements'][$field_name]);
                                        }
                                    }
                                }

                                unset( $config['field_count'] );

                                foreach($config as $tab_name => $tab)
                                {

                                    foreach($tab['elements'] as $field)
                                    {
                                        $upload_val = '';
                                        $map_to = $this->val($field_settings,$tab_name.'_'.$field['name']);
                                        if(is_array($map_to))
                                        {
                                            extract($map_to);
                                        }
                                        $add .= $this->field_settings($id,$field,$tab_name,$tab['label'],$map_to,$upload_val);
                                    }
                                }
                                $add .= '
                                </div>
                            </div>
                        </td>
                    </tr>';
                }
                else
                {
                    $message = lang('gathercontent_empty_page');
                    $message = sprintf($message,
                        '<a href="https://'.$this->option('api_url').'.gathercontent.com/pages/view/'.$this->option('project_id').'/'.$id.'" target="_blank">',
                        '</a>');
                    $add = '
                    <tr>
                        <td colspan="3 id="gc_fields_'.$id.'"">
                            <div class="alert alert-info">'.$message.'</div>
                        </td>
                    </tr>';
                }
            }
            $out .= $add;
            if(isset($page->children) && count($page->children) > 0)
                $out .= $this->generate_settings($page->children,$index,$show_settings);
        }

        return $out;
    }

    function field_settings($id,$field,$tab='content',$tab_label='',$val='',$upload_val='')
    {
        if($field['type'] == 'section')
            return '';
        $fieldid = $id.'_'.md5($tab.'_'.$field['label']);
        $counter = 0;
        while(isset($this->ids_used[$fieldid]))
        {
            $fieldid = $fieldid.$counter++;
        }
        $this->ids_used[$fieldid] = true;
        $html = '
        <div class="gc_settings_field gc_cf" data-field-tab="'.$tab.'" data-field-type="'.$field['type'].'" id="field_'.$fieldid.'">
            <div class="gc_move_field"></div>
            <div class="gc_field_name gc_left"><div class="gc_tab_name gc_tooltip" title="' . $this->attr(lang('gathercontent_tab')) . '">' . $tab_label . '</div>'.$field['label'].'</div>'.($field['type'] == 'files' ? '
            <div class="gc_file_field gc_right" id="gc_upload_dir_'.$fieldid.'">
                <span>'.lang('gathercontent_upload_location').'</span>
                '.$this->dropdown_html('<span></span>', $this->data['upload_select'],'gc[file_dir]['.$id.'][]', $upload_val).'
            </div>':'').'
            <div class="gc_field_map gc_right" id="gc_field_map_'.$fieldid.'">
                <span>'.lang('gathercontent_map_to').'</span>
                '.$this->dropdown_html('<span></span>',$this->data['map_to_select'],'gc[map_to]['.$id.'][]',$val).'
            </div>
            <input type="hidden" name="gc[field_tab]['.$id.'][]" value="'.$tab.'" />
            <input type="hidden" name="gc[field_name]['.$id.'][]" value="'.$field['name'].'" />
        </div>
        ';
        return $html;
    }

    function _curl($url,$curl_opts=array())
    {
        @set_time_limit(60);
        $session = curl_init();

        curl_setopt($session, CURLOPT_URL, $url);
        curl_setopt($session, CURLOPT_HEADER, FALSE);
        //curl_setopt($session, CURLOPT_TIMEOUT, 50);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($session, CURLOPT_CAINFO, PATH_THIRD.'gathercontent/cacert.pem');

        curl_setopt_array($session, $curl_opts);

        $response = curl_exec($session);
        $httpcode = curl_getinfo($session, CURLINFO_HTTP_CODE);
        curl_close($session);
        return array('response' => $response, 'httpcode' => $httpcode);
    }

    function get($command = '', $postfields = array())
    {
        $api_url = 'https://'.$this->option('api_url').'.gathercontent.com/api/0.3/'.$command;
        $curl_opts = array(
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_USERPWD => $this->option('api_key') . ":x",
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query($postfields)
        );
        extract($this->_curl($api_url,$curl_opts));

        try {
            $resp = json_decode($response);

            if(isset($resp->success) && $resp->success === TRUE)
            {
                return $resp;
            }
            elseif(isset($resp->error))
            {
                $error = $resp->error;
                if($error == 'You have to log in.')
                    $error = $this->auth_error();
                $this->error = lang($error);
            }
            else
            {
                $this->error = $this->auth_error();
            }
        }
        catch(Exception $e)
        {
            $this->error = lang('gathercontent_curl_error_2');
        }

        return FALSE;
    }

    function auth_error()
    {
        return sprintf(lang('gathercontent_curl_error_1'),'<a href="'.$this->url('login',FALSE).'">','</a>');
    }

    function sort_recursive($pages,$current=0)
    {
        $children = array();
        if(isset($pages[$current]))
        {
            $children = $pages[$current];
            foreach($children as $id => $page)
                $children[$id]->children = $this->sort_recursive($pages,$id);
        }
        return $children;
    }

    function sort_pages($a,$b)
    {
        if($a->position == $b->position)
        {
            if($a->id == $b->id)
                return 0;
            else
                return ($a->id < $b->id) ? -1 : 1;
        }
        return ($a->position < $b->position) ? -1 : 1;
    }
}
