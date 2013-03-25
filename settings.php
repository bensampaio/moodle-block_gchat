<?php

/**
 * Settings for the 'block_gchat' component.
 * @copyright 2012 Bruno Sampaio
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
	
	/*
	 * Properties
	 */
	
	// Server Name
	$name = 'block_gchat_server_name';
	$title = get_string('server_name', 'block_gchat');
	$description = get_string('server_name_desc', 'block_gchat');
	$default = $_SERVER['SERVER_NAME'];
	$settings->add(new admin_setting_configtext($name, $title, $description, $default, PARAM_CLEAN));

	// Server Port
	$name = 'block_gchat_server_port';
	$title = get_string('server_port', 'block_gchat');
	$description = get_string('server_port_desc', 'block_gchat');
	$default = 8000;
	$settings->add(new admin_setting_configtext($name, $title, $description, $default, PARAM_INT));
	
	// Chat Container
	$name = 'block_gchat_container';
	$title = get_string('chat_container', 'block_gchat');
	$description = get_string('chat_container_desc', 'block_gchat');
	$default = 'Body';
	$choices = array('body' => 'Body', '#page' => 'Page');
	$settings->add(new admin_setting_configselect($name, $title, $description, $default, $choices));
	
	
	/*
	 * Colors
	 */
	/*
	// Chat Window Header Color
	$name = 'block_gchat_header_color';
	$title = get_string('header_color', 'block_gchat');
	$description = get_string('header_color_desc', 'block_gchat');
	$default = '#2F922C';
	$previewconfig = array('selector'=>'#gchat .chat_window .header', 'style'=>'background-color');
	$settings->add(new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig));
	
	// Chat Window Header New Messages Color
	$name = 'block_gchat_header_new_messages_color';
	$title = get_string('header_new_messages_color', 'block_gchat');
	$description = get_string('header_new_messages_color_desc', 'block_gchat');
	$default = '#5ECC76';
	$previewconfig = array('selector'=>'#gchat .chat_window .header', 'style'=>'background-color');
	$settings->add(new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig));
	
	// Chat Window Header Text Color
	$name = 'block_gchat_header_text_color';
	$title = get_string('header_text_color', 'block_gchat');
	$description = get_string('header_text_color_desc', 'block_gchat');
	$default = '#B0DDBA';
	$previewconfig = array('selector'=>'#gchat .chat_window .header', 'style'=>'color');
	$settings->add(new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig));
	
	// Chat Window Border Color
	$name = 'block_gchat_window_border_color';
	$title = get_string('window_border_color', 'block_gchat');
	$description = get_string('window_border_color_desc', 'block_gchat');
	$default = '#888';
	$previewconfig = array(
			'selector'=>'.chat_window, .chat_window .header, .chat_window .messages, .chat_window .reply', 
			'style'=>'border-color'
	);
	$settings->add(new admin_setting_configcolourpicker($name, $title, $description, $default, $previewconfig));
	*/
}