## Git for ubuntu16.04

apt-get update && apt-get install vim screen git -y

screen -S orcnode

mkdir /var/www

cd /var/www

git clone https://github.com/originchainio/test.git originnode

## Fast Installation Environment

cd /var/www/originnode

chmod +x install_ubuntu1604.sh

./install_ubuntu1604.sh

## restart OS
restart

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

//del originnode file:

rm /etc/nginx/sites-enabled/originnode

//edit nginx default file:

vim /etc/nginx/sites-enabled/default

//Modify server_name to your ip

//And turn on PHP Be similar to:

#######################################################

	server_name your_ip;

       root /var/www/originnode;

       index index.html index.htm index.php;

	   ...

	   ...

       location ~ \.php$ {    

              include snippets/fastcgi-php.conf;

			  fastcgi_pass 127.0.0.1:9000;

              # include snippets/fastcgi-php.conf;

              # fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

       }

#######################################################



