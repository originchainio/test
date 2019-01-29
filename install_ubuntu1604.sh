#bin/bash
apt-get update
# Installation package
apt-get install build-essential bison re2c pkg-config -y
apt-get install libxml2-dev libbz2-dev libcurl4-openssl-dev -y
apt-get install libjpeg-dev libpng12-dev libfreetype6-dev libgmp-dev libreadline6-dev libxslt1-dev libzip-dev -y
apt-get install openssl -y
apt-get install libssl-dev -y
apt-get install make curl -y
apt-get install libcurl4-gnutls-dev -y
apt-get install libmcrypt-dev -y
apt-get install gcc autoconf -y
# install argon2
cd ~
git clone https://git.launchpad.net/ubuntu/+source/argon2
cd argon2
make clean
make && make install

# install php
# http://php.net/get/php-7.2.14.tar.gz/from/a/mirror
cd ~
wget http://php.net/get/php-7.2.14.tar.gz/from/this/mirror -O php-7.2.14.tar.gz
tar -zxvf php-7.2.14.tar.gz
cd php-7.2.14

./configure --prefix=/usr/local/php \
--with-password-argon2 --with-config-file-path=/etc/php/cgi --enable-fpm --enable-inline-optimization --disable-debug --disable-rpath \
--enable-shared --with-libxml-dir --with-xmlrpc --with-mhash --with-pcre-regex --with-zlib --with-libzip --enable-bcmath \
--with-iconv --with-bz2 --with-openssl --enable-calendar --with-curl --with-cdb --enable-dom --enable-exif \
--enable-fileinfo --enable-filter --with-pcre-dir --with-openssl-dir --with-gettext --with-gmp --with-mhash \
--enable-json --enable-mbstring --enable-mbregex --enable-mbregex-backtrack --with-libmbfl --with-onig \
--enable-pdo --with-mysqli=mysqlnd --with-pdo-mysql=mysqlnd --with-pdo-sqlite --with-readline --enable-session \
--enable-shmop --enable-simplexml --enable-sockets  --enable-sysvmsg --enable-sysvsem --enable-sysvshm \
--enable-wddx --with-libxml-dir --with-xsl --enable-zip --enable-mysqlnd-compression-support \
--with-pear --enable-opcache
make clean
make && make install

# environment variable  /etc/profile
echo 'PATH=$PATH:/usr/local/php/bin
export PATH' >> /etc/profile
source /etc/profile

mkdir /etc/php/
mkdir /etc/php/cgi/
# mkdir /etc/php/cli/
cp ~/php-7.2.14/php.ini-production  /etc/php/cgi/php.ini
# cp ~/php-7.2.14/php.ini-production  /etc/php/cli/php.ini
# PHP-FPM
cp ~/php-7.2.14/sapi/fpm/init.d.php-fpm /usr/local/bin/php-fpm
chmod +x /usr/local/bin/php-fpm
cp /usr/local/php/etc/php-fpm.conf.default /usr/local/php/etc/php-fpm.conf
#add user group
groupadd website
useradd -s /sbin/nologin -g website originnode
# install node
mkdir /var/www
cd /var/www
git clone https://github.com/originchainio/test.git originnode
chown -R originnode:website /var/www/originnode
chmod -R 0755 /var/www/originnode
cd /var/www/originnode
mkdir tmp
chmod 777 tmp

# fpm for node
cp /usr/local/php/etc/php-fpm.d/www.conf.default /usr/local/php/etc/php-fpm.d/originnode.conf
cd /usr/local/php/etc/php-fpm.d/
find -name 'originnode.conf' | xargs perl -pi -e 's|user = nobody|;user = nobody|g'
find -name 'originnode.conf' | xargs perl -pi -e 's|group = nobody|;group = nobody|g'
find -name 'originnode.conf' | xargs perl -pi -e 's|;user = nobody|user = originnode|g'
find -name 'originnode.conf' | xargs perl -pi -e 's|;group = nobody|group = website|g'
php-fpm start


# start PHP-FPM  /etc/systemd/system/php-fpm.service
echo "[Unit]
Description=The PHP FastCGI Process Manager
After=syslog.target network.target
[Service]
Type=forking
ExecStart=/usr/local/bin/php-fpm start
ExecReload=/usr/local/bin/php-fpm reload
ExecStop=/usr/local/bin/php-fpm stop
[Install]
WantedBy=multi-user.target" >> /etc/systemd/system/php-fpm.service
systemctl enable php-fpm.service

# install mysql
apt-get install mysql-server -y
apt-get install mysql-client -y
apt-get install libmysqlclient-dev -y
# install Nginx
apt-get install nginx -y
cd /etc/nginx/
# edit nginx
find -name 'nginx.conf' | xargs perl -pi -e 's|user www-data;|user originnode website;|g'
# host
cd /etc/nginx/sites-enabled/
echo "server {
       listen 80;
       listen [::]:80;
       server_name example.com;
	   
       root /var/www/originnode;
       index index.html index.htm index.php;
       location / {
              try_files $uri $uri/ =404;
       }
       location ~ \.php$ {
              fastcgi_pass 127.0.0.1:9000;
              include fastcgi.conf;
              # include snippets/fastcgi-php.conf;
              # fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
}" >> originnode
# restart nginx
service nginx restart
service nginx reload