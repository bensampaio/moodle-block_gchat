GLOBAL CHAT - MOODLE BLOCK

DESCRIPTION

To use this plugin you must run a server on the command line. This server uses the WebSocket protocol to establish a connection between users, avoiding the need for ajax requests.


CONTENTS ORGANISATION

	FOLDERS:
	- db: contains the "install.xml" file with database structure and the "access.php" file needed for Moodle 2.4;
	- lang: contains languages files for English and Portuguese (Portugal);
	- pix: contains image files used on the chat window;
	- ws: contains the files needed to run the chat server:
		- vendor: contains the Ratchet PHP library which provides a WebSocket protocol implementation; 
		- hash.php: implementation of a HashMap in PHP;
		- server.php: the chat server logic;
		- run.php: the CLI script to start the server;
	
	FILES:
	- block_gchat.php: defines the data needed to initialise the block;
	- lib.php: defines all functions needed to get chat sessions and messages data;
	- module.js: defines all javascript functions needed to communicate with the server and generates the chat windows HTML;
	- settings.php: defines all the block settings which allow the user to specify the chat server URL and port, and the chat window HTML container;
	- styles.css: the chat block and chat windows CSS styles;
	- version.php: block version information;
	

HOW TO RUN

The server script is located in blocks/gchat/ws/run.php.

To run it you must use the following commands: 

If PHP is on other location then usual, run the following:
export PATH=<path_to_php>:$PATH 
(inside <path_to_php> there must be a file with name 'php')

cd <path_to_run_script>
php <run_script_name>.php