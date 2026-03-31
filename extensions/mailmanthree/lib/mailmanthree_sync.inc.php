<?php

/**
 * Mailman3 Sync Class for ISPConfig3
 *
 * Synchronizes mailing list data between the Mailman3 REST API and the
 * ISPConfig DB cache table (mailmanthree_list). Derives client ownership
 * from mail_domain.sys_userid/sys_groupid.
 */

class mailmanthree_sync {

	private $mm;

	/**
	 * Constructor. Initializes the REST API client.
	 */
	public function __construct() {
		require_once dirname(__FILE__) . '/mailmanthree_rest.inc.php';
		$this->mm = new mailmanthree_rest();
	}

	/**
	 * Run a full sync: pull all lists from Mailman3 API, reconcile with DB cache.
	 *
	 * - Creates/updates cache rows for lists found in Mailman3
	 * - Preserves owner_email (ISPConfig-only field, not in Mailman3)
	 * - Purges cache rows for lists deleted from Mailman3
	 * - Derives sys_userid/sys_groupid from mail_domain ownership
	 *
	 * @return array  Keys: 'synced' (count), 'purged' (count), 'error' (string or empty)
	 */
	public function run_full_sync() {
		global $app;

		$result = array('synced' => 0, 'purged' => 0, 'error' => '');

		// Fetch all lists from Mailman3
		$mm_lists = $this->mm->get_all_lists();
		if ($mm_lists === false || ($this->mm->get_last_http_code() >= 400 && empty($mm_lists))) {
			$result['error'] = 'Failed to fetch lists from Mailman3: ' . $this->mm->get_last_error();
			return $result;
		}

		// Build a map of list_id => Mailman3 data
		$mm_map = array();
		foreach ($mm_lists as $mm_list) {
			$list_id = isset($mm_list['list_id']) ? $mm_list['list_id'] : '';
			if (!empty($list_id)) {
				$mm_map[$list_id] = $mm_list;
			}
		}

		// Fetch all existing cache rows
		$db_rows = $app->db->queryAllRecords("SELECT * FROM mailmanthree_list");
		$db_map = array();
		if (is_array($db_rows)) {
			foreach ($db_rows as $row) {
				$db_map[$row['list_id']] = $row;
			}
		}

		// Upsert: create or update cache rows for each Mailman3 list
		foreach ($mm_map as $list_id => $mm_list) {
			$record = $this->build_record($mm_list);

			if (isset($db_map[$list_id])) {
				// Update existing row, preserving owner_email
				$existing = $db_map[$list_id];
				$record['owner_email'] = $existing['owner_email'];

				$app->db->query(
					"UPDATE mailmanthree_list SET " .
					"sys_userid = ?, sys_groupid = ?, server_id = ?, " .
					"fqdn_listname = ?, list_name = ?, domain = ?, " .
					"display_name = ?, description = ?, owner_email = ?, " .
					"member_count = ?, last_synced = NOW(), sync_error = NULL " .
					"WHERE list_id = ?",
					$record['sys_userid'], $record['sys_groupid'], $record['server_id'],
					$record['fqdn_listname'], $record['list_name'], $record['domain'],
					$record['display_name'], $record['description'], $record['owner_email'],
					$record['member_count'], $list_id
				);
			} else {
				// Insert new row
				$app->db->query(
					"INSERT INTO mailmanthree_list " .
					"(sys_userid, sys_groupid, sys_perm_user, sys_perm_group, sys_perm_other, " .
					"server_id, list_id, fqdn_listname, list_name, domain, " .
					"display_name, description, owner_email, active, member_count, last_synced) " .
					"VALUES (?, ?, 'riud', 'riud', '', ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
					$record['sys_userid'], $record['sys_groupid'],
					$record['server_id'], $list_id, $record['fqdn_listname'],
					$record['list_name'], $record['domain'],
					$record['display_name'], $record['description'], $record['owner_email'],
					$record['member_count']
				);
			}

			$result['synced']++;
		}

		// Purge: remove cache rows for lists no longer in Mailman3
		foreach ($db_map as $list_id => $row) {
			if (!isset($mm_map[$list_id])) {
				$app->db->query("DELETE FROM mailmanthree_list WHERE list_id = ?", $list_id);
				$result['purged']++;
			}
		}

		return $result;
	}

