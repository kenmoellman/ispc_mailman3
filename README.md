# ispc_mailman3

Mailman3 module for ISPConfig3. Provides mailing list management within ISPConfig's admin panel, backed by a Mailman3 venv installation with Postorius and HyperKitty.

## Overview

This module adds a "Mailman3 Lists" section under Email in ISPConfig's navigation. Lists are managed through Mailman3's REST API — ISPConfig serves as a management frontend while Mailman3 handles all mail processing.

**Architecture:**
- Mailman3 core handles LMTP delivery, list processing, and the REST API
- Postorius provides web-based list management (member management, settings)
- HyperKitty provides web-based message archives
- Gunicorn serves Postorius/HyperKitty with whitenoise for static files
- ISPConfig extension syncs list metadata and provides a management UI
- Postfix routes list mail via `transport_maps` to Mailman3's LMTP

**Tested with:**
- Ubuntu 24.04 LTS (Noble)
- ISPConfig 3.2.x
- Python 3.12
- Mailman 3.3.10, Postorius 1.3.13, HyperKitty 1.3.12
- Django 4.2.29, Gunicorn 25.3.0
- MariaDB 11.4 / MySQL 8.0
- Postfix 3.6+

## Prerequisites

- ISPConfig 3 installed and working
- Postfix configured as the MTA
- MariaDB or MySQL
- Python 3.10+ with venv support (`python3-venv`)
- Build tools: `python3-dev`, `libmariadb-dev` (or `libmysqlclient-dev`), `pkg-config`, `libmilter-dev`
- `sassc` (for HyperKitty SCSS compilation)
- A `list` system user/group (usually pre-exists from Mailman2, or create with `adduser --system --group list`)
- A reverse proxy (nginx) in front of the server (optional but assumed in this guide)

## Installation

### Step 1: Create MySQL Databases

```sql
CREATE DATABASE mailman3 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE mailman3web CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'mailman3'@'localhost' IDENTIFIED BY 'YOUR_MM3_DB_PASSWORD';
CREATE USER 'mailman3web'@'localhost' IDENTIFIED BY 'YOUR_WEB_DB_PASSWORD';
GRANT ALL PRIVILEGES ON mailman3.* TO 'mailman3'@'localhost';
GRANT ALL PRIVILEGES ON mailman3web.* TO 'mailman3web'@'localhost';
FLUSH PRIVILEGES;
```

### Step 2: Create the Python Venv

```bash
mkdir -p /opt/mailman3
python3 -m venv /opt/mailman3/venv
/opt/mailman3/venv/bin/pip install --upgrade pip setuptools wheel
/opt/mailman3/venv/bin/pip install \
  mailman \
  mailman-web \
  mailman-hyperkitty \
  pymysql \
  mysqlclient \
  gunicorn \
  whitenoise
```

### Step 3: Create Directories

```bash
mkdir -p /var/lib/mailman3/{archives,cache,data,ext,lists,locks,messages,queue,templates}
mkdir -p /var/lib/mailman3/web/static
mkdir -p /var/log/mailman3/web
mkdir -p /run/mailman3
mkdir -p /opt/mailman3/bin

chown -R list:list /var/lib/mailman3
chown -R list:list /var/log/mailman3
chown list:list /run/mailman3
chown -R www-data:www-data /var/lib/mailman3/web
```

### Step 4: Configure Mailman3 Core

Copy `config/mailman3/mailman.cfg.sample` to `/etc/mailman3/mailman.cfg` and edit:
- Set database URL with your `mailman3` DB credentials
- Set `admin_pass` to a strong random password (this is the REST API password)
- Set `site_owner` to your admin email

```bash
mkdir -p /etc/mailman3
cp config/mailman3/mailman.cfg.sample /etc/mailman3/mailman.cfg
chown root:list /etc/mailman3/mailman.cfg
chmod 640 /etc/mailman3/mailman.cfg
# Edit the file with your credentials
```

### Step 5: Configure HyperKitty Archiver

Copy `config/mailman3/mailman-hyperkitty.cfg.sample` to `/etc/mailman3/mailman-hyperkitty.cfg` and edit:
- Set `api_key` to a shared secret (must match `MAILMAN_ARCHIVER_KEY` in settings.py)
- Set `base_url` to where gunicorn listens (e.g., `http://localhost:8083/hyperkitty/`)

