<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GatherContent Import Module Control Panel File
 *
 * @package		GatherContent Import
 * @author		Mathew Chapman
 * @copyright   Copyright (c) 2013 GatherContent
 * @link		http://www.gathercontent.com
 */

class Gathercontent_mcp {

	private $_base_url;

	private $_theme_url;

	private $_form_base;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		ee()->load->model('gathercontent_settings');
		$this->_form_base = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=gathercontent';
		$this->_base_url = BASE.AMP.$this->_form_base;
		$this->_theme_url = URL_THIRD_THEMES.'gathercontent/';

		$nav = array(
			'module_home' => $this->_base_url,
			'module_account' => $this->_base_url.AMP.'method=login',
		);
		ee()->cp->set_right_nav($nav);
	}

	// ----------------------------------------------------------------

	/**
	 * Index Function
	 *
	 * @return 	void
	 */
	public function index()
	{
		$this->_check_step('projects');
		ee()->load->library('form_validation');
		ee()->load->helper('form');
		$rules = array(
			array(
				'field' => 'project_id',
				'label' => lang('gathercontent_project'),
				'rules' => 'required',
			),
		);
		ee()->form_validation->set_rules($rules);
		if(ee()->form_validation->run())
		{
			$this->_set_project_id(ee()->input->post('project_id'));
		}

		ee()->load->library('gc_curl');
		ee()->view->cp_page_title = lang('gathercontent_module_name');
		ee()->gc_curl->get_projects();
		$data = array(
			'project_id' => ee()->gathercontent_settings->get('project_id'),
			'projects' => ee()->gc_curl->data['projects'],
			'action_url' => $this->_form_base,
			'submit_button' => $this->_submit_button('gathercontent_submit_projects'),
		);
		return ee()->load->view('projects', $data, TRUE);
	}

	public function set_project_id()
	{
		$this->_set_project_id(ee()->input->get('project_id'));
	}

	private function _set_project_id($id)
	{
		ee()->gathercontent_settings->update('project_id', $id);
		ee()->functions->redirect($this->_base_url.AMP.'method=items');
	}

	public function login()
	{
		ee()->load->library('form_validation');
		ee()->load->helper('form');
		$rules = array(
			array(
				'field' => 'api_url',
				'label' => lang('api_url'),
				'rules' => 'required',
			),
			array(
				'field' => 'api_key',
				'label' => lang('api_key'),
				'rules' => 'required',
			),
		);
		ee()->form_validation->set_rules($rules);
		if(ee()->form_validation->run())
		{
			$updates = array(
				'api_url' => $this->input->post('api_url'),
				'api_key' => $this->input->post('api_key'),
			);
			ee()->gathercontent_settings->update($updates);
			ee()->functions->redirect($this->_base_url);
		}

		$this->_check_step('login');
		ee()->cp->set_breadcrumb(
		    $this->_base_url,
		    lang('gathercontent_module_name')
		);
		ee()->view->cp_page_title = lang('gathercontent_login');
		$data = array(
			'api_key' => ee()->gathercontent_settings->get('api_key'),
			'api_url' => ee()->gathercontent_settings->get('api_url'),
			'action_url' => $this->_form_base.AMP.'method=login',
			'submit_button' => $this->_submit_button('gathercontent_submit_login'),
		);
		return ee()->load->view('login', $data, TRUE);
	}

	public function items()
	{
		$this->_check_step('items');
		ee()->load->library('form_validation');
		ee()->load->helper('form');
		$rules = array(
			array(
				'field' => 'item_id',
				'label' => lang('gathercontent_items'),
				'rules' => 'required',
			),
		);
		ee()->form_validation->set_rules($rules);
		if(ee()->form_validation->run())
		{
			$item_ids = $this->input->post('item_id');
			$import = array();
			foreach($item_ids as $id)
			{
				if($val = $this->input->post('import_'.$id))
				{
					$import[] = $id;
				}
			}
			if(count($import) > 0)
			{
				ee()->gathercontent_settings->update('selected_items',$import);
				ee()->functions->redirect($this->_base_url.AMP.'method=item_import');
			}
		}

		ee()->load->library('gc_curl');
		ee()->cp->set_breadcrumb(
		    $this->_base_url,
		    lang('gathercontent_module_name')
		);
		ee()->view->cp_page_title = lang('gathercontent_items');
		ee()->cp->add_to_head('<link rel="stylesheet" href="'.$this->_theme_url.'css/items.css">');

		ee()->gc_curl->get_projects();
		ee()->gc_curl->get_states();
		ee()->gc_curl->get_items();
		ee()->gc_curl->get_state_dropdown();
		ee()->gc_curl->get_projects_dropdown();
		$data = ee()->gc_curl->data;
		$data += array(
			'item_count' => ee()->gc_curl->item_count,
			'item_settings' => ee()->gc_curl->generate_settings(ee()->gc_curl->items),
			'_base_url' => $this->_base_url,
			'action_url' => $this->_form_base.AMP.'method=items',
			'submit_button' => $this->_submit_button('gathercontent_submit_items'),
		);
		return ee()->load->view('items', $data, TRUE);
	}

	public function item_import()
	{

		$this->_check_step('item_import');

		ee()->load->library('gc_curl');
		ee()->cp->set_breadcrumb(
		    $this->_base_url,
		    lang('gathercontent_module_name')
		);
		ee()->view->cp_page_title = lang('gathercontent_items');
		ee()->cp->add_to_head('<link rel="stylesheet" href="'.$this->_theme_url.'css/items.css">');
		ee()->cp->load_package_js('items_import');

		ee()->gathercontent_settings->update('media_files', array());

		ee()->gc_curl->get_post_types();
		ee()->gc_curl->item_overwrite_dropdown();
		ee()->gc_curl->map_to_dropdown();
		ee()->gc_curl->upload_dropdown();
		ee()->gc_curl->categories_dropdown();
		ee()->gc_curl->get_states();
		ee()->gc_curl->get_structure_parents();
		ee()->gc_curl->get_items(TRUE);


		$js_settings = array(
			'xid' => XID_SECURE_HASH,
			'error_message' => lang('gathercontent_import_error_3')
		);

		ee()->cp->add_to_foot('
		<script type="text/javascript">
			var gc_import = ' . json_encode($js_settings) . ';
		</script>');

		$data = ee()->gc_curl->data;

		$cur_settings = ee()->gathercontent_settings->get('saved_settings', array());
		if(!is_array($cur_settings))
		{
			$cur_settings = array();
		}

		$project_id = ee()->gathercontent_settings->get('project_id');
		$cur_settings = isset($cur_settings[$project_id]) ? $cur_settings[$project_id] : array();
		ee()->gc_curl->data['saved_settings'] = $cur_settings;

		$data += array(
			'project_id' => $project_id,
			'item_count' => ee()->gc_curl->item_count,
			'item_settings' => ee()->gc_curl->generate_settings(ee()->gc_curl->items,-1,true),
			'_base_url' => $this->_base_url,
			'_theme_url' => $this->_theme_url,
			'action_url' => $this->_form_base.AMP.'method=item_import',
			'submit_button' => $this->_submit_button('gathercontent_submit_items_import'),
		);

		ee()->gc_curl->data = $data;

		return ee()->load->view('items_import', $data, TRUE);
	}

	public function import_item()
	{
		ee()->load->library('gc_item');
		echo json_encode(ee()->gc_item->import_item());
		exit;
	}

	public function media()
	{

		$this->_check_step('media');
		ee()->cp->set_breadcrumb(
		    $this->_base_url,
		    lang('gathercontent_module_name')
		);
		ee()->view->cp_page_title = lang('gathercontent_media');

		$media = ee()->gathercontent_settings->get('media_files', array());

		if(!(is_array($media) && isset($media['total_files']) && $media['total_files'] > 0 && count($media) > 1))
		{
			ee()->functions->redirect($this->_base_url.AMP.'method=finished');
		}

		ee()->cp->load_package_js('media');

		unset($media['total_files']);

		ee()->load->library('gc_functions');
		$post_id = key($media);
		$data = ee()->gc_functions->get_item_title_array($post_id);

		$data += array(
			'_base_url' => $this->_base_url,
			'_theme_url' => $this->_theme_url,
		);

		return ee()->load->view('media', $data, TRUE);
	}

	public function import_media()
	{
		ee()->load->library('gc_media');

		$output = ee()->gc_media->import_file();

		echo json_encode($output);
		exit;
	}

	public function finished()
	{
		$this->_check_step('finished');
		ee()->cp->set_breadcrumb(
		    $this->_base_url,
		    lang('gathercontent_module_name')
		);
		ee()->view->cp_page_title = lang('gathercontent_finished');

		$project_id = ee()->gathercontent_settings->get('project_id');
		$saved_items = ee()->gathercontent_settings->get('saved_items');
		if(is_array($saved_items) && isset($saved_items[$project_id]))
		{
			unset($saved_items[$project_id]);
			ee()->gathercontent_settings->update('saved_items', $saved_items);
		}

		$data = array(
			'_base_url' => $this->_base_url,
		);
		return ee()->load->view('finished', $data, TRUE);
	}

	private function _check_step($step='')
	{
		$checks = array('projects', 'login', 'items', 'item_import', 'media', 'finished');
		$step = in_array($step,$checks) ? $step : 'projects';

		$checks = array(
			'projects' => array('fields'=>array('api_key','api_url'),'prev'=>'login'),
			'items' => array('fields'=>array('project_id'),'prev'=>'projects'),
			'item_import' => array('fields'=>array('project_id','selected_items'),'prev'=>'projects'),
			'media' => array('fields'=>array('project_id','selected_items'),'prev'=>'projects'),
		);

		if(isset($checks[$step]))
		{
			$error = FALSE;
			foreach($checks[$step]['fields'] as $chk)
			{
				if(ee()->gathercontent_settings->get($chk,'') == '')
				{
					$error = TRUE;
				}
			}
			if($error)
			{
				$step = $checks[$step]['prev'];
				ee()->functions->redirect($this->_base_url.($step == 'projects' ? '':AMP.'method='.$step));
				return FALSE;
			}
		}
		ee()->cp->add_to_head('<link rel="stylesheet" href="'.$this->_theme_url.'css/main.css">');
		ee()->cp->load_package_js('bootstrap.min');
		ee()->cp->load_package_js('main');
		return TRUE;
	}

	private function _submit_button($text,$tag='button',$ext='')
	{
		$html = '<'.$tag;
		if($tag == 'button')
		{
			$html .= ' type="submit"';
		}
		return $html.' class="btn btn-success gc_ajax_submit_button"'.$ext.'><img src="'.$this->_theme_url.'images/ajax-loader.gif" /> <span>'.lang($text).'</span></'.$tag.'>';
	}
}
/* End of file mcp.gathercontent.php */
/* Location: /system/expressionengine/third_party/gathercontent/mcp.gathercontent.php */
