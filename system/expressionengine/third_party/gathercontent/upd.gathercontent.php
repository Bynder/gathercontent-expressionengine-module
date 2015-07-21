<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GatherContent Import Module Install/Update File
 *
 * @package		GatherContent Import
 * @author		Mathew Chapman
 * @copyright   Copyright (c) 2013 GatherContent
 * @link		http://www.gathercontent.com
 */

class Gathercontent_upd {

	public $version = '1.0';

	// ----------------------------------------------------------------

	/**
	 * Installation Method
	 *
	 * @return 	boolean 	TRUE
	 */
	public function install()
	{
        $data = array(
            'module_name' => 'Gathercontent',
            'module_version' => $this->version,
            'has_cp_backend' => 'y',
            'has_publish_fields' => 'n',
        );
        ee()->db->insert('modules', $data);

        $data = array(
            'class' => 'Gathercontent_mcp',
            'method' => 'import_item',
        );
        ee()->db->insert('actions', $data);

        $fields = array(
            'id' => array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'null' => FALSE, 'auto_increment' => TRUE),
            'fid' => array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'null' => FALSE),
            'gid' => array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'null' => FALSE),
        );
        ee()->load->dbforge();
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', TRUE);
        ee()->dbforge->add_key('fid');
        ee()->dbforge->add_key('gid');
        ee()->dbforge->create_table('gathercontent_media');


        $fields = array(
            'id' => array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'null' => FALSE, 'auto_increment' => TRUE),
            'site_id' => array('type' => 'int', 'constraint' => '4', 'unsigned' => TRUE, 'null' => FALSE, 'default' => '1'),
            'option_name' => array('type' => 'varchar', 'constraint' => '60', 'null' => FALSE),
            'option_value' => array('type' => 'longtext', 'null' => FALSE)
        );
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', TRUE);
        ee()->dbforge->add_key('option_name');
        ee()->dbforge->create_table('gathercontent_settings');

        return TRUE;
	}

	// ----------------------------------------------------------------

	/**
	 * Uninstall
	 *
	 * @return 	boolean 	TRUE
	 */
	public function uninstall()
	{
        ee()->db->where('module_name', 'Gathercontent');
        ee()->db->delete('modules');

        ee()->load->dbforge();
        ee()->dbforge->drop_table('gathercontent_media');
        ee()->dbforge->drop_table('gathercontent_settings');

        return TRUE;
	}

	// ----------------------------------------------------------------

	/**
	 * Module Updater
	 *
	 * @return 	boolean 	TRUE
	 */
	public function update($current = '')
	{
		// If you have updates, drop 'em in here.
		return TRUE;
	}

}
/* End of file upd.gathercontent.php */
/* Location: /system/expressionengine/third_party/gathercontent/upd.gathercontent.php */
