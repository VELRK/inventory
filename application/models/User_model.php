<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
	public function find_by_email($email)
	{
		return $this->db->where('email', $email)
			->where('deleted_at IS NULL', null, false)
			->get('users')->row();
	}

	public function find($id)
	{
		return $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get('users')->row();
	}

	public function public_user($user)
	{
		if (!$user) {
			return null;
		}
		$company = null;
		if ($user->company_id) {
			$company = $this->db->get_where('marketing_companies', array('id' => $user->company_id))->row();
		}
		$projects = $this->db->select('p.id, p.name, p.city')
			->from('user_project_assignments a')
			->join('projects p', 'p.id = a.project_id')
			->where('a.user_id', $user->id)
			->get()->result();

		return array(
			'id' => (int) $user->id,
			'name' => $user->name,
			'email' => $user->email,
			'phone' => $user->phone,
			'avatar' => isset($user->avatar) ? $user->avatar : null,
			'avatar_url' => media_url(isset($user->avatar) ? $user->avatar : null),
			'role' => $user->role,
			'status' => $user->status,
			'company_id' => $user->company_id ? (int) $user->company_id : null,
			'company_name' => $company ? $company->name : 'Promoter',
			'initials' => initials_of($user->name),
			'projects' => $projects,
			'permissions' => $this->_permissions_for($user->role)
		);
	}

	private function _permissions_for($role)
	{
		$this->load->model('role_permission_model');
		return $this->role_permission_model->permissions_for_role($role);
	}

	public function list_filtered($filters, $limit, $offset)
	{
		$this->_apply($filters);
		$total = (int) $this->db->count_all_results('', false);
		$query = $this->db->order_by('users.id', 'DESC')->limit($limit, $offset)->get();
		$rows = ($query && is_object($query)) ? $query->result() : array();
		$items = array();
		foreach ($rows as $row) {
			$items[] = $this->public_user($row);
		}
		return array($items, $total);
	}

	private function _apply($filters)
	{
		$this->db->from('users');
		$this->db->where('users.deleted_at IS NULL', null, false);
		if (!empty($filters['company_id'])) {
			$this->db->where('users.company_id', (int) $filters['company_id']);
		}
		if (!empty($filters['role'])) {
			$this->db->where('users.role', $filters['role']);
		}
		if (!empty($filters['q'])) {
			$q = $this->db->escape_like_str($filters['q']);
			$this->db->group_start()
				->like('users.name', $q)
				->or_like('users.email', $q)
				->group_end();
		}
	}

	public function create($data)
	{
		// Avoid inserting empty strings into nullable FKs
		if (array_key_exists('company_id', $data) && ($data['company_id'] === '' || $data['company_id'] === 0)) {
			$data['company_id'] = null;
		}
		$ok = $this->db->insert('users', $data);
		if (!$ok) {
			return 0;
		}
		return (int) $this->db->insert_id();
	}

	public function update_user($id, $data)
	{
		$this->db->where('id', (int) $id)->update('users', $data);
	}

	public function set_projects($user_id, $project_ids)
	{
		$user_id = (int) $user_id;
		$this->db->where('user_id', $user_id)->delete('user_project_assignments');
		$project_ids = array_values(array_unique(array_filter(array_map('intval', (array) $project_ids))));
		foreach ($project_ids as $pid) {
			if ($pid < 1) {
				continue;
			}
			$this->db->insert('user_project_assignments', array(
				'user_id' => $user_id,
				'project_id' => $pid,
				'created_at' => now_dt()
			));
		}

		// Keep company pool in sync so GET /projects works for the whole team.
		$user = $this->find($user_id);
		if ($user && $user->company_id) {
			$company_id = (int) $user->company_id;
			foreach ($project_ids as $pid) {
				$exists = $this->db->get_where('company_project_assignments', array(
					'company_id' => $company_id,
					'project_id' => $pid
				))->row();
				if (!$exists) {
					$this->db->insert('company_project_assignments', array(
						'company_id' => $company_id,
						'project_id' => $pid,
						'created_at' => now_dt()
					));
				}
			}
		}
	}
}
