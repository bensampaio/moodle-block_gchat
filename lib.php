<?php
/**
 * Lib functions for 'block_gchat' component.
 * @copyright 2012 Bruno Sampaio
 */

defined('MOODLE_INTERNAL') || die();

define('CHAT_USERS_TABLE', 'block_gchat_users');
define('CHAT_SESSIONS_TABLE', 'block_gchat_sessions');
define('CHAT_USER_SESSIONS_TABLE', 'block_gchat_user_sessions');
define('CHAT_MESSAGES_TABLE', 'block_gchat_messages');


/**
 * SERVER FUNCTIONS
 *-------------------------------------------------------------------------------------------------*/

/**
 * Gets a user data.
 * @param int $user_id - the user id.
 * @return object $data - user data.
 */
function block_gchat_get_user($user_id) {
	global $DB;
	
	$user_fields = 'id, firstname, lastname, email, picture, imagealt';
	return $DB->get_record('user', array('id' => $user_id), $user_fields, MUST_EXIST);
}


/**
 * Updates a user status to online or offline.
 * @param int $user_id - the current user unique identifier.
 * @param string $status - the new user status.
 */
function block_gchat_update_user_status($user_id, $status) {
	global $DB;
	
	$record = new stdClass();
	$record->status = $status;
	$record->userid = $user_id;
	
	$id = $DB->get_field(CHAT_USERS_TABLE, 'id', array('userid' => $user_id));
	if($id) {
		$record->id = $id;
		$DB->update_record(CHAT_USERS_TABLE, $record);
	}
	else {
		$DB->insert_record(CHAT_USERS_TABLE, $record);
	}
}


/**
 * Gets all online colleagues of a certain user.
 * Colleagues are all the users attending the same courses as the current user,
 * and they can be students or teachers.
 * @param int $user_id - the current user unique identifier.
 */
function block_gchat_get_online_users($user_id) {
	global $CFG, $DB, $OUTPUT;
	
	$data = array();
	$groupBy = ($CFG->dbtype == 'pgsql')? '' : "GROUP BY ue1.userid";
	
	$query = 
		"SELECT DISTINCT(ue1.userid), ue1.enrolid,
			{user}.firstname, {user}.lastname, {user}.email, {user}.picture, {user}.imagealt,
			chat_users.status
		FROM {user_enrolments} ue1
		INNER JOIN {user} ON (ue1.userid = {user}.id)
		INNER JOIN {".CHAT_USERS_TABLE."} chat_users on ({user}.id = chat_users.userid)
		WHERE EXISTS 
			(SELECT userid, enrolid 
				FROM {user_enrolments} ue2 
				WHERE ue1.enrolid = ue2.enrolid AND ue2.userid = ?
			) AND
			ue1.userid != ? AND
			chat_users.status = 'online'
		$groupBy	
		ORDER BY {user}.firstname, {user}.lastname";

	//Get current user info
	$current_user = block_gchat_get_user($user_id);
	$current_user->status = $DB->get_field(CHAT_USERS_TABLE, 'status', array('userid' => $user_id));
	$data['current_user'] = array($current_user);
	
	//Get all online users info
	$data['online_users'] = array();
	$online_users = $DB->get_records_sql($query, array($user_id, $user_id));
	
	//Prepare data to send.
	$current = $data['current_user'][0];
	do {
		
		//Set user id
		if(!isset($current->id)) {
			$current->id = $current->userid;
		}
		
		//Set needed user parameters
		$current->name = $current->firstname.' '.$current->lastname;
		$current->picture = $OUTPUT->user_picture($current, array('size' => 24, 'link' => false));
		
		//Remove all unnecessary fields.
		unset($current->firstname);
		unset($current->lastname);
		unset($current->email);
		unset($current->imagealt);
		unset($current->userid);
		unset($current->enrolid);
		
		if($current->id != $user_id) {
			array_push($data['online_users'], $current);
		}
		
	} while (list($id, $current) = each($online_users)); //Iterate over all user colleagues.
	
	return $data;
}


/**
 * Gets a session data.
 * @param int $session_id - the session id.
 * @param int $user_id - id of one of the users in the session.
 * @return array $data - session data.
 */
