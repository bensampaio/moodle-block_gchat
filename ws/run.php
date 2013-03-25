<?php
/**
 * Global Chat run server script.
 *
 * Must be run from Command Line, or the chat won't work.
 *
 * @package    block_gchat
 * @copyright  2012 Bruno Sampaio
 */

define('CLI_SCRIPT', true);

// Require Moodle and Block Libs
require_once('../../../config.php');
include_once('../lib.php');

// Require Ratchet Libs and Server Class
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/server.php';

use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

// Create Server
$server = IoServer::factory(
	new WsServer(new block_gchat_server()), block_gchat_get_server_port()
);

// Run Server
$server->run();
