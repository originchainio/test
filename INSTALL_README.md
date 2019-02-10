## Install for ubuntu16.04

download install_ubuntu1604.sh

## Fast Installation Environment
chmod +x install_ubuntu1604.sh
./install_ubuntu1604.sh

## Import data structure

//Enter the node directory
cd /var/www/originnode

//Executive order,replace YourMysqlPass and DatabaseName
mysql -uroot -pYourMysqlPass
CREATE DATABASE DatabaseName;
use DatabaseName;
source /var/www/originnode/sql.sql;
quit

//Delete SQL files
rm /var/www/originnode/sql.sql

## modify

//Modify server_name to your domain name
vim /etc/nginx/sites-enabled/originnode

//Restart nginx
service nginx reload

//Modify node configuration
Route: /var/www/originnode


## If you don't use domain names, use IP as a node
del originnode file:
rm /etc/nginx/sites-enabled/originnode
edit nginx default file:
vim /etc/nginx/sites-enabled/default

//Modify server_name to your ip
And turn on PHP Be similar to:
#######################################################
	server_name your_ip;
       root /var/www/originnode;
       index index.html index.htm index.php;
	   ...
	   ...
       location ~ \.php$ {
              fastcgi_pass 127.0.0.1:9000;
              include fastcgi.conf;
              # include snippets/fastcgi-php.conf;
              # fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
#######################################################


