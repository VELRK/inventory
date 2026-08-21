<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Company_model extends CI_Model
{
	public function find($id)
	{
		return $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get('marketing_companies')->row();
	}

	public function decorate($company)
	{
		if (!$company) {
			return null;
		}
		$users = (int) $this->db->where('company_id', $company->id)->where('deleted_at IS NULL', null, false)->count_all_results('users');
		$projects = (int) $this->db->where('company_id', $company->id)->count_all_results('company_project_assignments');
		$assigned = $this->db->select('p.id, p.name, p.city')
			->from('company_project_assignments a')
			->join('projects p', 'p.id = a.project_id')
			->where('a.company_id', $company->id)
			->get()->result();
		return array(
			'id' => (int) $company->id,
			'name' => $company->name,
			'email' => $company->email,
			'phone' => $company->phone,
			'address' => $company->address,
			'city' => $company->city,
			'status' => $company->status,
			'permissions' => $company->permissions ? json_decode($company->permissions, true) : array(),
			'user_count' => $users,
			'project_count' => $projects,
			'projects' => $assigned,
			'project_ids' => array_map(function ($p) { return (int) $p->id; }, $assigned),
			'created_at' => $company->created_at,
			'updated_at' => $company->updated_at
		);
	}

	public function list_filtered($filters, $limit, $offset)
	{
		$this->db->from('marketing_companies')->where('deleted_at IS NULL', null, false);
		if (!empty($filters['q'])) {
			$q = $this->db->escape_like_str($filters['q']);
			$this->db->group_start()->like('name', $q)->or_like('email', $q)->group_end();
		}
		if (!empty($filters['status'])) {
			$this->db->where('status', $filters['status']);
		}
		$total = $this->db->count_all_results('', false);
		$rows = $this->db->order_by('id', 'DESC')->limit($limit, $offset)->get()->result();
		$items = array();
		foreach ($rows as $row) {
			$items[] = $this->decorate($row);
		}
		return array($items, $total);
	}

	public function set_projects($company_id, $project_ids)
	{
		$this->db->where('company_id', (int) $company_id)->delete('company_project_assignments');
		foreach ((array) $project_ids as $pid) {
			$this->db->insert('company_project_assignments', array(
				'company_id' => (int) $company_id,
				'project_id' => (int) $pid,
				'created_at' => now_dt()
			));
		}
	}
}
