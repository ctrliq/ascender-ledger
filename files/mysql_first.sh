#!/bin/bash

# Check is MySQL is not setup
if [ ! -d "/var/lib/mysql/mysql" ]; then
  mysql_install_db --user=root --basedir=/usr --datadir=/var/lib/mysql
fi
# Launch MySQL in the background
/usr/bin/mysqld_safe --user=root &

# Check for the admin password. It will be in a different place depending on whether we are 
# running via docker-compose or kubernetes
if [ -f "/run/secrets/admin-password/admin-password" ]; then
  SHA=`cat /run/secrets/admin-password/admin-password | sha256sum | cut -d " " -f1`
fi
if [ -f "/run/secrets/admin-password" ]; then
  SHA=`cat /run/secrets/admin-password | sha256sum | cut -d " " -f1`
fi

# Check to see if the ledger DB exists
if [ ! -d "/var/lib/mysql/ledger" ]; then
  sleep 5
  /usr/bin/mysql -e "CREATE DATABASE IF NOT EXISTS ledger;"
  /usr/bin/mysql ledger < /root/ledger.sql
  /usr/bin/mysql ledger -e "INSERT INTO users (name, username, email, password, enabled, registered, code, super) VALUES ('admin', 'admin', 'admin', '$SHA', '1', '1', '', '1');";
fi

# Grab the Database Password
if [ -f "/run/secrets/db-ledger-password/db-ledger-password" ]; then
  LEDGER=`cat /run/secrets/db-ledger-password/db-ledger-password`
fi
if [ -f "/run/secrets/db-ledger-password" ]; then
  LEDGER=`cat /run/secrets/db-ledger-password`
fi

# Strip non-asci characters from the database password
LEDGER=${LEDGER//[^[:ascii:]]/}

# Just in case we changed the deployment passwords, update them again
/usr/bin/mysql -e "GRANT ALL PRIVILEGES ON ledger.* to 'ledger'@'%' IDENTIFIED BY '$LEDGER'"
/usr/bin/mysql ledger -e "UPDATE users SET password = '$SHA' WHERE username = 'admin'"

sleep infinity