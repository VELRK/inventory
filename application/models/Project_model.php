<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Project_model extends CI_Model
{
	public function find($id)
	{
		return $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get('projects')->row();
	}

	public function with_stats($project)
	{
		if (!$project) {
			return null;
		}
		$stats = $this->db->select('status, COUNT(*) as cnt', false)
			->from('inventory_units')
			->where('project_id', $project->id)
			->where('deleted_at IS NULL', null, false)
			->group_by('status')
			->get()->result();
		$counts = array('available' => 0, 'on_hold' => 0, 'blocked' => 0, 'booked' => 0, 'registered' => 0, 'total' => 0);
		foreach ($stats as $s) {
			$counts[$s->status] = (int) $s->cnt;
			$counts['total'] += (int) $s->cnt;
		}
		$images = $this->db->get_where('project_images', array('project_id' => $project->id))->result();
		return array(
			'id' => (int) $project->id,
			'name' => $project->name,
			'location' => $project->location,
			'city' => $project->city,
			'project_type' => $project->project_type,
			'description' => $project->description,
			'approval_details' => $project->approval_details,
			'contact_name' => $project->contact_name,
			'contact_phone' => $project->contact_phone,
			'contact_email' => $project->contact_email,
			'cover_image' => $project->cover_image,
			'cover_image_url' => media_url($project->cover_image),
			'status' => $project->status,
			'counts' => $counts,
			'images' => $images,
			'created_at' => $project->created_at
		);
	}

	public function list_filtered($filters, $limit, $offset, $allowed_ids = null)
	{
		$this->db->from('projects')->where('deleted_at IS NULL', null, false);
		if ($allowed_ids !== null) {
			if (empty($allowed_ids)) {
				return array(array(), 0);
			}
			$this->db->where_in('id', $allowed_ids);
		}
		if (!empty($filters['q'])) {
			$q = $this->db->escape_like_str($filters['q']);
			$this->db->group_start()->like('name', $q)->or_like('city', $q)->or_like('location', $q)->group_end();
		}
		if (!empty($filters['status'])) {
			$this->db->where('status', $filters['status']);
		}
		$total = $this->db->count_all_results('', false);
		$rows = $this->db->order_by('id', 'DESC')->limit($limit, $offset)->get()->result();
		$items = array();
		foreach ($rows as $row) {
			$items[] = $this->with_stats($row);
		}
		return array($items, $total);
	}
}
