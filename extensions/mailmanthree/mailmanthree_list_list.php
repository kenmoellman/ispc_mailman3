<?php

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

/******************************************
* Begin Form configuration
******************************************/

$list_def_file = "list/mailmanthree_list.list.php";

/******************************************
* End Form configuration
******************************************/

//* Check permissions for module
$app->auth->check_module_permissions('mail');

// Sync from Mailman3 on first load per session or on explicit sync request
$sync_needed = false;
if (isset($_GET['sync']) && $_GET['sync'] == '1') {
	$sync_needed = true;
} elseif (!isset($_SESSION['mailmanthree_last_sync'])) {
	$sync_needed = true;
}

if ($sync_needed) {
	require_once 'lib/mailmanthree_rest.inc.php';
	require_once 'lib/mailmanthree_sync.inc.php';

	$syncer = new mailmanthree_sync();
	$sync_result = $syncer->run_full_sync();
	$_SESSION['mailmanthree_last_sync'] = time();

	if (!empty($sync_result['error'])) {
		$app->log('mailmanthree: Sync error - ' . $sync_result['error'], LOGLEVEL_WARN);
	}
}

$app->uses('listform_actions');

class list_action extends listform_actions {
}

$list = new list_action;
$list->onLoad();
