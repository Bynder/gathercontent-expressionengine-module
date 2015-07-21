<?php
class Gc_functions {

    var $plugin_url;
    var $step_error = FALSE;
    var $step;
    var $site_id;

    function __construct()
    {
        $this->plugin_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=gathercontent';
        $this->site_id = ee()->config->item('site_id');
        ee()->load->model('category_model');
    }

    function custom_state_color($color_id, $color_custom) {

        $colors = array(
            1 => '#C5C5C5',
            2 => '#FAA732',
            3 => '#5EB95E',
            4 => '#0E90D2',
            5 => '#ECD815',
            6 => '#DD4398',
            7 => '#954F99',
            9999 => $this->custom_color_hex($color_custom)
        );

        return $colors[$color_id];
    }

    function custom_color_hex($color_custom) {

        if(empty($color_custom)) {
            $color_custom = '#999999';
        }

        return $color_custom;
    }

    function attr($val)
    {
        return htmlspecialchars($val, ENT_QUOTES);
    }

    function get_item_title_array($post_id)
    {
        $data = array();

        ee()->db->select('t.title');
        ee()->db->from('channel_titles AS t');
        ee()->db->where('t.entry_id', $post_id);

        $post = ee()->db->get();

        if($post->num_rows())
        {
            $title = $post->row('title');
        }
        else
        {
            $title = '';
        }
        $title = empty($title) ? '(no title)' : $title;
        $data['original_title'] = strip_tags($title);
        if(strlen($title) > 30)
        {
            $title = substr($title,0,27).'...';
        }
        $data['item_title'] = $title;
        return $data;
    }

    function percent($num,$total)
    {
        return number_format((($num / $total) * 100),2);
    }

    function foreach_safe($arr)
    {
        if(is_array($arr) && count($arr) > 0)
        {
            return TRUE;
        }
        return FALSE;
    }

    function url($key='',$echo=TRUE)
    {
        $url = $this->plugin_url.AMP.'method='.$key;
        if(!$echo)
        {
            return $url;
        }
        echo $url;
    }

    function enqueue($handle,$type='script')
    {
        if($type == 'script')
        {
            wp_enqueue_script($this->base_name.'-'.$handle);
        }
        else
        {
            wp_enqueue_style($this->base_name.'-'.$handle);
        }
    }

    function admin_print_scripts()
    {
        $this->enqueue('main');
        $this->enqueue($this->step());
    }

    function admin_print_styles()
    {
        $this->enqueue('main','style');
        $step = $this->step();
        if($step == 'items' || $step == 'item_import')
        {
            $this->enqueue('items','style');
        }
    }

    function option($key)
    {
        return ee()->gathercontent_settings->get($key);
    }

    function val($array,$field,$default='')
    {
        if(is_array($array) && isset($array[$field]))
        {
            return $array[$field];
        }
        return $default;
    }

    function add_media_to_content($post_id,$file,$more_than_1=FALSE)
    {
        ee()->load->model('channel_entries_model');

        $entry_data = ee()->channel_entries_model->get_entry_data(array($post_id));
        $entry_data = $entry_data[0];

        $channel_id = ee()->channel_entries_model->get_entry($post_id)->row('channel_id');

        ee()->load->library('gc_item');

        $save_data = array();

        if($file['field_type'] == 'file')
        {
            $save_data['field_id_'.$file['field']] = '{filedir_'.$file['upload_dir'].'}'.$file['file_name'];
        }
        else
        {
            $tag = '#_gc_file_name_'.$file['counter'].'#';
            if($file['is_image'])
                $html = '<a href="'.$file['url'].'"><img src="'.$file['url'].'" alt="" /></a>';
            else
                $html = '<a href="'.$file['url'].'">'.$file['title'].'</a>';
            if($more_than_1)
                $html .= $tag;

            if(is_numeric($file['field']))
            {
                $save_data['field_id_'.$file['field']] = str_replace($tag, $html, $entry_data['field_id_'.$file['field']]);
            }
            else
            {
                $save_data[$file['field']] = str_replace($tag, $html, $entry_data[$file['field']]);
            }

        }

        $save_data['entry_id'] = $post_id;
        $save_data['channel_id'] = $entry_data['channel_id'];

        ee()->gc_item->save_gathercontent_item($save_data, $post_id, $entry_data['channel_id']);

    }

    function get_media_ajax_output($post_id,$media,$cur_post,$item_total,$total)
    {
        $cur_num = $_GET['cur_num'];
        $cur_total = $_GET['cur_total'];

        $next_id = $post_id;
        if($cur_num == $item_total)
        {
            $item_percent = 100;
            $cur_num = 1;
            unset($media[$post_id]);
            $next_id = key($media);
        }
        else
        {
            $item_percent = $this->percent($cur_num,$item_total);
            $cur_num++;
            $media[$post_id] = $cur_post;
        }
        $media['total_files'] = $total;
        ee()->gathercontent_settings->update('media_files', $media);
        if($cur_total == $total)
        {
            $next_id = $post_id;
            $item_percent = $overall_percent = '100';
        }
        else
        {
            $overall_percent = $this->percent($cur_total,$total);
        }
        $cur_total++;

        $data = $this->get_item_title_array($next_id);

        $out = array(
            'item_percent' => $item_percent,
            'overall_percent' => $overall_percent,
            'cur_num' => $cur_num,
            'cur_total' => $cur_total,
            'item_title' => $data['item_title'],
            'original_title' => $data['original_title'],
        );
        return $out;
    }
}
