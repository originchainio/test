<?php
return array_merge(array(
/*
|--------------------------------------------------------------------------
| Base Configuration
|--------------------------------------------------------------------------
*/
	'init'=>true,

	// Coin name
	'coin_name'=>'origin',

	//Local hostname
	'hostname'=>'http://192.168.1.40',

	//
	'public_api'=>true,

	// Hosts that are allowed to mine and public api allow host on this node
	'allow_host'=>array('127.0.0.1',
						'localhost',
						'::1',
						'192.168.1.40'
						),


	// The number of peers to send each new transaction to
	'transaction_propagation_peers'=>5,  //广播非本地交易的peer数量

	'block_propagation_peers'=>5,		//转播块的peer数量

	//
	'local_node'=>true,


/*
|--------------------------------------------------------------------------
| Log Configuration
|--------------------------------------------------------------------------
*/
	// Enable log output to the specified file
	'enable_logging'=>false,

	// The specified file to write to (this should not be publicly visible)
	'log_file'=>'origin.log',

	// Log verbosity (default 0, maximum 3)
	'log_verbosity'=>0,

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

	//	null  or D:\php-7.2.12\extras\ssl\openssl.cnf
	'openssl_cnf'=>NULL, 


),include(__DIR__.'/config_db.php'),include(__DIR__.'/config_peer.php')
,include(__DIR__.'/config_mempool.php')
,include(__DIR__.'/config_sanity.php')
);

?>