function block_gchat_get_session($session_id, $user_id) {
	global $DB;
	
	$query = 
		"SELECT * FROM {".CHAT_SESSIONS_TABLE."}
		WHERE id = :id AND (userfrom = :userfrom OR userto = :userto)";
			
	$params = array('id' => $session_id, 'userfrom' => $user_id, 'userto' => $user_id);
	
	return $DB->get_record_sql($query, $params);
}


/**
 * Starts a session with other user.
 * A session always belongs to two users and their order in the database is irrelevant.
 * This is, if a session with id1 and id2 already exists it is used for both users and never shall
 * be created a session with both ids in reverse order.
 * @param int $from_id - the current user unique identifier.
 * @param int $to_id - the other user unique identifier.
 * @return array $data - session data.
 */
function block_gchat_start_session($from_id, $to_id) {
	global $DB, $OUTPUT;
	
	//Get Users data
	$userfrom = block_gchat_get_user($from_id);
	$userto = block_gchat_get_user($to_id);
	
	$query = 
		"SELECT * FROM {".CHAT_SESSIONS_TABLE."}
		WHERE 
			(userfrom = ? AND userto = ?) OR
			(userto = ? AND userfrom = ?)";
			
	$params = array($from_id, $to_id, $from_id, $to_id);
	
	//Get session
	$session = $DB->get_record_sql($query, $params);
	
	if(!isset($session->id)) {
		
		//Create session if it doesn't exist yet.
		$session = new stdClass();
		$session->userfrom = $from_id;
		$session->userto = $to_id;
		$session->id = $DB->insert_record(CHAT_SESSIONS_TABLE, $session);
	}
	
	//Store User Session
	$user_session = block_gchat_open_session($session->id, $from_id);
	$session->unseen = $user_session->unseen;
	
	//Set users data to send
	$from = array('id' => $userfrom->id, 'name' => $userfrom->firstname.' '.$userfrom->lastname);
	$to = array('id' => $userto->id, 'name' => $userto->firstname.' '.$userto->lastname);
	
	return block_global_get_session_info($session, $from, $to);
}


/**
 * Gets all open sessions for the current user.
 * @param int $user_id - the current user unique identifier.
 * @return array $data - all sessions data.
 */
function block_gchat_get_all_open_sessions($user_id) {
	global $CFG, $DB;
	$userfromConcat = ($CFG->dbtype == 'pgsql')? "(userfrom.firstname|| ' '|| userfrom.lastname)" : "CONCAT(userfrom.firstname, ' ', userfrom.lastname)";
	$usertoConcat = ($CFG->dbtype == 'pgsql')? "(userto.firstname|| ' '|| userto.lastname)" : "CONCAT(userto.firstname, ' ', userto.lastname)";
	
	$query = 
		"SELECT {".CHAT_SESSIONS_TABLE."}.id, 
			{".CHAT_SESSIONS_TABLE."}.userfrom, {".CHAT_SESSIONS_TABLE."}.userto,
			$userfromConcat as userfrom_name,
			$usertoConcat as userto_name,
			{".CHAT_USER_SESSIONS_TABLE."}.isopen, {".CHAT_USER_SESSIONS_TABLE."}.unseen
			FROM {".CHAT_SESSIONS_TABLE."}
			INNER JOIN {".CHAT_USER_SESSIONS_TABLE."} 
				ON ({".CHAT_SESSIONS_TABLE."}.id = {".CHAT_USER_SESSIONS_TABLE."}.sessionid)
			INNER JOIN {user} as userfrom
				ON ({".CHAT_SESSIONS_TABLE."}.userfrom = userfrom.id)
			INNER JOIN {user} as userto
				ON ({".CHAT_SESSIONS_TABLE."}.userto = userto.id)
			WHERE {".CHAT_USER_SESSIONS_TABLE."}.userid = ? AND {".CHAT_USER_SESSIONS_TABLE."}.isopen = 1 ";
	
	$params = array($user_id);
	
	$data = array();
	$sessions = $DB->get_records_sql($query, $params);
	foreach($sessions as $session) {
		$from = array();
		$to = array();
		
		if($session->userfrom == $user_id) {
			$from['id'] = $session->userfrom;
			$from['name'] = $session->userfrom_name;
			$to['id'] = $session->userto;
			$to['name'] = $session->userto_name;
		}
		else {
			$from['id'] = $session->userto;
			$from['name'] = $session->userto_name;
			$to['id'] = $session->userfrom;
			$to['name'] = $session->userfrom_name;
		}
		
		array_push($data, block_global_get_session_info($session, $from, $to));
	}
	
	return $data;
}


