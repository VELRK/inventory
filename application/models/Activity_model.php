<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_model extends CI_Model
{
	public function add($data)
	{
		$this->db->insert('activity_logs', $data);
	}

	public function list_filtered($filters, $limit, $offset)
	{
		$this->db->from('activity_logs');
		if (!empty($filters['q'])) {
			$this->db->like('description', $filters['q']);
		}
		if (!empty($filters['from'])) {
			$this->db->where('created_at >=', $filters['from'] . ' 00:00:00');
		}
		if (!empty($filters['to'])) {
			$this->db->where('created_at <=', $filters['to'] . ' 23:59:59');
		}
		if (!empty($filters['company_id'])) {
			$this->db->where('company_id', (int) $filters['company_id']);
		}
		$total = $this->db->count_all_results('', false);
		$rows = $this->db->order_by('id', 'DESC')->limit($limit, $offset)->get()->result();
		foreach ($rows as $row) {
			$user = $row->user_id ? $this->db->get_where('users', array('id' => $row->user_id))->row() : null;
			$row->user_name = $user ? $user->name : 'System';
		}
		return array($rows, $total);
	}
}
