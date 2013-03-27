GLOBAL CHAT - MOODLE BLOCK

DESCRIPTION
-----------

This plugin introduces a chat for Moodle very similar to Google Chat or Facebook Chat. It uses a block to list all online users that are part of all courses a user is subscribed to, and opens a chat window on the bottom of the page when a online user is clicked.

The advantages of this chat are:
- Users don't need to open a separate window to use this chat, while Moodle activity chat needs them to do so;
- A user can establish a real time conversation with other online users subscribed to the courses this user belongs to;
- Users can change between pages and their open conversations will always be there;

If you are a student use it to collaborate with your colleagues, or to ask questions to your teachers.
If you are a teacher use it to communicate with your students individually.

We believe Moodle needs a different chat concept, that's why we decided to create this plugin. It still needs some improvement, but first we would like to know what people think about this idea :)


CONTENTS ORGANISATION
---------------------

	FOLDERS:
	- db: contains the "install.xml" file with database structure and the "access.php" file needed for Moodle 2.4;
	- lang: contains languages files for English and Portuguese (Portugal);
	- pix: contains image files used on the chat window;
	- ws: contains the files needed to run the chat server:
		- scripts: contains example scripts to run on server startup and to restart the server if anything goes wrong (more about this below on next section);
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
	

HOW TO USE
----------

To use this plugin you must run a CLI script which will be responsible for managing the chat sessions and the communication between users. This script initialises a server that uses the WebSocket protocol to establish a connection between users, avoiding the need for ajax requests. With this approach the client browser doesn't need to constantly ask the server if there are updates because the server will send new data when other user starts a conversations or sends a message.

The server script is located in "blocks/gchat/ws" and it is the "run.php" file. On the following line the path to this file is referred to by <path_to_run_script>.


TRY IT

If you want to try the server put the following commands on a command line: 

If PHP is on other location then usual, run the following:
export PATH=<path_to_php>:$PATH 
(inside <path_to_php> there must be a file with name 'php')

cd <path_to_run_script>
php <run_script_name>.php


DEPLOY IT

If you like the concept and you want to install this chat on your Moodle site I recommend you read this first: http://socketo.me/docs/deploy. You don't need to do everything that's explained on that page but that depends on the kind of security you need for you Moodle site.

On that page you will find a reference to Supervisor, which I recommend you use to run the chat server script, so in case the script crashes it is immediately restarted. Besides that, you should create a script to run when your machine starts up that would be responsible for starting up supervisor. Inside "ws/scripts" there are two scripts that you can use for those purposes:

	- The "supervisord.conf" must be copied to /usr/local/etc/supervisord.conf. Before using it you must open this file, then locate <$CFG->dirroot> and change this by the absolute path to your Moodle root folder;
	- The "moodle_gchat" file must be copied to the /etc/init.d folder on a linux system, this way it will be executed when your machine starts. To execute this script you must provide one of the following commands:
		- start: this starts supervisor;
		- restart: this kills the server script and then supervisor will restart it;
		- stop: this stops supervisor and then the server script.

So after copying the files above to their respective folders and after making the necessary changes, you can start the server with the following command: "cd /etc/init.d && sudo ./moodle_chat start".

In case you don't understand something just contact me.