	/**
	 * Sync a single list after create/update. Fetches fresh data from API.
	 *
	 * @param string $list_id      Mailman3 list_id (name.domain format)
	 * @param string $owner_email  Owner email (ISPConfig-only, passed through)
	 * @return bool
	 */
	public function sync_single_list($list_id, $owner_email = '') {
		global $app;

		$mm_list = $this->mm->get_list($list_id);
		if ($mm_list === false) {
			return false;
		}

		$record = $this->build_record($mm_list);
		if (!empty($owner_email)) {
			$record['owner_email'] = $owner_email;
		}

		// Check if row already exists
		$existing = $app->db->queryOneRecord("SELECT mailmanthree_list_id, owner_email FROM mailmanthree_list WHERE list_id = ?", $list_id);

		if ($existing) {
			// Preserve owner_email if not explicitly provided
			if (empty($owner_email)) {
				$record['owner_email'] = $existing['owner_email'];
			}

			$app->db->query(
				"UPDATE mailmanthree_list SET " .
				"sys_userid = ?, sys_groupid = ?, server_id = ?, " .
				"fqdn_listname = ?, list_name = ?, domain = ?, " .
				"display_name = ?, description = ?, owner_email = ?, " .
				"member_count = ?, last_synced = NOW(), sync_error = NULL " .
				"WHERE list_id = ?",
				$record['sys_userid'], $record['sys_groupid'], $record['server_id'],
				$record['fqdn_listname'], $record['list_name'], $record['domain'],
				$record['display_name'], $record['description'], $record['owner_email'],
				$record['member_count'], $list_id
			);
		} else {
			$app->db->query(
				"INSERT INTO mailmanthree_list " .
				"(sys_userid, sys_groupid, sys_perm_user, sys_perm_group, sys_perm_other, " .
				"server_id, list_id, fqdn_listname, list_name, domain, " .
				"display_name, description, owner_email, active, member_count, last_synced) " .
				"VALUES (?, ?, 'riud', 'riud', '', ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
				$record['sys_userid'], $record['sys_groupid'],
				$record['server_id'], $list_id, $record['fqdn_listname'],
				$record['list_name'], $record['domain'],
				$record['display_name'], $record['description'], $record['owner_email'],
				$record['member_count']
			);
		}

		return true;
	}

	/**
	 * Remove a list from the DB cache after deletion.
	 *
	 * @param string $list_id  Mailman3 list_id (name.domain format)
	 * @return bool
	 */
	public function remove_from_cache($list_id) {
		global $app;
		$app->db->query("DELETE FROM mailmanthree_list WHERE list_id = ?", $list_id);
		return true;
	}

	/**
	 * Build a cache record from Mailman3 API list data.
	 * Derives client ownership from mail_domain table.
	 *
	 * @param array $mm_list  Mailman3 list entry from API
	 * @return array  Cache record fields
	 */
	private function build_record($mm_list) {
		global $app;

		// Extract list name and domain from fqdn_listname (user@domain)
		$fqdn = isset($mm_list['fqdn_listname']) ? $mm_list['fqdn_listname'] : '';
		$parts = explode('@', $fqdn, 2);
		$list_name = isset($parts[0]) ? $parts[0] : '';
		$domain = isset($parts[1]) ? $parts[1] : '';

		// Get display_name from API
		$display_name = isset($mm_list['display_name']) ? $mm_list['display_name'] : '';

		// Get description from API (may need config endpoint for full description)
		$description = isset($mm_list['description']) ? $mm_list['description'] : '';

		// Get member count
		$member_count = isset($mm_list['member_count']) ? intval($mm_list['member_count']) : 0;

		// Derive ownership from mail_domain
		$sys_userid = 1;
		$sys_groupid = 1;
		$server_id = 1;

		if (!empty($domain)) {
			$mail_domain = $app->db->queryOneRecord(
				"SELECT sys_userid, sys_groupid, server_id FROM mail_domain WHERE domain = ?",
				$domain
			);
			if ($mail_domain) {
				$sys_userid = intval($mail_domain['sys_userid']);
				$sys_groupid = intval($mail_domain['sys_groupid']);
				$server_id = intval($mail_domain['server_id']);
			}
		}

		return array(
			'sys_userid'    => $sys_userid,
			'sys_groupid'   => $sys_groupid,
			'server_id'     => $server_id,
			'fqdn_listname' => $fqdn,
			'list_name'     => $list_name,
			'domain'        => $domain,
			'display_name'  => $display_name,
			'description'   => $description,
			'owner_email'   => '',
			'member_count'  => $member_count,
		);
	}
}
