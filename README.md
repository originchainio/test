## Install for Linux (Ubuntu 16.04)

*For more information, please refer to:INSTALL_README.md

	1.Clone this git
	2.Chmod +x install_ubuntu1604.sh
	3.Install this install_ubuntu1604.sh
	4.Restart OS
	5.Creat mysql database
	6.Import sql.sql
	7.Modify node config
	8.Modify or creat Nginx config
	9.Run once sync.php

### For Local passive node(No public network IP only passive receiving block)

*Modify /var/www/originnode/config/config.php

	'local_node'=>true,

*Setting Task Timing Execution: php sync.php

*passive node No mining, No masternode

## Activate the account

*Make a roll-in and roll-out automatic activation

## Masternode

*Register a new account

*Activate the account.Make a roll-in and roll-out automatic activation

*Modify /var/www/originnode/config/config.php

	'local_node'=>false,
	'masternode'=>true,
	'masternode_public_key'=>your publickey,

*Deposit in 10000 coins to your account

*activation(Command Line Running)

	cd /var/www/originnode
	php Uinterface.php registermasternode YourAccountPrivatekey

*return

	Array
	(
		[result] => ok
		[error] =>
	)

