<?php

/******************************************
* Begin Form configuration
******************************************/

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

	private $stored_password = '';

	function onShowEnd() {
		global $app, $conf;

		// Build domain SELECT options
		$sql = "SELECT domain FROM mail_domain WHERE " . $app->tform->getAuthSQL('r') . " AND active = 'y' ORDER BY domain";
		$domains = $app->db->queryAllRecords($sql);
		$domain_select = "<option value=''></option>";
		if (is_array($domains)) {
			foreach ($domains as $domain) {
				$selected = (isset($this->dataRecord['domain']) && $domain['domain'] == $this->dataRecord['domain']) ? 'SELECTED' : '';
				$domain_select .= "<option value='" . $app->functions->htmlentities($domain['domain']) . "' $selected>" . $app->functions->htmlentities($domain['domain']) . "</option>\r\n";
			}
		}
		$app->tpl->setVar('domain_option', $domain_select);

		if ($this->id > 0) {
			//* Editing existing record — lock domain and list_name
			$app->tpl->setVar('edit_disabled', 1);
			$app->tpl->setVar('list_name_value', $this->dataRecord['list_name'], true);
			$app->tpl->setVar('domain_value', $this->dataRecord['domain'], true);

			// Build Postorius and HyperKitty links
			$config = $app->db->queryOneRecord("SELECT postorius_url, hyperkitty_url FROM mailmanthree_config ORDER BY mailmanthree_config_id LIMIT 1");
			$postorius_url = $config ? rtrim($config['postorius_url'], '/') : '/postorius';
			$hyperkitty_url = $config ? rtrim($config['hyperkitty_url'], '/') : '/hyperkitty';

			$list_id = $this->dataRecord['list_name'] . '.' . $this->dataRecord['domain'];
			$fqdn_listname = $this->dataRecord['list_name'] . '@' . $this->dataRecord['domain'];

			$app->tpl->setVar('postorius_link', $postorius_url . '/lists/' . $fqdn_listname . '/');
			$app->tpl->setVar('hyperkitty_link', $hyperkitty_url . '/list/' . $fqdn_listname . '/');
		} else {
			$app->tpl->setVar('edit_disabled', 0);
		}

		parent::onShowEnd();
	}

	function onSubmit() {
		global $app, $conf;

		// Store password before it gets processed — we need it for Postorius provisioning
		if (isset($this->dataRecord['password']) && $this->dataRecord['password'] != '') {
			$this->stored_password = $this->dataRecord['password'];
		}

		// Force domain to lowercase
		if (isset($this->dataRecord['domain'])) {
			$this->dataRecord['domain'] = strtolower($this->dataRecord['domain']);
		}

		// Force list_name to lowercase
		if (isset($this->dataRecord['list_name'])) {
			$this->dataRecord['list_name'] = strtolower($this->dataRecord['list_name']);
		}

		// Build Mailman3 identifiers
		$list_name = $this->dataRecord['list_name'];
		$domain = $this->dataRecord['domain'];
		$list_id = $list_name . '.' . $domain;
		$fqdn_listname = $list_name . '@' . $domain;

		// Store computed values
		$this->dataRecord['list_id'] = $list_id;
		$this->dataRecord['fqdn_listname'] = $fqdn_listname;

		// Set server_id from mail_domain
		$mail_domain = $app->db->queryOneRecord("SELECT server_id FROM mail_domain WHERE domain = ?", $domain);
		if ($mail_domain) {
			$this->dataRecord['server_id'] = $mail_domain['server_id'];
		}

		// Call Mailman3 REST API FIRST — abort on failure
		$mm = new mailmanthree_rest();

		if ($this->id == 0) {
			// Creating new list
			$success = $mm->create_list(array(
				'list_name'    => $list_name,
				'domain'       => $domain,
				'display_name' => isset($this->dataRecord['display_name']) ? $this->dataRecord['display_name'] : '',
				'description'  => isset($this->dataRecord['description']) ? $this->dataRecord['description'] : '',
				'owner_email'  => isset($this->dataRecord['owner_email']) ? $this->dataRecord['owner_email'] : '',
			));

			if (!$success) {
				$app->tform->errorMessage .= 'Mailman3 API error: ' . $mm->get_last_error() . '<br />';
				// Don't call parent — abort the save
				parent::onSubmit();
				return;
			}
		} else {
			// Updating existing list
			$success = $mm->update_list(array(
				'list_id'      => $list_id,
				'display_name' => isset($this->dataRecord['display_name']) ? $this->dataRecord['display_name'] : '',
				'description'  => isset($this->dataRecord['description']) ? $this->dataRecord['description'] : '',
				'owner_email'  => isset($this->dataRecord['owner_email']) ? $this->dataRecord['owner_email'] : '',
			));

			if (!$success) {
				$app->tform->errorMessage .= 'Mailman3 API error: ' . $mm->get_last_error() . '<br />';
			}
		}

		// Provision Postorius user if password was set
		if (!empty($this->stored_password) && !empty($this->dataRecord['owner_email'])) {
			$this->provision_postorius_user($this->dataRecord['owner_email'], $this->stored_password);
		}

		// Remove password from dataRecord — it is not stored in our DB
		unset($this->dataRecord['password']);

		// Call parent to save the DB record
		parent::onSubmit();
	}

	function onAfterInsert() {
		global $app;

		// Run targeted sync to populate member_count etc.
		if (!empty($this->dataRecord['list_id'])) {
			$syncer = new mailmanthree_sync();
			$syncer->sync_single_list(
				$this->dataRecord['list_id'],
				isset($this->dataRecord['owner_email']) ? $this->dataRecord['owner_email'] : ''
			);
		}

		parent::onAfterInsert();
	}

	function onAfterUpdate() {
		global $app;

		// Run targeted sync to refresh data
		if (!empty($this->dataRecord['list_id'])) {
			$syncer = new mailmanthree_sync();
			$syncer->sync_single_list(
				$this->dataRecord['list_id'],
				isset($this->dataRecord['owner_email']) ? $this->dataRecord['owner_email'] : ''
			);
		}

		parent::onAfterUpdate();
	}

	/**
	 * Provision a Postorius (Django) user account.
	 *
	 * @param string $email    User email
	 * @param string $password User password
	 */
	private function provision_postorius_user($email, $password) {
		global $app;

		$script = '/usr/local/bin/manage_postorius_user.py';
		if (!file_exists($script)) {
			$app->log('mailmanthree: Postorius user script not found: ' . $script, LOGLEVEL_WARN);
			return;
		}

		$cmd = 'python3 ' . escapeshellarg($script)
			. ' --email ' . escapeshellarg($email)
			. ' --password ' . escapeshellarg($password)
			. ' --action create 2>&1';

		$output = '';
		$retval = 0;
		exec($cmd, $output_arr, $retval);
		$output = implode("\n", $output_arr);

		if ($retval != 0) {
			$app->log('mailmanthree: Postorius user provisioning failed: ' . $output, LOGLEVEL_WARN);
		}
	}
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();
