<?php

/**
 * Mailman3 REST API Bridge for ISPConfig3
 *
 * Wraps the Mailman3 Core REST API (v3.1) for list management.
 * Handles HTTP Basic auth, form-encoded requests, JSON responses,
 * and Mailman3-specific quirks (boolean strings, dot-separated list_id).
 */

class mailmanthree_rest {

	private $base_url;
	private $username;
	private $password;
	private $last_error = '';
	private $last_http_code = 0;

	/**
	 * Constructor. Accepts explicit credentials or loads from DB.
	 *
	 * @param string|null $base_url  Mailman3 REST API base URL
	 * @param string|null $username  API username
	 * @param string|null $password  API password
	 */
	public function __construct($base_url = null, $username = null, $password = null) {
		global $app;

		if ($base_url !== null && $username !== null && $password !== null) {
			$this->base_url = rtrim($base_url, '/');
			$this->username = $username;
			$this->password = $password;
		} else {
			$this->load_config_from_db();
		}
	}

	/**
	 * Load API credentials from the mailmanthree_config table.
	 */
	private function load_config_from_db() {
		global $app;

		$row = $app->db->queryOneRecord("SELECT api_url, api_user, api_pass FROM mailmanthree_config ORDER BY mailmanthree_config_id LIMIT 1");
		if ($row) {
			$this->base_url = rtrim($row['api_url'], '/');
			$this->username = $row['api_user'];
			$this->password = $row['api_pass'];
		} else {
			$this->base_url = 'http://127.0.0.1:8001/3.1';
			$this->username = 'restadmin';
			$this->password = '';
		}
	}

	/**
	 * Get the last error message.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Get the last HTTP response code.
	 *
	 * @return int
	 */
	public function get_last_http_code() {
		return $this->last_http_code;
	}

