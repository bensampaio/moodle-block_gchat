<?php

/**
 * Global Chat block class implementation
 *
 * @package    block_gchat
 * @copyright  2012 Bruno Sampaio
 */

class block_gchat extends block_list {
	
    function init() {
        $this->title = get_string('pluginname', 'block_gchat');
    }

    function get_content() {
        global $CFG, $OUTPUT, $PAGE, $DB, $USER;

		require_once(__DIR__ . '/lib.php');

        if($this->content !== NULL) {
            return $this->content;
        }
		
		if (isloggedin() and !isguestuser()) {
			
			$this->title = get_string('pluginname', 'block_gchat');
			
			$this->content = new stdClass;
			$this->content->icons = '';
	        $this->content->items = array();
			$this->content->footer = '';
			
			$this->content->items[] = 
				'<img class="icon" src="'.$OUTPUT->pix_url('i/loading_small').'" />'.
				get_string('loading', 'block_gchat').'...';
			
			
			// Init javascript
			$data = array(
				block_gchat_get_server_name(), 
				block_gchat_get_server_port(), 
				$CFG->wwwroot,
				block_gchat_get_chat_container(),
				array('id' => $USER->id, 'name' => $USER->firstname.' '.$USER->lastname),
				array(
					'close' => array(
						'img' => (string) $OUTPUT->pix_url('close', 'block_gchat'),
						'visibility' => 1
					),
					'minimize' => array(
						'img' => (string) $OUTPUT->pix_url('minimize', 'block_gchat'),
						'visibility' => 1
					),
					'maximize' => array(
						'img' => (string) $OUTPUT->pix_url('maximize', 'block_gchat'),
						'visibility' => 0
					)
				)
			);
			$jsmodule = array(
				'name' => 'module',
				'fullpath' => '/blocks/gchat/module.js',
			    'requires' => array('base', 'io', 'node', 'json', 'selector'),
			    'strings' => array(
			        array('send-message', 'block_gchat'),
			        array('no-support', 'block_gchat'),
					array('no-users', 'block_gchat'),
					array('connection-lost', 'block_gchat')
			    )
			);
			$PAGE->requires->js_init_call('M.block_gchat.init', $data, false, $jsmodule);
        }
        return $this->content;
    }
}
