<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_model extends CI_Model
{
	public function find($id)
	{
		return $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get('inventory_units')->row();
	}

	public function decorate($unit)
	{
		if (!$unit) {
			return null;
		}
		$project = $this->db->get_where('projects', array('id' => $unit->project_id))->row();
		$images = $this->db->get_where('unit_images', array('unit_id' => $unit->id))->result();
		return array(
			'id' => (int) $unit->id,
			'project_id' => (int) $unit->project_id,
			'project_name' => $project ? $project->name : '',
			'project_city' => $project ? $project->city : '',
			'unit_no' => $unit->unit_no,
			'block_phase' => $unit->block_phase,
			'plot_type' => $unit->plot_type,
			'area_sqft' => (float) $unit->area_sqft,
			'facing' => $unit->facing,
			'road_width_ft' => $unit->road_width_ft !== null ? (float) $unit->road_width_ft : null,
			'dimensions' => $unit->dimensions,
			'price' => (float) $unit->price,
			'price_formatted' => format_inr($unit->price),
			'price_per_sqft' => (float) $unit->price_per_sqft,
			'is_premium' => (int) $unit->is_premium === 1,
			'is_corner' => (int) $unit->is_corner === 1,
			'approval_details' => $unit->approval_details,
			'remarks' => $unit->remarks,
			'status' => $unit->status,
			'status_label' => status_label($unit->status),
			'images' => $images,
			'created_at' => $unit->created_at
		);
	}

	public function list_filtered($filters, $limit, $offset, $allowed_ids = null)
	{
		$this->db->from('inventory_units u')->where('u.deleted_at IS NULL', null, false);
		if ($allowed_ids !== null) {
			if (empty($allowed_ids)) {
				return array(array(), 0);
			}
			$this->db->where_in('u.project_id', $allowed_ids);
		}
		if (!empty($filters['project_id'])) {
			$this->db->where('u.project_id', (int) $filters['project_id']);
		}
		if (!empty($filters['status'])) {
			$this->db->where('u.status', $filters['status']);
		}
		if (!empty($filters['q'])) {
			$this->db->like('u.unit_no', $filters['q']);
		}
		$total = $this->db->count_all_results('', false);
		$rows = $this->db->order_by('u.id', 'DESC')->limit($limit, $offset)->get()->result();
		$items = array();
		foreach ($rows as $row) {
			$items[] = $this->decorate($row);
		}
		return array($items, $total);
	}

	public function stats($allowed_ids = null, $project_id = null)
	{
		$this->db->select('status, COUNT(*) as cnt', false)->from('inventory_units')->where('deleted_at IS NULL', null, false);
		if ($allowed_ids !== null) {
			if (empty($allowed_ids)) {
				return array('available' => 0, 'on_hold' => 0, 'booked' => 0, 'registered' => 0, 'total' => 0);
			}
			$this->db->where_in('project_id', $allowed_ids);
		}
		if ($project_id) {
			$this->db->where('project_id', (int) $project_id);
		}
		$rows = $this->db->group_by('status')->get()->result();
		$out = array('available' => 0, 'on_hold' => 0, 'booked' => 0, 'registered' => 0, 'total' => 0);
		foreach ($rows as $row) {
			$out[$row->status] = (int) $row->cnt;
			$out['total'] += (int) $row->cnt;
		}
		return $out;
	}

	public function set_status($id, $status, $notify = true)
	{
		$unit = $this->find($id);
		if (!$unit) {
			return false;
		}
		$old = $unit->status;
		$status = (string) $status;
		$this->db->where('id', (int) $id)->update('inventory_units', array(
			'status' => $status,
			'updated_at' => now_dt()
		));
		// Doc rule: non-Available → Available notifies everyone with project access.
		if ($notify && $status === 'available' && $old !== 'available') {
			$this->notify_available($unit, $old);
		}
		return true;
	}

	public function notify_available($unit, $previous_status = '')
	{
		if (!$unit) {
			return false;
		}
		$ci =& get_instance();
		if (empty($ci->mailer)) {
			$ci->load->library('mailer');
		}
		$project = $this->db->get_where('projects', array('id' => $unit->project_id))->row();
		$actor = !empty($ci->auth_user) ? $ci->auth_user : null;
		$prev = $previous_status !== '' ? status_label($previous_status) : '';
		return $ci->mailer->dispatch_event('inventory.available', array(
			'projectName' => $project ? $project->name : '',
			'siteNumber' => $unit->unit_no,
			'unit_no' => $unit->unit_no,
			'previousStatus' => $prev !== '' ? $prev : 'Previous',
			'currentStatus' => 'Available',
			'status' => 'Available',
			'superAdminName' => $actor ? $actor->name : 'Super Admin',
			'updatedDate' => date('d M Y, h:i A'),
			'link' => frontend_app_url('/inventory?project_id=' . (int) $unit->project_id),
			'project_id' => (int) $unit->project_id,
			'actor_user_id' => $actor ? (int) $actor->id : 0
		));
	}
}
