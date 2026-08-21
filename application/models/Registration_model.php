<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registration_model extends CI_Model
{
	public function find($id)
	{
		return $this->db->where('id', (int) $id)->where('deleted_at IS NULL', null, false)->get('registrations')->row();
	}

	public function decorate($row)
	{
		if (!$row) {
			return null;
		}
		$unit = $this->db->get_where('inventory_units', array('id' => $row->unit_id))->row();
		$project = $this->db->get_where('projects', array('id' => $row->project_id))->row();
		$company = $row->company_id ? $this->db->get_where('marketing_companies', array('id' => $row->company_id))->row() : null;
		return array(
			'id' => (int) $row->id,
			'unit_id' => (int) $row->unit_id,
			'unit_no' => $unit ? $unit->unit_no : '',
			'project_id' => (int) $row->project_id,
			'project_name' => $project ? $project->name : '',
			'project_city' => $project ? $project->city : '',
			'company_id' => $row->company_id ? (int) $row->company_id : null,
			'company_name' => $company ? $company->name : '',
			'customer_name' => $row->customer_name,
			'customer_phone' => $row->customer_phone,
			'customer_email' => $row->customer_email,
			'initials' => initials_of($row->customer_name),
			'amount' => (float) $row->amount,
			'amount_formatted' => format_inr($row->amount),
			'registration_date' => $row->registration_date,
			'status' => $row->status,
			'status_label' => status_label($row->status),
			'payment_status' => $row->payment_status,
			'notes' => $row->notes,
			'created_at' => $row->created_at
		);
	}

	public function list_filtered($filters, $limit, $offset)
	{
		$this->_apply($filters);
		$total = $this->db->count_all_results('', false);
		$rows = $this->db->order_by('r.id', 'DESC')->limit($limit, $offset)->get()->result();
		$items = array();
		foreach ($rows as $row) {
			$items[] = $this->decorate($row);
		}
		$sum = $this->_sum($filters);
		return array($items, $total, $sum);
	}

	private function _apply($filters)
	{
		$this->db->from('registrations r')->where('r.deleted_at IS NULL', null, false);
		if (!empty($filters['company_id'])) {
			$this->db->where('r.company_id', (int) $filters['company_id']);
		}
		if (!empty($filters['project_id'])) {
			$this->db->where('r.project_id', (int) $filters['project_id']);
		}
		if (!empty($filters['status'])) {
			$this->db->where('r.status', $filters['status']);
		}
		if (!empty($filters['payment_status'])) {
			$this->db->where('r.payment_status', $filters['payment_status']);
		}
		if (!empty($filters['from'])) {
			$this->db->where('r.registration_date >=', $filters['from']);
		}
		if (!empty($filters['to'])) {
			$this->db->where('r.registration_date <=', $filters['to']);
		}
		if (!empty($filters['q'])) {
			$this->db->like('r.customer_name', $filters['q']);
		}
	}

	private function _sum($filters)
	{
		$this->db->select_sum('amount');
		$this->_apply($filters);
		$row = $this->db->get()->row();
		return $row && $row->amount ? (float) $row->amount : 0;
	}
}