/**
 * Generates a session info including its id and seen status,
 * the both users data and the last messages shared between them.
 * @param int $session_id - the session id.
 * @param object $user_from - the first user data.
 * @param object $user_to - the second user data.
 * @return array $data - session data.
 */
function block_global_get_session_info($session, $user_from, $user_to) {
	$data = array(
		'session' => array('id' => $session->id, 'unseen' => $session->unseen),
		'userfrom' => $user_from,
		'userto' => $user_to,
		'messages' => block_gchat_load_messages($session->id)
	);
	return $data;
}


/**
 * Sets a user session as opened.
 * @param int $session_id - the session id.
 * @param int $user_id - the current user unique identifier.
 * @return array $record - user session data.
 */
function block_gchat_open_session($session_id, $user_id) {
	global $DB;
	
	$conditions = array('sessionid' => $session_id, 'userid' => $user_id);
	$record = $DB->get_record(CHAT_USER_SESSIONS_TABLE, $conditions);
	
	if($record) {
		$DB->set_field(CHAT_USER_SESSIONS_TABLE, 'isopen', 1, $conditions);
		$record->isopen = 1;
	}
	else {
		$record = new stdClass();
		$record->isopen = 1;
		$record->unseen = 0;
		$record->sessionid = $session_id;
		$record->userid = $user_id;
		$record->id = $DB->insert_record(CHAT_USER_SESSIONS_TABLE, $record);
	}
	return $record;
}


/**
 * Sets a user session as seen or unseen.
 * @param int $session_id - the session id.
 * @param int $user_id - the current user unique identifier.
 * @param int $unseen - the new session status.
 */
function block_gchat_set_session_seen_status($session_id, $user_id, $unseen) {
	global $DB;
	
	$DB->set_field(
		CHAT_USER_SESSIONS_TABLE, 
		'unseen', $unseen, 
		array('sessionid' => $session_id, 'userid' => $user_id)
	);
}


/**
 * Sets a user session as closed.
 * @param int $session_id - the session id.
 * @param int $user_id - the current user unique identifier.
 */
function block_gchat_close_session($session_id, $user_id) {
	global $DB;
	
	$DB->set_field(
		CHAT_USER_SESSIONS_TABLE, 
		'isopen', 0, 
		array('sessionid' => $session_id, 'userid' => $user_id)
	);
}


/**
 * Loads last 10 messages of a session.
 * @param int $session_id - the session id.
 * @return array $data - messages data.
 */
function block_gchat_load_messages($session_id) {
	global $DB;
	
	// Get last messages
	$query = "SELECT id, text, userid FROM {".CHAT_MESSAGES_TABLE."} WHERE sessionid = ? ORDER BY timecreated DESC, id DESC LIMIT 10";
	
	return array_reverse($DB->get_records_sql($query, array($session_id)));
}


/**
 * Posts a message sent by a user into a session.
 * @param int $session_id - the session id.
 * @param int $user_id - the current user unique identifier.
 * @param string $message_text - the message text.
 * @return int $userto_id - the other user id to send back the message, 
 * or 0 if the message is empty, or if session doesn't exists.
 */
function block_gchat_post_message($session_id, $user_id, $message_text) {
	global $DB;
	
	if(strlen($message_text) > 0) {
		
		//Get session
		$session = block_gchat_get_session($session_id, $user_id);
		
		if($session) {
			//Create Message
			$record = new stdClass();
			$record->text = $message_text;
			$record->timecreated = $_SERVER['REQUEST_TIME'];
			$record->sessionid = $session_id;
			$record->userid = $user_id;

			//Store message
			$DB->insert_record(CHAT_MESSAGES_TABLE, $record);
			
			//Open session to other user
			$userto_id = $session->userfrom == $user_id? $session->userto : $session->userfrom;
			$user_session = block_gchat_open_session($session_id, $userto_id);
			
			//Set unseen messages for other user
			block_gchat_set_session_seen_status($session_id, $userto_id, 1);
			
			return $userto_id;
		}
		else {
			return false;
		}
	}
	else {
		return false;
	}
}


