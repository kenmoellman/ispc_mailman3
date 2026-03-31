<?php

/**
 * Mailman3 Sync Cron Job (optional)
 *
 * This cron job is NOT required for normal operation — the ISPConfig module
 * syncs on login and after changes. This script exists only as a fallback
 * for environments that want periodic background sync.
 *
 * To enable: symlink into ISPConfig's cron.d directory
 *   ln -s /path/to/mailmanthree_sync.php /usr/local/ispconfig/server/cron.d/
 */

require_once '/usr/local/ispconfig/server/lib/config.inc.php';
require_once '/usr/local/ispconfig/server/lib/app.inc.php';
require_once '/usr/local/ispconfig/interface/web/mailmanthree/lib/mailmanthree_rest.inc.php';
require_once '/usr/local/ispconfig/interface/web/mailmanthree/lib/mailmanthree_sync.inc.php';

$syncer = new mailmanthree_sync();
$result = $syncer->run_full_sync();

if ($result['error']) {
    $app->log('mailmanthree_sync cron: error — ' . $result['error'], LOGLEVEL_ERROR);
}
