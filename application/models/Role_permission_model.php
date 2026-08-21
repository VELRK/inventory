<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_permission_model extends CI_Model
{
	public function roles()
	{
		return array(
			'promoter_admin' => 'Promoter admin',
			'marketing_team_admin' => 'Marketing team admin',
			'marketing_team_user' => 'Marketing team user'
		);
	}

	/**
	 * Catalog of portal access keys admins can toggle.
	 * group helps the UI section the matrix.
	 */
	public function catalog()
	{
		return array(
			array('key' => 'nav.dashboard', 'label' => 'Dashboard', 'group' => 'Menu'),
			array('key' => 'nav.projects', 'label' => 'Projects', 'group' => 'Menu'),
			array('key' => 'nav.inventory', 'label' => 'Inventory', 'group' => 'Menu'),
			array('key' => 'nav.companies', 'label' => 'Companies', 'group' => 'Menu'),
			array('key' => 'nav.users', 'label' => 'Users', 'group' => 'Menu'),
			array('key' => 'nav.requests', 'label' => 'Requests', 'group' => 'Menu'),
			array('key' => 'nav.bookings', 'label' => 'Bookings', 'group' => 'Menu'),
			array('key' => 'nav.activity', 'label' => 'Activity', 'group' => 'Menu'),
			array('key' => 'nav.settings', 'label' => 'Settings', 'group' => 'Menu'),
			array('key' => 'nav.email_templates', 'label' => 'Email templates', 'group' => 'Menu'),
			array('key' => 'nav.access', 'label' => 'Access control', 'group' => 'Menu'),
			array('key' => 'nav.schema', 'label' => 'Schema Studio', 'group' => 'Menu'),
			array('key' => 'nav.api_tester', 'label' => 'API Tester', 'group' => 'Menu'),

			array('key' => 'projects.manage', 'label' => 'Create / edit / delete projects', 'group' => 'Actions'),
			array('key' => 'inventory.create', 'label' => 'Create inventory units', 'group' => 'Actions'),
			array('key' => 'inventory.edit', 'label' => 'Edit inventory units / status', 'group' => 'Actions'),
			array('key' => 'inventory.delete', 'label' => 'Archive inventory units', 'group' => 'Actions'),
			array('key' => 'companies.manage', 'label' => 'Manage companies', 'group' => 'Actions'),
			array('key' => 'users.manage', 'label' => 'Manage users', 'group' => 'Actions'),
			array('key' => 'requests.review', 'label' => 'Approve / reject hold requests', 'group' => 'Actions'),
			array('key' => 'bookings.manage', 'label' => 'Create / manage bookings', 'group' => 'Actions'),
			array('key' => 'registrations.manage', 'label' => 'Manage registrations', 'group' => 'Actions'),
			array('key' => 'settings.manage', 'label' => 'Change SMTP / settings', 'group' => 'Actions'),
			array('key' => 'activity.view', 'label' => 'View activity log', 'group' => 'Actions'),
			array('key' => 'access.manage', 'label' => 'Edit role access matrix', 'group' => 'Actions'),
		);
	}

	public function defaults()
	{
		$all = array();
		foreach ($this->catalog() as $row) {
			$all[] = $row['key'];
		}
		$team_admin = array(
			'nav.dashboard', 'nav.projects', 'nav.inventory', 'nav.requests', 'nav.bookings', 'nav.users',
			'inventory.edit', 'users.manage', 'bookings.manage'
		);
		$team_user = array(
			'nav.dashboard', 'nav.projects', 'nav.inventory', 'nav.requests', 'nav.users'
		);
		return array(
			'promoter_admin' => $all,
			'marketing_team_admin' => $team_admin,
			'marketing_team_user' => $team_user
		);
	}

	public function ensure_table()
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `role_permissions` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`role` VARCHAR(40) NOT NULL,
			`permission_key` VARCHAR(80) NOT NULL,
			`is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
			`updated_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uk_role_perm` (`role`, `permission_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	public function ensure_seeded()
	{
		$this->ensure_table();
		$count = (int) $this->db->count_all('role_permissions');
		if ($count > 0) {
			return;
		}
		$now = now_dt();
		foreach ($this->defaults() as $role => $keys) {
			foreach ($keys as $key) {
				$this->db->insert('role_permissions', array(
					'role' => $role,
					'permission_key' => $key,
					'is_allowed' => 1,
					'created_at' => $now,
					'updated_at' => $now
				));
			}
		}
	}

	public function permissions_for_role($role)
	{
		$this->ensure_seeded();
		$rows = $this->db->get_where('role_permissions', array(
			'role' => $role,
			'is_allowed' => 1
		))->result();
		$out = array();
		foreach ($rows as $row) {
			$out[] = $row->permission_key;
		}
		// If somehow empty after seed, fall back to defaults.
		if (!$out && isset($this->defaults()[$role])) {
			return $this->defaults()[$role];
		}
		return $out;
	}

	public function has($role, $permission_key)
	{
		if ($role === 'promoter_admin' && in_array($permission_key, array('nav.access', 'access.manage'), true)) {
			return true;
		}
		return in_array($permission_key, $this->permissions_for_role($role), true);
	}

	public function matrix()
	{
		$this->ensure_seeded();
		$roles = array_keys($this->roles());
		$catalog = $this->catalog();
		$allowed = array();
		foreach ($roles as $role) {
			$allowed[$role] = $this->permissions_for_role($role);
		}
		$items = array();
		foreach ($catalog as $row) {
			$flags = array();
			foreach ($roles as $role) {
				$flags[$role] = in_array($row['key'], $allowed[$role], true);
			}
			$items[] = array(
				'key' => $row['key'],
				'label' => $row['label'],
				'group' => $row['group'],
				'roles' => $flags
			);
		}
		return array(
			'roles' => $this->roles(),
			'permissions' => $items
		);
	}

	/**
	 * $matrix = array('marketing_team_admin' => array('nav.dashboard' => true, ...), ...)
	 */
	public function save_matrix($matrix)
	{
		$this->ensure_seeded();
		$valid_roles = array_keys($this->roles());
		$valid_keys = array();
		foreach ($this->catalog() as $row) {
			$valid_keys[] = $row['key'];
		}
		$now = now_dt();
		foreach ((array) $matrix as $role => $perms) {
			if (!in_array($role, $valid_roles, true)) {
				continue;
			}
			foreach ((array) $perms as $key => $allowed) {
				if (!in_array($key, $valid_keys, true)) {
					continue;
				}
				// Promoter always keeps Access page control.
				if ($role === 'promoter_admin' && in_array($key, array('nav.access', 'access.manage'), true)) {
					$allowed = 1;
				}
				$is_allowed = $allowed ? 1 : 0;
				$exists = $this->db->get_where('role_permissions', array(
					'role' => $role,
					'permission_key' => $key
				))->row();
				if ($exists) {
					$this->db->where('id', (int) $exists->id)->update('role_permissions', array(
						'is_allowed' => $is_allowed,
						'updated_at' => $now
					));
				} else {
					$this->db->insert('role_permissions', array(
						'role' => $role,
						'permission_key' => $key,
						'is_allowed' => $is_allowed,
						'created_at' => $now,
						'updated_at' => $now
					));
				}
			}
		}
		return $this->matrix();
	}
}
