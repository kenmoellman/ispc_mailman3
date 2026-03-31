<?php

/******************************************
* Begin Form configuration
******************************************/

$tform_def_file = "form/mailmanthree_config.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

//* Check permissions for module
$app->auth->check_module_permissions('mail');

//* Admin-only page
if (!$app->auth->is_admin()) {
	die('Access denied.');
}

// Loading classes
$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');

require_once 'lib/mailmanthree_rest.inc.php';

class page_action extends tform_actions {

	function onShowEnd() {
		global $app;

		// Test the current connection
		$config = $app->db->queryOneRecord("SELECT * FROM mailmanthree_config ORDER BY mailmanthree_config_id LIMIT 1");

		if ($config && !empty($config['api_pass'])) {
			$mm = new mailmanthree_rest($config['api_url'], $config['api_user'], $config['api_pass']);
			if ($mm->check_connection()) {
				$app->tpl->setVar('connection_status', 'ok');
				$app->tpl->setVar('connection_message', 'Connected to Mailman3 API');
			} else {
				$app->tpl->setVar('connection_status', 'error');
				$app->tpl->setVar('connection_message', 'Connection failed: ' . $mm->get_last_error());
			}
		} else {
			$app->tpl->setVar('connection_status', '');
			$app->tpl->setVar('connection_message', 'Not configured — enter API credentials and save.');
		}

		parent::onShowEnd();
	}

	function onBeforeInsert() {
		global $app;

		// Only allow one config row — if one exists, switch to update
		$existing = $app->db->queryOneRecord("SELECT mailmanthree_config_id FROM mailmanthree_config ORDER BY mailmanthree_config_id LIMIT 1");
		if ($existing) {
			$this->id = $existing['mailmanthree_config_id'];
		}
	}

	function onSubmit() {
		global $app;

		// If only one config row allowed, load its ID for update
		if ($this->id == 0) {
			$existing = $app->db->queryOneRecord("SELECT mailmanthree_config_id FROM mailmanthree_config ORDER BY mailmanthree_config_id LIMIT 1");
			if ($existing) {
				$this->id = $existing['mailmanthree_config_id'];
			}
		}

		parent::onSubmit();
	}
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();