/**
 * SETTINGS FUNCTIONS
 *-------------------------------------------------------------------------------------------------*/

/**
 * Get server name.
 * @return string
 */
function block_gchat_get_server_name() {
	global $CFG;

	if (!empty($CFG->block_gchat_server_name)) {
		return $CFG->block_gchat_server_name;
	} else {
	    return $_SERVER['SERVER_NAME'];
	}
}


/**
 * Get server port.
 * @return string
 */
function block_gchat_get_server_port() {
	global $CFG;

	if (!empty($CFG->block_gchat_server_port)) {
		return $CFG->block_gchat_server_port;
	} else {
	    return 8000;
	}
}


/**
 * Get chat container.
 * @return string
 */
function block_gchat_get_chat_container() {
	global $CFG;

	if (!empty($CFG->block_gchat_container)) {
		return $CFG->block_gchat_container;
	} else {
	    return 'body';
	}
}


/**
 * Process CSS files based on block settings.
 */
function block_gchat_process_css($css) {
	global $CFG;
	
	//Set Chat Window Header Color
    if (!empty($CFG->block_gchat_header_color)) {
        $header_color = $CFG->block_gchat_header_color;
    } else {
        $header_color = null;
    }
    $css = block_gchat_set_header_color($css, $header_color);

	//Set Chat Window New Messages Header Color
    if (!empty($CFG->block_gchat_header_new_messages_color)) {
        $header_new_color = $CFG->block_gchat_header_new_messages_color;
    } else {
        $header_new_color = null;
    }
    $css = block_gchat_set_new_header_color($css, $header_new_color);

	//Set Chat Window Header Text Color
    if (!empty($CFG->block_gchat_header_text_color)) {
        $header_text_color = $CFG->block_gchat_header_text_color;
    } else {
        $header_text_color = null;
    }
    $css = block_gchat_set_header_text_color($css, $header_text_color);

	//Set Chat Window Border Color
    if (!empty($CFG->block_gchat_window_border_color)) {
        $header_text_color = $CFG->block_gchat_window_border_color;
    } else {
        $header_border_color = null;
    }
    $css = block_gchat_set_border_color($css, $header_border_color);

	return $css;
}


/**
 * Sets window header color
 *
 * @param string $css
 * @param mixed $color
 * @return string
 */
function block_gchat_set_header_color($css, $color) {
    $tag = '[[setting:chat_window_header_color]]';
    $replacement = $color;
    if (is_null($replacement)) {
        $replacement = '#2F922C';
    }
    $css = str_replace($tag, $replacement, $css);
    return $css;
}


/**
 * Sets window header new messages color
 *
 * @param string $css
 * @param mixed $color
 * @return string
 */
function block_gchat_set_new_header_color($css, $color) {
    $tag = '[[setting:chat_window_new_header_color]]';
    $replacement = $color;
    if (is_null($replacement)) {
        $replacement = '#5ECC76';
    }
    $css = str_replace($tag, $replacement, $css);
    return $css;
}


/**
 * Sets window header text color
 *
 * @param string $css
 * @param mixed $color
 * @return string
 */
function block_gchat_set_header_text_color($css, $color) {
    $tag = '[[setting:chat_window_text_color]]';
    $replacement = $color;
    if (is_null($replacement)) {
        $replacement = '#B0DDBA';
    }
    $css = str_replace($tag, $replacement, $css);
    return $css;
}


/**
 * Sets window border color
 *
 * @param string $css
 * @param mixed $color
 * @return string
 */
function block_gchat_set_border_color($css, $color) {
    $tag = '[[setting:chat_window_border_color]]';
    $replacement = $color;
    if (is_null($replacement)) {
        $replacement = '#888';
    }
    $css = str_replace($tag, $replacement, $css);
    return $css;
}
