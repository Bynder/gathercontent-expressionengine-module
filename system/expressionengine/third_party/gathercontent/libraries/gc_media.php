<?php

require_once PATH_THIRD.'gathercontent/libraries/gc_curl.php';
class Gc_media extends Gc_curl {

	function import_file()
	{
		$out = array('error' => lang('gathercontent_import_error_1'));
		$cur_num = $_GET['cur_num'];
		$cur_total = $_GET['cur_total'];
		$retry = $_GET['cur_retry'];

		$media = $this->option('media_files');
		$total = $media['total_files'];
		unset($media['total_files']);

		$post_id = key($media);
		if($this->foreach_safe($media[$post_id]['files']))
		{
			$cur_post = $media[$post_id];
			$page_total = $cur_post['total_files'];
			$more_than_1 = (count($cur_post['files'][0]) > 1);
			$file = array_shift($cur_post['files'][0]);
			if(!$more_than_1)
			{
				array_shift($cur_post['files']);
			}

			ee()->load->model('file_model');

			ee()->load->model('file_upload_preferences_model');

			$prefs = ee()->file_upload_preferences_model->get_file_upload_preferences(
				'1', // Overriding the group ID to get all IDs
				$file['upload_dir'],
				FALSE
			);

			$url_base = $prefs['url'];

			ee()->load->library('filemanager');

			ee()->db->select('m.fid');
			ee()->db->from('gathercontent_media AS m');
			ee()->db->where('m.gid', $file['id']);
			$query = ee()->db->get();
			if($query->num_rows() > 0)
			{

				$fid = $query->row('fid');

				$file_obj = ee()->file_model->get_files_by_id($fid)->row();				

				$mime = get_mime_by_extension($file_obj->rel_path);

				$is_image = ee()->filemanager->is_image($mime);

				$file['new_file'] = $file_obj->rel_path;
				$file['title'] = $file_obj->file_name;
				$file['file_name'] = $file_obj->file_name;
				$file['url'] = $url_base.'/'.$file_obj->file_name;
				$file['new_id'] = $fid;
				$file['is_image'] = $is_image;

				$this->add_media_to_content($post_id,$file,$more_than_1);

				$out = $this->get_media_ajax_output($post_id,$media,$cur_post,$page_total,$total);
				$out['success'] = true;
				$out['new_file'] = $file_obj->rel_path;
				
			}
			else
			{

				$new_file = ee()->filemanager->clean_filename($file['original_filename'], $file['upload_dir'], array('ignore_dupes' => FALSE));

				$fp = fopen($new_file,'w');
				$resp = $this->_curl('https://gathercontent.s3.amazonaws.com/'.$file['filename'],array(CURLOPT_FILE => $fp));
				fclose($fp);

				@chmod($new_file, FILE_WRITE_MODE);

				$filename = basename($new_file);

				if($resp['httpcode'] == 200)
				{

					ee()->load->helper('file');

					$mime = get_mime_by_extension($new_file);

					$is_image = ee()->filemanager->is_image($mime);

					$url = $url_base.$filename;

					$path = dirname($new_file);

					if($is_image)
					{

						$dimensions = ee()->file_model->get_dimensions_by_dir_id($file['upload_dir']);
						$dimensions = $dimensions->result_array();
						ee()->filemanager->create_thumb(
							$new_file, 
							array(
								'server_path' => $path,
								'file_name' => $filename,
								'mime_type' => $mime,
								'dimensions' => $dimensions,
							),
							TRUE,
							FALSE
						);
					}
					
					$thumb_info = ee()->filemanager->get_thumb($filename, $file['upload_dir']);

					$file_data = array(
						'upload_location_id'	=> $file['upload_dir'],
						'site_id'				=> ee()->config->item('site_id'),

						'file_name'				=> $filename,
						'orig_name'				=> $file['original_filename'],
						'file_data_orig_name'	=> $file['original_filename'],

						'is_image'				=> $is_image,
						'mime_type'				=> $mime,

						'rel_path'				=> $new_file,
						'file_thumb'			=> $thumb_info['thumb'],
						'thumb_class' 			=> $thumb_info['thumb_class'],

						'modified_by_member_id' => ee()->session->userdata('member_id'),
						'uploaded_by_member_id'	=> ee()->session->userdata('member_id'),

						'file_size'				=> $file['size'] * 1024,
					);

					$file_id = ee()->file_model->save_file($file_data);

					if($file_id !== false)
					{
						//success
						$file['new_file'] = $new_file;
						$file['title'] = $filename;
						$file['file_name'] = $filename;
						$file['url'] = $url_base.$filename;
						$file['new_id'] = $file_id;
						$file['is_image'] = $is_image;

						$data = array(
							'fid' => $file_id,
							'gid' => $file['id'],
						);

						ee()->db->insert('gathercontent_media', $data);

						$this->add_media_to_content($post_id,$file,$more_than_1);

						$out = $this->get_media_ajax_output($post_id,$media,$cur_post,$page_total,$total);
						$out['success'] = true;
						$out['new_file'] = $new_file;
					}
					else
					{
						ee()->file_model->delete_raw_file($filename, $file['upload_dir'], FALSE);

						if($retry == '1')
						{
							$out = $this->get_media_ajax_output($post_id,$media,$cur_post,$page_total,$total);
							$out['success'] = false;
							$out['error'] = sprintf(lang('gathercontent_import_error_4'), $new_file);
						}
						else
						{
							$out = array(
								'success'=>false,
								'retry'=>true,
								'msg'=>sprintf(lang('gathercontent_import_error_5'), $file['original_filename'])
							);
						}
					}
				}
				else
				{
					ee()->file_model->delete_raw_file($filename, $file['upload_dir'], FALSE);

					if($retry == '1')
					{
						$out = $this->get_media_ajax_output($post_id,$media,$cur_post,$page_total,$total);
						$out['success'] = false;
						$out['error'] = sprintf(lang('gathercontent_import_error_6'), $file['original_filename']);
					}
					else
					{
						$out = array(
							'success'=>false,
							'retry'=>true,
							'msg'=>sprintf(lang('gathercontent_import_error_5'), $file['original_filename'])
						);
					}
					//failed
				}
			}
		}

		return $out;
	}
}