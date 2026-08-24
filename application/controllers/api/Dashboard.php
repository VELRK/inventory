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
		$this->inventory_model->sync_status_from_transactions();
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
		$this->inventory_model->sync_status_from_transactions();
		$status = $this->inventory_model->stats($allowed);

		if ($allowed !== null && empty($allowed)) {
			$this->api_response->ok(array('status_pie' => array(), 'project_breakdown' => array(), 'monthly' => array()));
		}

		$this->db->select('id, name')->from('projects')->where('deleted_at IS NULL', null, false);
		if ($allowed !== null) {
			$this->db->where_in('id', $allowed);
		}
		$projects = $this->db->order_by('name', 'ASC')->get()->result();
		$breakdown = array();
		foreach ($projects as $p) {
			$s = $this->inventory_model->stats(null, $p->id);
			$breakdown[] = array(
				'name' => $p->name,
				'available' => $s['available'],
				'on_hold' => $s['on_hold'],
				'booked' => $s['booked'],
				'registered' => $s['registered']
			);
		}

		$this->db->select("DATE_FORMAT(booking_date, '%Y-%m') as ym, COUNT(*) as bookings, COALESCE(SUM(amount),0) as value", false)
			->from('bookings')
			->where('deleted_at IS NULL', null, false)
			->where('status <>', 'cancelled');
		if ($allowed !== null) {
			$this->db->where_in('project_id', $allowed);
		}
		$monthly = $this->db->group_by('ym')->order_by('ym', 'DESC')->limit(6)->get()->result();

		$this->api_response->ok(array(
			'status_pie' => array(
				array('name' => 'Available', 'value' => $status['available']),
				array('name' => 'On Hold', 'value' => $status['on_hold']),
				array('name' => 'Booked', 'value' => $status['booked']),
				array('name' => 'Registered', 'value' => $status['registered'])
			),
			'project_breakdown' => $breakdown,
			'monthly' => array_reverse($monthly)
		));
	}
}
