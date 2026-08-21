<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('project_model', 'inventory_model', 'booking_model', 'registration_model', 'request_model'));
	}

	public function index()
	{
		$allowed = $this->allowed_project_ids();
		$projects = $this->project_model->list_filtered(array(), 5, 0, $allowed);
		$stats = $this->inventory_model->stats($allowed);
		$project_count = $allowed === null
			? (int) $this->db->where('deleted_at IS NULL', null, false)->count_all_results('projects')
			: count($allowed);

		$pending_requests = $this->db->where('status', 'pending');
		if (!$this->is_admin()) {
			$pending_requests->where('company_id', $this->company_id());
		}
		$pending = (int) $pending_requests->count_all_results('block_requests');

		$this->api_response->ok(array(
			'greeting' => 'Welcome, ' . ($this->auth_user->company_id ? $this->user_model->public_user($this->auth_user)['company_name'] : 'Admin') . ' 👋',
			'role' => $this->auth_user->role,
			'total_projects' => $project_count,
			'inventory' => $stats,
			'pending_requests' => $pending,
			'recent_projects' => $projects[0]
		));
	}

	public function charts()
	{
		$allowed = $this->allowed_project_ids();
		$status = $this->inventory_model->stats($allowed);

		$this->db->select('p.name, u.status, COUNT(*) as cnt', false)
			->from('inventory_units u')
			->join('projects p', 'p.id = u.project_id')
			->where('u.deleted_at IS NULL', null, false);
		if ($allowed !== null) {
			if (empty($allowed)) {
				$this->api_response->ok(array('status_pie' => array(), 'project_breakdown' => array(), 'monthly' => array()));
			}
			$this->db->where_in('u.project_id', $allowed);
		}
		$rows = $this->db->group_by(array('p.name', 'u.status'))->get()->result();
		$breakdown = array();
		foreach ($rows as $row) {
			if (!isset($breakdown[$row->name])) {
				$breakdown[$row->name] = array('name' => $row->name, 'available' => 0, 'blocked' => 0, 'booked' => 0, 'registered' => 0, 'on_hold' => 0);
			}
			$breakdown[$row->name][$row->status] = (int) $row->cnt;
		}

		$monthly = $this->db->query("
			SELECT DATE_FORMAT(booking_date, '%Y-%m') as ym, COUNT(*) as bookings, COALESCE(SUM(amount),0) as value
			FROM bookings WHERE deleted_at IS NULL GROUP BY ym ORDER BY ym DESC LIMIT 6
		")->result();

		$this->api_response->ok(array(
			'status_pie' => array(
				array('name' => 'Available', 'value' => $status['available']),
				array('name' => 'Blocked', 'value' => $status['blocked']),
				array('name' => 'Booked', 'value' => $status['booked']),
				array('name' => 'Registered', 'value' => $status['registered']),
				array('name' => 'On Hold', 'value' => $status['on_hold'])
			),
			'project_breakdown' => array_values($breakdown),
			'monthly' => array_reverse($monthly)
		));
	}
}