```bash
cp config/mailman3/mailman-hyperkitty.cfg.sample /etc/mailman3/mailman-hyperkitty.cfg
chown root:list /etc/mailman3/mailman-hyperkitty.cfg
chmod 640 /etc/mailman3/mailman-hyperkitty.cfg
```

### Step 6: Configure Mailman3 Web (Django)

Copy `config/mailman3/settings.py.sample` to `/etc/mailman3/settings.py` and edit:
- Set `SECRET_KEY` to a unique random string
- Set `DATABASES` credentials for the `mailman3web` DB
- Set `MAILMAN_REST_API_PASS` to match `admin_pass` in mailman.cfg
- Set `MAILMAN_ARCHIVER_KEY` to match `api_key` in mailman-hyperkitty.cfg
- Set `EMAILNAME`, `TIME_ZONE`, `ADMINS` for your environment
- Set `POSTORIUS_TEMPLATE_BASE_URL` to your gunicorn listen address

```bash
cp config/mailman3/settings.py.sample /etc/mailman3/settings.py
chown root:www-data /etc/mailman3/settings.py
chmod 640 /etc/mailman3/settings.py
chmod 755 /etc/mailman3
```

### Step 7: Initialize Django

```bash
# Run database migrations
MAILMAN_WEB_CONFIG=/etc/mailman3/settings.py /opt/mailman3/venv/bin/mailman-web migrate

# Collect static files
MAILMAN_WEB_CONFIG=/etc/mailman3/settings.py /opt/mailman3/venv/bin/mailman-web collectstatic --noinput

# Compress static files (requires sassc)
MAILMAN_WEB_CONFIG=/etc/mailman3/settings.py /opt/mailman3/venv/bin/mailman-web compress

# Create Django superuser
MAILMAN_WEB_CONFIG=/etc/mailman3/settings.py /opt/mailman3/venv/bin/mailman-web createsuperuser

# Fix ownership
chown -R www-data:www-data /var/lib/mailman3/web/
```

### Step 8: Install Systemd Services

```bash
cp config/systemd/mailman3.service /etc/systemd/system/
cp config/systemd/mailman3-web.service /etc/systemd/system/
cp config/systemd/mailman3-web-qcluster.service /etc/systemd/system/

# Edit mailman3-web.service: replace YOUR_PRIVATE_IP with your server's private IP
# (needed if your reverse proxy is on a separate host)

systemctl daemon-reload
systemctl enable mailman3 mailman3-web mailman3-web-qcluster
systemctl start mailman3
systemctl start mailman3-web
systemctl start mailman3-web-qcluster
```

### Step 9: Configure Postfix

Add Mailman3's transport map and virtual alias map to `/etc/postfix/main.cf`:

```bash
# Add to transport_maps (routes list addresses to Mailman3 LMTP)
postconf -e "transport_maps = hash:/var/lib/mailman3/data/postfix_lmtp, <your existing transport_maps>"

# Add postfix user to list group (for reading map files)
usermod -aG list postfix
```

**Important:** List domains that are also in `virtual_mailbox_domains` need a `postfix_vmap` entry so Postfix accepts list addresses as valid recipients. Generate it:

```bash
cp scripts/regenerate-postfix-vmap.sh /opt/mailman3/bin/
chmod +x /opt/mailman3/bin/regenerate-postfix-vmap.sh
/opt/mailman3/bin/regenerate-postfix-vmap.sh

# Add to virtual_alias_maps
postconf -e "virtual_alias_maps = hash:/var/lib/mailman3/data/postfix_vmap, <your existing virtual_alias_maps>"

postfix reload
```

Run `regenerate-postfix-vmap.sh` after creating or deleting lists.

### Step 10: Install the ISPConfig Extension

