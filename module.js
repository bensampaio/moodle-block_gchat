/**
 * Global Chat javascript functions
 *
 * @package    block_gchat
 * @copyright  2012 Bruno Sampaio
 */

M.block_gchat = {
	name : 'gchat',
	space_between_windows : 15,
	
	
	/**
	 * Initiates the connection with the server and creates the chat windows container.
	 * @param object Y - YUI 3 object.
	 * @param string server_name - the server name.
	 * @param string server_port - the server port.
	 * @param string server_url - the server url.
	 * @param string container - the id or class of the chat container.
	 * @param object user - the current user id and name.
	 * @param object imgs - the images urls needed.
	 */
	init : function(Y, server_name, server_port, server_url, chat_container, user, imgs) {
		
		// Set Global Values
		this.chat_container = chat_container;
		this.user = user;
		this.imgs = imgs;

		// Create Chat Windows Container
		var container = Y.one(this.chat_container);
		var chat = Y.Node.create('<div id="gchat"></div>');
		container.append(chat);

		// Check browser support for WebSockets
		if ("WebSocket" in window) {
			
			//Start Connection
			this.start_connection(Y, server_name, server_port, server_url, user);
		}
		else {
			// The user browser doesn't support WebSockets
			this.error(Y, 'no-support');
		}
	},
	
	
	/**
	 * Start connection with the server
	 * @param object Y - YUI 3 object.
	 * @param string server_name - the server name.
	 * @param string server_port - the server port.
	 * @param string server_url - the server url.
	 * @param object imgs - the images urls needed.
	 */
	start_connection : function(Y, server_name, server_port, server_url, user) {
		var self = this, conn = new WebSocket('ws://'+server_name+':'+server_port);

		conn.onopen = function() {

			// Update Status to Online
			self.update_status(Y, conn, user.id, 'online');
		};

		conn.onmessage = function(msg) {
			// Parse data received.
	        var data = Y.JSON.parse(msg.data);

			if(data.action && data.params) {
				switch(data.action) {

					// When the current user or other user goes online.
					case 'get_users_and_sessions' :
						self.get_users_online(Y, conn, data.params);
						self.get_sessions(Y, conn, server_url, data.params);
						break;

					// When a user goes offline.
					case 'remove_users' :
						self.remove_users_offline(Y, conn, data.params);
						break;

					// When the current user starts a session.	
					case 'start_session' :
						self.start_session(Y, conn, server_url, data.params, true);
						break;

					// When the current user or other user posts a message to a session.	
					case 'post_message' :
						self.post_message(Y, conn, server_url, data.params);
						break;
				}
			}

	    };

		conn.onerror = function() {
			conn.close();
		};

		conn.onclose = function() {
			setTimeout(function() {
				self.start_connection(Y, server_name, server_port, server_url, user);
			}, 5000);
			
			Y.all('.chat_window').remove(true);
			
			self.error(Y, 'connection-lost');
		};
	},
	
	
	/**
	 * Sends data from the client to the server.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param string action - the action to be performed on server side.
	 * @param object params - the data sent by the server.
	 */
	send_data : function(Y, conn, action, params) {
		var data = {};
		data.action = action;
		data.params = params;
		conn.send(Y.JSON.stringify(data));
	},
	
	
	/**
	 * Initiates the connection with the server and creates the chat windows container.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param int user_id - the user id.
	 * @param string status - the user status.
	 */
	update_status : function(Y, conn, user_id, status) {
		var params = {};
		params.user_id = user_id;
		params.status = status;

		this.send_data(Y, conn, 'update_status', params);
	},
	
	
	/**
	 * Receives all online users from server and add them to the block in alphabetic order.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param object params - the data sent by the server.
	 */
	get_users_online : function(Y, conn, params) {
		if(params.users) {
			var usersList = Y.one('.block_'+this.name).one('.unlist');

			var users = usersList.all('.user');

			//If users list is empty
			if(users.size() == 0) {

				//If server sent at least one user
				if(params.users.length > 0) {
					usersList.setContent('');
					var item_class = 0;

					for(var i = 0; i < params.users.length; i++) {

						//Create and insert user item on list
						var item = this.create_user(Y, conn, params.users[i], item_class);
						item_class? 0 : 1;

						usersList.append(item);
					}

					users = usersList.all('.user');
				}

				//If all users are offline
				else {
					this.empty(Y, usersList);
				}
			}
			else {

				//Iterate list of new users
				for(var i = 0; i < params.users.length; i++) {
					var item_id = '#gchat_user_'+params.users[i].id;

					//If user isn't already listed
					if(!usersList.one(item_id)) {
						var user_name = params.users[i].name;
						var j = 0, item_class, otherNode, compare;

						//Find user position on list by alphabetic order
						do {
							otherNode = users.item(j);
							item_class = parseInt(otherNode.ancestor().getAttribute('class').toString().charAt(1))? 0 : 1;
							var other_name = otherNode.one('.name').one('p').getContent();
							compare = this.strcmp(user_name.toLowerCase(), other_name.toLowerCase());
							j++;

						} while(compare < 0 && j < users.size());

						//Create and insert user item on list
						var item = this.create_user(Y, conn, params.users[i], item_class);
						compare < 0? otherNode.insert(item, 'before') : otherNode.insert(item, 'after');
					}
				}
			}
		}
	},
	
	
	/**
	 * Creates a user HTML to add to the block.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param object user - the user data.
	 * @param int item_class - the element class.
	 * @return Node item.
	 */
	create_user : function(Y, conn, user, item_class) {
		var self = this;
		var item = Y.Node.create('<li class="r'+item_class+'"></li>');
		var item_div = Y.Node.create('<div class="column c1 user"></div>');
		item_div.setAttribute('id', 'gchat_user_'+user.id);

		var table = Y.Node.create('<table></table>');
		var table_row = Y.Node.create('<tr></tr>');

		//User Picture
		var table_col_picture = Y.Node.create('<td class="picture"></td>');
		var user_picture = Y.Node.create(user.picture);
		table_col_picture.append(user_picture);
		table_row.append(table_col_picture);

		//User Name
		var table_col_name = Y.Node.create('<td class="name"></td>');
		var user_name = Y.Node.create('<p></p>');
		user_name.setContent(user.name);
		table_col_name.append(user_name);
		table_row.append(table_col_name);

		//User Status
		var table_col_status = Y.Node.create('<td class="status"></td>');
		var user_status = Y.Node.create('<span></span>');
		user_status.addClass(user.status);
		table_col_status.append(user_status);
		table_row.append(table_col_status);

		table.append(table_row);
		item_div.append(table);
		item.append(item_div);

		//Item click event to start a session with the user
		item_div.on('click', function(event) {
			var id = this.getAttribute('id').toString().split('_')[2];

			var params = {};
			params.from_id = self.user.id;
			params.to_id = id;
			
			self.send_data(Y, conn, 'start_session', params);
		});

		return item;
	},
	
	
	/**
	 * Receives all offline users from the server and removes them from the list of online users.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param object params - the data sent by the server.
	 */
	remove_users_offline : function(Y, conn, params) {
		if(params.users) {
			var usersList = Y.one('.block_'+this.name).one('.unlist');

			// Iterate over the list of users
			for(var i = 0; i < params.users.length; i++) {

				// Check if user is in the list.
				var user = usersList.one('#gchat_user_'+params.users[i].id);
				if(user) {
					user.remove(true); // Removes user.
				}
			}

			// If list is empty show empty message.
			if(usersList.all('.user').size() == 0) {
				this.empty(Y, usersList);
			}
		}
	},


	/**
	 * Receives all open sessions from the server and adds them to the chat container.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param object params - the data sent by the server.
	 */
	get_sessions : function(Y, conn, server_url, params) {
		if(params.sessions) {
			for(i in params.sessions) {
				this.start_session(Y, conn, server_url, params.sessions[i], false);
			}
		}
	},
	
	
	/**
	 * Starts a session with other user and creates the chat window.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param string server_url - the server url.
	 * @param object params - the data sent by the server.
	 * @param bool focus - to focus the chat window or not.
	 */
	start_session : function(Y, conn, server_url, params, focus) {
		if(params.session.id) {
			var self = this;
			
			var session_id = params.session.id;

			var chat = Y.one('#gchat');
			var chat_window_id = 'gchat_session_'+session_id;
			var chat_window = Y.one('#'+chat_window_id);

			if(!chat_window) {

				// Create Window
				if(session_id > 0) {
					var all_chat_windows = chat.all('.chat_window');
					var total_chat_windows = all_chat_windows.size();
					var chat_window_width = 0;

					if(total_chat_windows > 0) {
						chat_window_width = parseInt(all_chat_windows.getStyle('width'));
					}
					var right_space = ((total_chat_windows* (chat_window_width + this.space_between_windows)) + this.space_between_windows);

					chat_window = Y.Node.create('<div></div>');
					chat_window.setAttribute('id', chat_window_id);
					chat_window.addClass('chat_window gchat_userto_'+params.userto.id);
					chat_window.setStyle('right', right_space+'px');
					chat.append(chat_window);

					// Window Header
					var chat_window_header = Y.Node.create('<div></div>');
					chat_window_header.addClass('header');
					chat_window.append(chat_window_header);

					var chat_window_header_name = Y.Node.create('<a></a>');
					chat_window_header_name.setAttribute('href', server_url+'/user/profile.php?id='+params.userto.id);
					chat_window_header_name.setContent(params.userto.name);
					chat_window_header.append(chat_window_header_name);

					// Window Actions
					var chat_window_header_actions = Y.Node.create('<div></div>');
					chat_window_header_actions.addClass('actions');
					chat_window_header.append(chat_window_header_actions);

					for(i in this.imgs) {
						var chat_window_header_actions_action = Y.Node.create('<img></img>');
						chat_window_header_actions_action.addClass('icon');
						chat_window_header_actions_action.addClass(i);
						chat_window_header_actions_action.setAttribute('src', this.imgs[i].img);
						chat_window_header_actions.append(chat_window_header_actions_action);

						if(!this.imgs[i].visibility) {
							chat_window_header_actions_action.setStyle('display', 'none');
						}
					}


					// Window Messages
					var chat_window_messages = Y.Node.create('<div></div>');
					chat_window_messages.addClass('messages');
					chat_window.append(chat_window_messages);


					// Window Reply
					var chat_window_reply = Y.Node.create('<div></div>');
					chat_window_reply.addClass('reply');
					chat_window.append(chat_window_reply);

					var chat_window_reply_form = Y.Node.create('<form></form>');
					chat_window_reply.append(chat_window_reply_form);

					var chat_window_reply_text = Y.Node.create('<input type="text"></input>');
					chat_window_reply_text.addClass('chat_window_insert_text');
					chat_window_reply_form.append(chat_window_reply_text);

					var chat_window_reply_button = Y.Node.create('<input type="submit"></input>');
					chat_window_reply_button.addClass('chat_window_submit_text');
					chat_window_reply_button.setAttribute('value', M.util.get_string('send-message', 'block_gchat'));
					chat_window_reply_form.append(chat_window_reply_button);


					// Set Actions
					var minimize = chat_window_header_actions.one('.minimize');
					var maximize = chat_window_header_actions.one('.maximize');
					var close = chat_window_header_actions.one('.close');

					// Minimize Event
					minimize.on('click', function(event) {
						chat_window.addClass('collapsed');
						this.setStyle('display', 'none');
						maximize.setStyle('display', 'block');
					});

					// Maximize Event
					maximize.on('click', function(event) {
						chat_window.removeClass('collapsed');
						this.setStyle('display', 'none');
						minimize.setStyle('display', 'block');
					});

					// Close Event
					close.on('click', function(event) {

						var params = {};
						params.session_id = session_id;
						params.user_id = self.user.id;

						self.send_data(Y, conn, 'close_session', params);

						var current_window = chat_window;
						chat_window_width = parseInt(current_window.getStyle('width'));

						while( current_window = current_window.next('.chat_window') ) {
							var current_window_right = parseInt(current_window.getStyle('right'));
							current_window.setStyle('right', (current_window_right - chat_window_width - self.space_between_windows) + 'px');
						}

						chat_window.remove(true);
					});

					// Input Text Focus Event
					chat_window_reply_text.on('focus', function(event) {
						self.setAllSeen(Y, conn, chat_window, session_id);
					});
					chat_window_reply_text.on('textInput', function(event) {
						self.setAllSeen(Y, conn, chat_window, session_id);
					});

					// Submit Message
					chat_window_reply_form.on('submit', function(event) {
						event.preventDefault();

						var message = chat_window_reply_text.get('value');

						if(message.length > 0) {
							var params = {};
							params.session = {};
							params.session.id = session_id;
							params.session.unseen = 1;
							params.user = self.user;
							params.message = {};
							params.message.text = message;
							params.message.userid = self.user.id;

							self.post_message(Y, conn, server_url, params);
							chat_window_reply_text.set('value', '');
						}
					});
				}

				// Creates all messages
				if(params.messages) {
					var msg_params = {};
					msg_params.session = params.session;

					for(i in params.messages) {
						var msg = params.messages[i];
						if(msg.userid == params.userfrom.id) {
							msg_params.user = params.userfrom;
						}
						else {
							msg_params.user = params.userto;
						}
						msg_params.message = msg;

						this.create_message(Y, chat_window_messages, msg_params);
						this.messages_scroll_bottom(Y, chat_window_messages);
					}
				}
			}

			// If the user which opened the session isn't the same as current user
			if(params.userto.id != this.user.id && focus) {
				// Focus input text
				chat_window.one('.chat_window_insert_text').focus();
			}

			// If session as unseen messages
			else if(parseInt(params.session.unseen)) {
				chat_window.addClass('new_messages');
			}
		}
	},
	
	
	/**
	 * Posts a message to a session by sending the data to the server and by adding it to the chat window.
	 * If the message was received from other user, it first starts or updates the session 
	 * and then adds the message to the chat window.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param string server_url - the server url.
	 * @param object params - the data sent by the server.
	 */
	post_message : function(Y, conn, server_url, params) {
		if(params.session && params.message) {
			if(params.message.userid == this.user.id) {
				this.send_data(Y, conn, 'post_message', params);
			}
			else {
				var session_params = {};
				session_params.session = params.session;
				session_params.userto = params.user;
				this.start_session(Y, conn, server_url, session_params, false);
			}

			//Create Message
			var chat_window_messages = Y.one('#gchat_session_'+params.session.id).one('.messages');
			this.create_message(Y, chat_window_messages, params);

			//Scroll to Bottom
			this.messages_scroll_bottom(Y, chat_window_messages);
		}
	},


	/**
	 * Sets all messages as seen by notifying the server and by removing the class.
	 * @param object Y - YUI 3 object.
	 * @param object conn - the connection with the server.
	 * @param object chat_window - the chat window DOM.
	 * @param int session_id - the session id.
	 */
	setAllSeen : function(Y, conn, chat_window, session_id) {
		if(chat_window.hasClass('new_messages')) {
			
			//Set all messages seen
			var params = {
				session_id : session_id,
				user_id : this.user.id
			};
			this.send_data(Y, conn, 'seen_session', params);

			chat_window.removeClass('new_messages');
		}
	},


	/**
	 * Creates a message HTML to add to a chat window.
	 * @param object Y - YUI 3 object.
	 * @param Node chat_window_messages - the DOM element which contains the session messages.
	 * @param object params - the data sent by the server.
	 */
	create_message : function(Y, chat_window_messages, params) {

		//Get last user to post
		var chat_window_last_user = chat_window_messages.all('> .user').slice(-1).item(0);

		var last_user = null;
		if(chat_window_last_user) {
			last_user = chat_window_last_user.getAttribute('class').toString().split(' ')[1];
			last_user = last_user.split('_')[2];
		}

		if(last_user != params.user.id) {
			if(last_user) {
				//Create Users Separator
				var chat_user_separator = Y.Node.create('<div></div>');
				chat_user_separator.addClass('border');
				chat_window_messages.append(chat_user_separator);
			}

			//Create User
			var chat_user = Y.Node.create('<p></p>');
			chat_user.addClass('user gchat_user_'+params.user.id);
			chat_user.setContent(params.user.name+':');
			chat_window_messages.append(chat_user);
		}

		//Create Message
		var chat_message = Y.Node.create('<p></p>');
		chat_message.addClass('message');
		chat_message.setContent(params.message.text);
		chat_window_messages.append(chat_message);

		//Create Break Line
		var chat_br = Y.Node.create('<br/>');
		chat_window_messages.append(chat_br);
	},


	/**
	 * Scroll the messages DOM element to the bottom.
	 * @param object Y - YUI 3 object.
	 * @param Node container - the DOM element which contains the session messages.
	 */
	messages_scroll_bottom : function(Y, container) {
		container.set('scrollTop', container.get('scrollHeight')-parseInt(container.getStyle('height')));
	},


	/**
	 * Compares two strings.
	 * @param string str1 - first string.
	 * @param string str2 - second string.
	 * @return bool result - 0 if strings are equal, -1 if str1 < str 2, or 1 if str1 > str2.
	 */
	strcmp : function(str1, str2) {
		return ( ( str1 == str2 ) ? 0 : ( ( str1 > str2 ) ? 1 : -1 ) );
	},


	/**
	 * Creates the empty chat message.
	 * @param object Y - YUI 3 object.
	 * @param Node container - the DOM element which contains the online users.
	 */
	empty : function(Y, container) {
		var empty_item = Y.Node.create('<li class="empty"></li>');
		var item_div = Y.Node.create('<div class="column c1"></div>');
		item_div.setContent(M.util.get_string('no-users', 'block_gchat'));
		empty_item.append(item_div);
		container.setContent(empty_item);
	},


	/**
	 * Creates a chat error message.
	 * @param object Y - YUI 3 object.
	 * @param string message - the message code.
	 */
	error : function(Y, message) {
		var block_content = Y.one('.block_'+this.name).one('.unlist');
		var list_item = Y.Node.create('<li></li>');
		var item_div = Y.Node.create('<div class="notifyproblem"></div>');
		item_div.setContent(M.util.get_string(message, 'block_gchat'));
		list_item.append(item_div);
		block_content.setContent(list_item);
	}
	
};