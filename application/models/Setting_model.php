<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Setting_model extends CI_Model
{
	public function get($key, $default = null)
	{
		$row = $this->db->get_where('settings', array('setting_key' => $key))->row();
		if (!$row) {
			return $default;
		}
		return $row->setting_value;
	}

	public function all_grouped()
	{
		$rows = $this->db->order_by('setting_group', 'ASC')->get('settings')->result();
		$out = array();
		foreach ($rows as $row) {
			$value = $row->setting_value;
			if ((int) $row->is_secret === 1 && $value) {
				$value = str_repeat('*', min(8, strlen($value)));
			}
			$out[$row->setting_group][] = array(
				'id' => (int) $row->id,
				'key' => $row->setting_key,
				'value' => (int) $row->is_secret === 1 ? $value : $row->setting_value,
				'raw_editable' => true,
				'is_secret' => (int) $row->is_secret === 1
			);
		}
		return $out;
	}

	public function put($key, $value)
	{
		$exists = $this->db->get_where('settings', array('setting_key' => $key))->row();
		if ($exists) {
			$this->db->where('setting_key', $key)->update('settings', array(
				'setting_value' => $value,
				'updated_at' => now_dt()
			));
		} else {
			$this->db->insert('settings', array(
				'setting_key' => $key,
				'setting_value' => $value,
				'setting_group' => 'general',
				'is_secret' => 0,
				'updated_at' => now_dt()
			));
		}
	}

	public function credentials()
	{
		return array(
			array(
				'role' => 'Promoter / Admin',
				'email' => $this->get('test_admin_email'),
				'password' => $this->get('test_admin_password')
			),
			array(
				'role' => 'Marketing Team Admin',
				'email' => $this->get('test_team_admin_email'),
				'password' => $this->get('test_team_admin_password')
			),
			array(
				'role' => 'Marketing Team User',
				'email' => $this->get('test_team_user_email'),
				'password' => $this->get('test_team_user_password')
			)
		);
	}
}
