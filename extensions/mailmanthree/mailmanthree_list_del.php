<?php

/******************************************
* Begin Form configuration
******************************************/

$list_def_file = "list/mailmanthree_list.list.php";
$tform_def_file = "form/mailmanthree_list.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

//* Check permissions for module
$app->auth->check_module_permissions('mail');

// Loading classes
$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');

require_once 'lib/mailmanthree_rest.inc.php';
require_once 'lib/mailmanthree_sync.inc.php';

class page_action extends tform_actions {

	function onBeforeDelete() {
		global $app;

		// Fetch the list details before DB removal
		$rec = $app->db->queryOneRecord(
			"SELECT list_name, domain, list_id FROM mailmanthree_list WHERE mailmanthree_list_id = ?",
			$this->id
		);

		if ($rec) {
			// Delete from Mailman3 first
			$mm = new mailmanthree_rest();
			$success = $mm->delete_list($rec['list_name'], $rec['domain']);

			if (!$success) {
				$app->log('mailmanthree: Failed to delete list from Mailman3: ' . $mm->get_last_error(), LOGLEVEL_ERROR);
				// Continue with DB deletion anyway — the list may already be gone from Mailman3
			}

			// Remove from sync cache
			$syncer = new mailmanthree_sync();
			$syncer->remove_from_cache($rec['list_id']);
		}
	}
}

$page = new page_action;
$page->onDelete();