```bash
# Copy extension to ISPConfig
cp -r extensions/mailmanthree /usr/local/ispconfig/extensions/

# The file.list handles symlinking into ISPConfig's interface.
# Run the ISPConfig extension installer or manually create symlinks:
cd /usr/local/ispconfig/interface/web/mail
ln -sf ../../../extensions/mailmanthree/mailmanthree_list_list.php
ln -sf ../../../extensions/mailmanthree/mailmanthree_list_edit.php
ln -sf ../../../extensions/mailmanthree/mailmanthree_list_del.php
ln -sf ../../../extensions/mailmanthree/mailmanthree_config_edit.php
ln -sf ../../../extensions/mailmanthree/mailmanthree_sync_now.php

# Symlink subdirectories
cd /usr/local/ispconfig/interface/web/mail
for dir in form lib list templates; do
    for f in ../../../extensions/mailmanthree/$dir/mailmanthree*; do
        ln -sf "$f" "$dir/"
    done
done

# Symlink menu
ln -sf ../../../extensions/mailmanthree/mailmanthree.menu.php \
  /usr/local/ispconfig/interface/web/mail/

# Create the database tables
mysql -u root dbispconfig < extensions/mailmanthree/install/sql/mailmanthree.sql

# Configure API credentials in ISPConfig:
# Go to Email → Mailman3 Config and enter:
#   - API URL: http://localhost:8001
#   - API User: restadmin
#   - API Password: (same as admin_pass in mailman.cfg)
#   - Postorius URL: https://your-server.example.com/postorius
#   - HyperKitty URL: https://your-server.example.com/hyperkitty
```

### Step 11: Configure Reverse Proxy (nginx)

If nginx is on a separate host, add location blocks proxying to port 8083:

```nginx
# Mailman3 web interface
location /postorius/ { proxy_pass http://BACKEND_IP:8083; ... }
location /hyperkitty/ { proxy_pass http://BACKEND_IP:8083; ... }
location /accounts/ { proxy_pass http://BACKEND_IP:8083; ... }
location /admin/ { proxy_pass http://BACKEND_IP:8083; ... }
location /user-profile/ { proxy_pass http://BACKEND_IP:8083; ... }
location /archives/ { proxy_pass http://BACKEND_IP:8083; ... }
location /mailman3/static/ { proxy_pass http://BACKEND_IP:8083; ... }
```

The `/archives/` block is required for HyperKitty's AJAX calls (recent threads, top posters, etc.).

### Step 12: Verify

```bash
# Check Mailman3 core
curl -s -u restadmin:YOUR_API_PASS http://localhost:8001/3.1/system/versions

# Check LMTP
ss -tlnp | grep 8024

# Check web interface
curl -s -o /dev/null -w "%{http_code}" http://localhost:8083/postorius/lists/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8083/hyperkitty/

# Check Postfix transport map
postmap -q "yourlist@lists.example.com" hash:/var/lib/mailman3/data/postfix_lmtp
```

## File Permissions Summary

| Path | Owner | Mode | Notes |
|------|-------|------|-------|
| `/etc/mailman3/mailman.cfg` | root:list | 640 | Core config with DB creds |
| `/etc/mailman3/mailman-hyperkitty.cfg` | root:list | 640 | Archiver config |
| `/etc/mailman3/settings.py` | root:www-data | 640 | Django settings with DB creds |
| `/var/lib/mailman3/` | list:list | — | All subdirs except web/ |
| `/var/lib/mailman3/web/` | www-data:www-data | — | Static files, fulltext index |
| `/var/lib/mailman3/queue/*/` | list:list | 770 | Queue directories |
| `/var/log/mailman3/` | list:list | — | Core logs |
| `/var/log/mailman3/web/` | www-data:www-data | — | Gunicorn/Django logs |

## Migrating from Mailman2

If migrating from Mailman2 with pipermail archives:

1. Import lists using Mailman3's `mailman import21` command
2. Import archives into HyperKitty using `hyperkitty_import`
3. Set up pipermail redirects in Apache (see `config/` for examples)
4. Rename ISPConfig's `mail_mailinglist` table to preserve Mailman2 data:
   ```sql
   RENAME TABLE mail_mailinglist TO mail_mailinglist_mm2_backup;
   -- Then recreate an empty mail_mailinglist table (ISPConfig expects it to exist)
   ```

## Repository Structure

```
extensions/mailmanthree/    # ISPConfig extension (copy to /usr/local/ispconfig/extensions/)
config/
  mailman3/                 # Configuration file templates (.sample files)
  systemd/                  # Systemd service units
scripts/
  regenerate-postfix-vmap.sh  # Regenerates Postfix virtual alias map after list changes
```

## License

GPL-3.0 — see [LICENSE](LICENSE)
