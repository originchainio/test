<?php
return array(
/*
|--------------------------------------------------------------------------
| Peer Configuration
|--------------------------------------------------------------------------
*/
	// Maximum number of connected peers
	'max_peer'=>30,

	// Database max peer number
	'db_max_peers'=>300,

	// How many new peers to check from each peer
	'max_test_peers'=>5,	//s随机检测peer 一次最大值

	// The initial peers to sync from in sanity
	'initial_peer_list'=>array(
							'http://192.168.1.36',
							'http://192.168.1.37',
							'http://192.168.1.38',
							),

	// Bad peer is not add database
	'bad_peers'=>array(
							"127.",
							"localhost",
							"10.",
							),
);
?>