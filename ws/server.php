<?php
/**
 * Global Chat server class implementation
 *
 * @package    block_gchat
 * @copyright  2012 Bruno Sampaio
 */

// Require HashMap
require __DIR__ . '/hash.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class block_gchat_server implements MessageComponentInterface {
	protected $clients, $connections;

	public function __construct() {
        $this->clients = array();
		$this->connections = new HashMap();
    }

    public function onOpen(ConnectionInterface $conn) {}

    public function onMessage(ConnectionInterface $conn, $data) {
	
		//Parse data received.
		$data = json_decode($data);
		echo $data->action.'<br/>';
		
		if(isset($data->action) && isset($data->params)) {
			switch($data->action) {
				
				//When a user becomes online
				case 'update_status' :
					$this->update_user_status($conn, $data->params);
					break;
					
				//When a user opens a chat window with a colleague
				case 'start_session' :
					$this->start_session($data->params);
					break;
					
				//When a user focus a chat window, markin all messages as seen
				case 'seen_session' :
					$this->seen_session($data->params);
					break;
				
				//When a user closes a chat window
				case 'close_session' :
					$this->close_session($data->params);
					break;
				
				//When a user posts a message in a chat session with other colleague
				case 'post_message' :
					$this->post_message($data->params);
					break;
			}
		}
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }

	public function onClose(ConnectionInterface $conn) {
		
		//When a user becomes offline
		$params = new stdClass();
		$params->user_id = $this->connections[$conn];
		$params->status = 'offline';
		$this->update_user_status($conn, $params);
	}


	/**
	 * Sends data to one user.
	 * @param int/object $user - the user id or the connection object.
	 * @param string $action - the action to perform on the client side.
	 * @param object $params - the data to send to the client.
	 */
	private function send_data($user, $action, $params) {
		$data = array('action' => $action, 'params' => $params);
		$json = json_encode($data);
		
		//If only the user id is provided
		if(is_int($user) || is_numeric($user)) {
			if(isset($this->clients[$user])) {
				foreach($this->clients[$user] as $conn) {
					$conn->send($json);
				}
			}
		}
		
		//If only the user connection is provided
		else if(is_object($user) && $user instanceof ConnectionInterface) {
			$conn = $user;
			$conn->send($json);
		}
	}
	
	
	/**
	 * Sends data to multiple users.
	 * @param array $users - array of user objects with at least their ids.
	 * @param string $action - the action to perform on the client side.
	 * @param object $params - the data to send to each client.
	 */
	private function send_data_multiple($users, $action, $params) {
		$data = array('action' => $action, 'params' => $params);
		$json = json_encode($data);
		
		//Iterate all users
		foreach($users as $user) {
			
			//For each user iterate over their connections
			if(isset($this->clients[$user->id])) {
				foreach($this->clients[$user->id] as $conn) {
					$conn->send($json);
				}
			}
		}
	}


	/**
	 * Updates a user status to online or offline.
	 * @param object $conn - the client connection.
	 * @param object $params - the data received from the client.
	 */
	private function update_user_status(ConnectionInterface $conn, $params) {
		if(isset($params->user_id) && isset($params->status)) {
			
			//Update Status
			block_gchat_update_user_status($params->user_id, $params->status);
			
			//Get online users for current user
			$users = block_gchat_get_online_users($params->user_id);
			
			//If Online
			if($params->status == 'online') {
				
				//Store Connection
				$this->connections[$conn] = $params->user_id;
				if(!isset($this->clients[$params->user_id])) {
					$this->clients[$params->user_id] = array();
				}
				array_push($this->clients[$params->user_id], $conn);
				
				//Get user open sessions
				$sessions = block_gchat_get_all_open_sessions($params->user_id);
				
				//Send user all current online users and open sessions
				$params = array('users' => $users['online_users'], 'sessions' => $sessions);
				$this->send_data($conn, 'get_users_and_sessions', $params);
				
				
				//Send other users the new user
				$params = array('users' => $users['current_user']);
				$this->send_data_multiple($users['online_users'], 'get_users_and_sessions', $params);
			}
			
			//If Offline
			else if($params->status == 'offline') {
				
				//Close Connection
				unset($this->connections[$conn]);
				$key = array_search($conn, $this->clients[$params->user_id]);
				$this->clients[$params->user_id][$key]->close();
				unset($this->clients[$params->user_id][$key]);
				
				//Tell other users that this user went offline
				$params = array('users' => $users['current_user']);
				$this->send_data_multiple($users['online_users'], 'remove_users', $params);
			}
		}
	}
	
	
	/**
	 * Starts a chat session with other user and sends back to the client all data needed.
	 * @param object $params - the data received from the client.
	 */
	private function start_session($params) {
		if(isset($params->from_id) && isset($params->to_id)) {
			$session_params = block_gchat_start_session($params->from_id, $params->to_id);
			$this->send_data($params->from_id, 'start_session', $session_params);
		}
	}
	
	
	/**
	 * Marks a session as seen. Equivalent to marking all messages as seen.
	 * @param object $params - the data received from the client.
	 */
	private function seen_session($params) {
		if(isset($params->session_id) && isset($params->user_id)) {
			block_gchat_set_session_seen_status($params->session_id, $params->user_id, 0);
		}
	}
	
	
	/**
	 * Closes a chat session with other user.
	 * @param object $params - the data received from the client.
	 */
	private function close_session($params) {
		if(isset($params->session_id) && isset($params->user_id)) {
			block_gchat_close_session($params->session_id, $params->user_id);
		}
	}
	
	
	/**
	 * Posts a message into a chat session and sends it to the other user in that session.
	 * @param object $params - the data received from the client.
	 */
	private function post_message($params) {
		if(isset($params->session) && isset($params->user) && isset($params->message)) {
			$userto = block_gchat_post_message($params->session->id, $params->user->id, $params->message->text);
			
			//If message posted successfully
			if($userto) {
				$this->send_data($userto, 'post_message', $params);
			}
		}
	}
}