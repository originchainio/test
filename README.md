## Info

Name:Originchain

Symbol:Orc

Blocktime: ~ 4 minutes

Fee: 0.5%

Theoretical maximum:110,800,001

## Need
	
	php >= 7.2

	argon2

	openssl

	gmp

	PDO

	bcmath

	mysql

	nginx or apache

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
	'masternode_public_key'=>'your publickey',

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

## Default directory

*node: /var/www/originnode

*php: /usr/local/php

*php-ini: /etc/php/cgi/php.ini

*Nginx: /etc/nginx/

*Nginx config: /etc/nginx/sites-enabled/

## Directory permission settings

*node: 0755

*cache and log 777

	cd /var/www/
	chown -R originnode:website /var/www/originnode
	chmod -R 0755 /var/www/originnode
	cd /var/www/originnode
	chmod 777 log
	chmod 777 cache

*"install_ubuntu1604.sh" Permissions have been processed
