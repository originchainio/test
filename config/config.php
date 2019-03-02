<?php
return array_merge(array(
/*
|--------------------------------------------------------------------------
| Base Configuration
|--------------------------------------------------------------------------
*/
	'init'=>true,

	// PHP PATH
	// If you configure PHP environment variables and allow them to be invoked globally directly by PHP CGI mode, you can leave them blank. If it is a quick installation script install, please keep it unchanged
	'php_path'=>'/usr/local/php/bin/',

	//Local hostname
	'hostname'=>'http://192.168.1.40',

	//
	'public_api'=>true,

	// Hosts that are allowed to mine and public api allow host on this node
	'allow_host'=>array('127.0.0.1',
						'localhost',
						'::1'
						),

	//Local node, passive receiving, not being mined and as master node, test configuration
	'local_node'=>false,


/*
|--------------------------------------------------------------------------
| Log Configuration
|--------------------------------------------------------------------------
*/
	// Enable log output to the specified file
	'enable_logging'=>true,

	// The specified file to write to (this should not be publicly visible)
	'log_file'=>'origin.log',

	'log_file_error'=>'origin_error.log',

	// Log verbosity (default 0, maximum 3)
	'log_verbosity'=>1,

/*
|--------------------------------------------------------------------------
| Masternode Configuration
|--------------------------------------------------------------------------
*/
	// Enable this node as a masternode
	'masternode'=>false,

	// The public key for the masternode
	'masternode_public_key'=>'',

/*
|--------------------------------------------------------------------------
| Openssl Configuration
|--------------------------------------------------------------------------
*/

	//	NULL  or D:\php\extras\ssl\openssl.cnf
	'openssl_cnf'=>NULL,


),include(__DIR__.'/config_db.php'),include(__DIR__.'/config_peer.php')
,include(__DIR__.'/config_mempool.php')
,include(__DIR__.'/config_sanity.php')
);

?>