	/**
	 * Make an HTTP request to the Mailman3 REST API.
	 *
	 * @param string $method   HTTP method: GET, POST, PATCH, PUT, DELETE
	 * @param string $endpoint API endpoint (relative to base_url)
	 * @param array  $data     Request body data (form-encoded for POST/PATCH/PUT)
	 * @return array|false     Decoded JSON response or false on error
	 */
	public function request($method, $endpoint, $data = array()) {
		$url = $this->base_url . '/' . ltrim($endpoint, '/');
		$this->last_error = '';
		$this->last_http_code = 0;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

		$method = strtoupper($method);
		switch ($method) {
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
				break;
			case 'PATCH':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
				break;
			case 'PUT':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
				break;
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			case 'GET':
			default:
				// GET is the default
				break;
		}

		$response = curl_exec($ch);
		$this->last_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (curl_errno($ch)) {
			$this->last_error = 'cURL error: ' . curl_error($ch);
			curl_close($ch);
			return false;
		}

		curl_close($ch);

		// 204 No Content is a valid success response (e.g., DELETE)
		if ($this->last_http_code == 204) {
			return array();
		}

		$decoded = json_decode($response, true);

		if ($this->last_http_code >= 400) {
			$reason = '';
			if (is_array($decoded) && isset($decoded['description'])) {
				$reason = $decoded['description'];
			} elseif (is_array($decoded) && isset($decoded['title'])) {
				$reason = $decoded['title'];
			} elseif (!empty($response)) {
				$reason = substr($response, 0, 200);
			}
			$this->last_error = 'HTTP ' . $this->last_http_code . ': ' . $reason;
			return false;
		}

		if ($decoded === null && !empty($response)) {
			$this->last_error = 'JSON decode error: ' . json_last_error_msg();
			return false;
		}

		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * Check if a domain exists in Mailman3.
	 *
	 * @param string $mail_host Domain name
	 * @return bool
	 */
	public function domain_exists($mail_host) {
		$result = $this->request('GET', 'domains/' . urlencode($mail_host));
		return ($result !== false && $this->last_http_code == 200);
	}

	/**
	 * Create a domain in Mailman3.
	 *
	 * @param string $mail_host Domain name
	 * @return bool
	 */
	public function create_domain($mail_host) {
		$result = $this->request('POST', 'domains', array(
			'mail_host' => $mail_host,
		));

		if ($result === false) {
			// 400 with "Duplicate email host" means it already exists — that's fine
			if ($this->last_http_code == 400 && stripos($this->last_error, 'Duplicate') !== false) {
				$this->last_error = '';
				return true;
			}
			return false;
		}

		return true;
	}

	/**
	 * Create a mailing list in Mailman3.
	 *
	 * Auto-creates the domain if it doesn't exist, creates the list,
	 * applies configuration via PATCH, and adds the owner with
	 * pre_verified/pre_confirmed/pre_approved flags.
	 *
	 * @param array $list_data  Keys: list_name, domain, display_name, description, owner_email
	 * @return bool
	 */
	public function create_list($list_data) {
		$list_name = $list_data['list_name'];
		$domain = $list_data['domain'];
		$fqdn = $list_name . '@' . $domain;

		// Step 1: Ensure domain exists
		if (!$this->create_domain($domain)) {
			return false;
		}

		// Step 2: Create the list
		$result = $this->request('POST', 'lists', array(
			'fqdn_listname' => $fqdn,
		));

		if ($result === false) {
			return false;
		}

		// Step 3: Apply configuration via PATCH
		$list_id = $list_name . '.' . $domain;
		$config_data = array();

		if (!empty($list_data['display_name'])) {
			$config_data['display_name'] = $list_data['display_name'];
		}
		if (!empty($list_data['description'])) {
			$config_data['description'] = $list_data['description'];
		}

		if (!empty($config_data)) {
			$config_result = $this->request('PATCH', 'lists/' . urlencode($list_id) . '/config', $config_data);
			if ($config_result === false) {
				// Log but don't fail — list was created successfully
				global $app;
				$app->log('mailmanthree_rest: Warning - list created but config PATCH failed: ' . $this->last_error, LOGLEVEL_WARN);
			}
		}

		// Step 4: Add owner
		if (!empty($list_data['owner_email'])) {
			$owner_result = $this->request('POST', 'lists/' . urlencode($list_id) . '/owner', array(
				'address'        => $list_data['owner_email'],
				'pre_verified'   => 'True',
				'pre_confirmed'  => 'True',
				'pre_approved'   => 'True',
			));

			if ($owner_result === false && $this->last_http_code != 409) {
				// 409 = already an owner, that's fine
				global $app;
				$app->log('mailmanthree_rest: Warning - list created but owner add failed: ' . $this->last_error, LOGLEVEL_WARN);
			}
		}

		$this->last_error = '';
		return true;
	}

	/**
	 * Update a mailing list's configuration in Mailman3.
	 *
	 * @param array $list_data  Keys: list_id, display_name, description, owner_email
	 * @return bool
	 */
	public function update_list($list_data) {
		$list_id = $list_data['list_id'];

		$config_data = array();
		if (isset($list_data['display_name'])) {
			$config_data['display_name'] = $list_data['display_name'];
		}
		if (isset($list_data['description'])) {
			$config_data['description'] = $list_data['description'];
		}

		if (!empty($config_data)) {
			$result = $this->request('PATCH', 'lists/' . urlencode($list_id) . '/config', $config_data);
			if ($result === false) {
				return false;
			}
		}

		// Update owner if provided
		if (!empty($list_data['owner_email'])) {
			$owner_result = $this->request('POST', 'lists/' . urlencode($list_id) . '/owner', array(
				'address'        => $list_data['owner_email'],
				'pre_verified'   => 'True',
				'pre_confirmed'  => 'True',
				'pre_approved'   => 'True',
			));
			// 409 = already owner, that's fine
			if ($owner_result === false && $this->last_http_code != 409) {
				global $app;
				$app->log('mailmanthree_rest: Warning - owner update failed: ' . $this->last_error, LOGLEVEL_WARN);
			}
		}

		$this->last_error = '';
		return true;
	}

	/**
	 * Delete a mailing list from Mailman3.
	 *
	 * @param string $list_name  List name (local part)
	 * @param string $domain     Domain
	 * @return bool
	 */
	public function delete_list($list_name, $domain) {
		$list_id = $list_name . '.' . $domain;
		$result = $this->request('DELETE', 'lists/' . urlencode($list_id));

		if ($result === false && $this->last_http_code != 404) {
			return false;
		}

		// 404 means already gone — that's fine
		$this->last_error = '';
		return true;
	}

	/**
	 * Get the configuration of a single mailing list.
	 *
	 * @param string $list_id  Mailman3 list_id (name.domain format)
	 * @return array|false
	 */
	public function get_list_config($list_id) {
		return $this->request('GET', 'lists/' . urlencode($list_id) . '/config');
	}

	/**
	 * Get a single list's details.
	 *
	 * @param string $list_id  Mailman3 list_id (name.domain format)
	 * @return array|false
	 */
	public function get_list($list_id) {
		return $this->request('GET', 'lists/' . urlencode($list_id));
	}

	/**
	 * Get all mailing lists from Mailman3, handling pagination.
	 *
	 * @return array  Array of list records
	 */
	public function get_all_lists() {
		$all_lists = array();
		$page = 1;
		$per_page = 50;

		do {
			$result = $this->request('GET', 'lists?page=' . $page . '&count=' . $per_page);

			if ($result === false) {
				return $all_lists;
			}

			if (isset($result['entries']) && is_array($result['entries'])) {
				foreach ($result['entries'] as $entry) {
					$all_lists[] = $entry;
				}
			}

			$total = isset($result['total_size']) ? intval($result['total_size']) : 0;
			$page++;

		} while (count($all_lists) < $total);

		return $all_lists;
	}

	/**
	 * Check API connection. Returns true on success, false on failure.
	 *
	 * @return bool
	 */
	public function check_connection() {
		$result = $this->request('GET', 'system/versions');
		return ($result !== false && $this->last_http_code == 200);
	}

	/**
	 * Convert a PHP boolean to Mailman3's string representation.
	 *
	 * @param bool $value
	 * @return string 'True' or 'False'
	 */
	public static function bool_to_mm($value) {
		return $value ? 'True' : 'False';
	}

	/**
	 * Convert Mailman3's string boolean to PHP boolean.
	 *
	 * @param string $value 'True' or 'False'
	 * @return bool
	 */
	public static function mm_to_bool($value) {
		return (strtolower($value) === 'true');
	}
}
