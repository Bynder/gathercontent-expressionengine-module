<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GatherContent Import Module Control Panel File
 *
 * @package		GatherContent Import
 * @author		Mathew Chapman
 * @copyright   Copyright (c) 2013 GatherContent
 * @link		http://www.gathercontent.com
 */

class Gathercontent_ext {

    var $version        = '1.0';
    var $settings_exist = 'n';
    var $docs_url       = '';

    function delete_file($files)
    {
    	if(is_array($files) && count($files) > 0)
    	{
    		foreach($files as $file)
    		{
    			ee()->db->where('fid', $file->file_id);
    			ee()->db->delete('gathercontent_media');
    		}
    	}
    }

	function activate_extension()
	{

	    $data = array(
	        'class'     => __CLASS__,
	        'method'    => 'delete_file',
	        'hook'      => 'files_after_delete',
	        'priority'  => 10,
	        'version'   => $this->version,
	        'enabled'   => 'y'
	    );

	    ee()->db->insert('extensions', $data);
	}
	
	function disable_extension()
	{
	    ee()->db->where('class', __CLASS__);
	    ee()->db->delete('extensions');
	}
}