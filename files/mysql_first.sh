#!/bin/bash

# Check is MySQL is not setup
if [ ! -d "/var/lib/mysql/mysql" ]; then
  mysql_install_db --user=root --basedir=/usr --datadir=/var/lib/mysql
fi
# Launch MySQL in the background
/usr/bin/mysqld_safe --user=root &

# Check for the admin password. It will be in a different place depending on whether we are
# running via docker-compose or kubernetes
ADMIN_PASSWORD_FILE=""
if [ -f "/run/secrets/admin-password/admin-password" ]; then
  ADMIN_PASSWORD_FILE="/run/secrets/admin-password/admin-password"
fi
if [ -f "/run/secrets/admin-password" ]; then
  ADMIN_PASSWORD_FILE="/run/secrets/admin-password"
fi

# The admin password is stored as a salted bcrypt hash of its SHA-256, which Ledger accepts and
# converts to its own format at the first login (the key Ledger hashes passwords with is not
# available in this container).  The secret is never written to the database in a form that
# can be looked up.  Prints nothing when there is no secret.
#   bcrypt_of_secret              hash the secret with a new salt
#   bcrypt_of_secret <hash>       print "same" when the secret matches that hash
bcrypt_of_secret() {
  [ -n "$ADMIN_PASSWORD_FILE" ] || return 0
  python3 - "$ADMIN_PASSWORD_FILE" "$1" <<'PY'
import crypt, hashlib, sys
# Ledger trims what is typed at login, so trim the secret the same way
digest = hashlib.sha256(open(sys.argv[1]).read().strip().encode()).hexdigest()
if len(sys.argv) > 2 and sys.argv[2]:
    print("same" if crypt.crypt(digest, sys.argv[2]) == sys.argv[2] else "")
else:
    print(crypt.crypt(digest, crypt.mksalt(crypt.METHOD_BLOWFISH)))
PY
}

# Wait for the server to accept connections
for i in $(seq 1 30); do
  /usr/bin/mysqladmin ping >/dev/null 2>&1 && break
  sleep 1
done

# Check to see if the ledger DB exists
if [ ! -d "/var/lib/mysql/ledger" ]; then
  /usr/bin/mysql -e "CREATE DATABASE IF NOT EXISTS ledger;"
  /usr/bin/mysql ledger < /root/ledger.sql
  /usr/bin/mysql ledger -e "INSERT INTO users (name, username, email, password, enabled, registered, code, super) VALUES ('admin', 'admin', 'admin', '', '1', '1', '', '1');";
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

# Changing the admin-password secret sets the admin password.  Only a changed secret does: a
# password the admin has since set in Ledger is kept across restarts.  The setting holds a
# salted hash of the secret last applied, to tell whether it has changed.
if [ -n "$ADMIN_PASSWORD_FILE" ]; then
  APPLIED=`/usr/bin/mysql -N ledger -e "SELECT value FROM settings WHERE setting = 'admin_password_secret'" 2>/dev/null`
  if [ "`bcrypt_of_secret "$APPLIED"`" != "same" ]; then
    HASH=`bcrypt_of_secret`
    MARKER=`bcrypt_of_secret`
    if [ -n "$HASH" ] && [ -n "$MARKER" ]; then
      /usr/bin/mysql ledger -e "UPDATE users SET password = '$HASH' WHERE username = 'admin'"
      /usr/bin/mysql ledger -e "INSERT INTO settings (setting, value) VALUES ('admin_password_secret', '$MARKER') ON DUPLICATE KEY UPDATE value = VALUES(value)"
    fi
  fi
fi

sleep infinity
