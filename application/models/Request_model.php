<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Request_model extends CI_Model
{
	public function find($id)
	{
		return $this->db->get_where('block_requests', array('id' => (int) $id))->row();
	}

	public function decorate($row)
	{
		if (!$row) {
			return null;
		}
		$unit = $this->db->get_where('inventory_units', array('id' => $row->unit_id))->row();
		$project = $unit ? $this->db->get_where('projects', array('id' => $unit->project_id))->row() : null;
		$company = $this->db->get_where('marketing_companies', array('id' => $row->company_id))->row();
		$requester = $this->db->get_where('users', array('id' => $row->requested_by))->row();
		return array(
			'id' => (int) $row->id,
			'unit_id' => (int) $row->unit_id,
			'unit_no' => $unit ? $unit->unit_no : '',
			'project_name' => $project ? $project->name : '',
			'project_city' => $project ? $project->city : '',
			'area_sqft' => $unit ? (float) $unit->area_sqft : 0,
			'price' => $unit ? (float) $unit->price : 0,
			'requested_by' => (int) $row->requested_by,
			'company_id' => (int) $row->company_id,
			'company_name' => $company ? $company->name : '',
			'requested_by_name' => $requester ? $requester->name : '',
			'customer_name' => $row->customer_name,
			'customer_phone' => $row->customer_phone,
			'customer_email' => $row->customer_email,
			'expected_booking_date' => $row->expected_booking_date,
			'remarks' => $row->remarks,
			'status' => $row->status,
			'status_label' => status_label($row->status),
			'review_notes' => $row->review_notes,
			'reviewed_at' => $row->reviewed_at,
			'created_at' => $row->created_at
		);
	}

	public function list_filtered($filters, $limit, $offset)
	{
		$this->db->from('block_requests r');
		if (!empty($filters['company_id'])) {
			$this->db->where('r.company_id', (int) $filters['company_id']);
		}
		if (!empty($filters['requested_by'])) {
			$this->db->where('r.requested_by', (int) $filters['requested_by']);
		}
		if (!empty($filters['status'])) {
			$this->db->where('r.status', $filters['status']);
		}
		if (!empty($filters['q'])) {
			$this->db->like('r.customer_name', $filters['q']);
		}
		$total = $this->db->count_all_results('', false);
		$rows = $this->db->order_by('r.id', 'DESC')->limit($limit, $offset)->get()->result();
		$items = array();
		foreach ($rows as $row) {
			$items[] = $this->decorate($row);
		}
		return array($items, $total);
	}
}
