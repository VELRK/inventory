<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Requests extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('request_model', 'inventory_model'));
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			list($page, $limit, $offset) = pagination_params();
			$filters = array(
				'q' => request_value('q'),
				'status' => request_value('status'),
				'company_id' => request_value('company_id')
			);
			if (!$this->is_admin()) {
				$filters['company_id'] = $this->company_id();
				if ($this->auth_user->role === 'marketing_team_user') {
					$filters['requested_by'] = $this->user_id();
				}
			}
			list($items, $total) = $this->request_model->list_filtered($filters, $limit, $offset);
			$this->api_response->paginated($items, $total, $page, $limit);
		}
		if ($method === 'POST') {
			$unit_id = (int) request_value('unit_id');
			$unit = $this->inventory_model->find($unit_id);
			if (!$unit) {
				$this->api_response->error('NOT_FOUND', 'Unit not found.', 404);
			}
			$allowed = $this->allowed_project_ids();
			if ($allowed !== null && !in_array((int) $unit->project_id, $allowed, true)) {
				$this->api_response->error('FORBIDDEN', 'This unit is not assigned to you.', 403);
			}
			if ($unit->status !== 'available') {
				$this->api_response->error('UNIT_NOT_AVAILABLE', 'Only available units can be blocked.', 409);
			}
			$name = trim((string) request_value('customer_name'));
			$phone = trim((string) request_value('customer_phone'));
			if ($name === '' || $phone === '') {
				$this->api_response->validation(array(
					'customer_name' => 'Customer name is required.',
					'customer_phone' => 'Customer phone is required.'
				));
			}
			if (!$this->company_id()) {
				$this->api_response->error('FORBIDDEN', 'Only marketing teams can submit block requests.', 403);
			}
			$this->db->insert('block_requests', array(
				'unit_id' => $unit_id,
				'company_id' => $this->company_id(),
				'requested_by' => $this->user_id(),
				'customer_name' => $name,
				'customer_phone' => $phone,
				'customer_email' => request_value('customer_email'),
				'expected_booking_date' => request_value('expected_booking_date'),
				'remarks' => request_value('remarks'),
				'status' => 'pending',
				'created_at' => now_dt()
			));
			$id = $this->db->insert_id();
			$this->inventory_model->set_status($unit_id, 'on_hold');
			$admins = $this->db->where('role', 'promoter_admin')->where('status', 'active')->get('users')->result();
			$company = $this->db->get_where('marketing_companies', array('id' => $this->company_id()))->row();
			$project = $this->db->get_where('projects', array('id' => $unit->project_id))->row();
			foreach ($admins as $admin) {
				$this->mailer->notify_event('request.submitted', $admin->email, array(
					'unit_no' => $unit->unit_no,
					'project' => $project ? $project->name : '',
					'company' => $company ? $company->name : ''
				));
				$this->notify($admin->id, 'New block request', $unit->unit_no . ' requested by ' . ($company ? $company->name : 'team'));
			}
			$this->log_activity('request.create', 'Block request submitted for ' . $unit->unit_no, 'block_requests', $id);
			$this->api_response->ok($this->request_model->decorate($this->request_model->find($id)), 'Block request submitted.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$row = $this->request_model->find($id);
		if (!$row) {
			$this->api_response->error('NOT_FOUND', 'Request not found.', 404);
		}
		if (!$this->is_admin() && (int) $row->company_id !== $this->company_id()) {
			$this->api_response->error('FORBIDDEN', 'You cannot view this request.', 403);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->request_model->decorate($row));
		}
		if ($method === 'PUT' || $method === 'POST') {
			if (!$this->is_admin() && (int) $row->requested_by !== $this->user_id() && !$this->is_team_admin()) {
				$this->api_response->error('FORBIDDEN', 'You cannot edit this request.', 403);
			}
			if ($row->status !== 'pending' && !$this->is_admin()) {
				$this->api_response->error('INVALID_STATE', 'Only pending requests can be edited.', 409);
			}
			$this->db->where('id', (int) $id)->update('block_requests', array(
				'customer_name' => request_value('customer_name', $row->customer_name),
				'customer_phone' => request_value('customer_phone', $row->customer_phone),
				'customer_email' => request_value('customer_email', $row->customer_email),
				'expected_booking_date' => request_value('expected_booking_date', $row->expected_booking_date),
				'remarks' => request_value('remarks', $row->remarks),
				'updated_at' => now_dt()
			));
			$this->log_activity('request.update', 'Updated block request #' . $id, 'block_requests', $id);
			$this->api_response->ok($this->request_model->decorate($this->request_model->find($id)), 'Request updated.');
		}
		if ($method === 'DELETE') {
			if (!$this->is_admin() && (int) $row->requested_by !== $this->user_id() && !$this->is_team_admin()) {
				$this->api_response->error('FORBIDDEN', 'You cannot delete this request.', 403);
			}
			if ($row->status !== 'pending' && !$this->is_admin()) {
				$this->api_response->error('INVALID_STATE', 'Only pending requests can be deleted.', 409);
			}
			$this->db->where('id', (int) $id)->delete('block_requests');
			$unit = $this->inventory_model->find($row->unit_id);
			if ($row->status === 'pending' && $unit && $unit->status === 'on_hold') {
				$this->inventory_model->set_status($row->unit_id, 'available');
			}
			$this->log_activity('request.delete', 'Deleted block request #' . $id, 'block_requests', $id);
			$this->api_response->ok(array(), 'Request deleted.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function review($id)
	{
		$this->require_roles(array('promoter_admin'));
		$row = $this->request_model->find($id);
		if (!$row) {
			$this->api_response->error('NOT_FOUND', 'Request not found.', 404);
		}
		if ($row->status !== 'pending') {
			$this->api_response->error('INVALID_STATE', 'This request was already reviewed.', 409);
		}
		$decision = request_value('decision');
		if (!in_array($decision, array('approved', 'rejected'), true)) {
			$this->api_response->validation(array('decision' => 'Decision must be approved or rejected.'));
		}
		$this->db->where('id', (int) $id)->update('block_requests', array(
			'status' => $decision,
			'reviewed_by' => $this->user_id(),
			'reviewed_at' => now_dt(),
			'review_notes' => request_value('review_notes'),
			'updated_at' => now_dt()
		));
		$unit = $this->inventory_model->find($row->unit_id);
		if ($decision === 'approved') {
			$this->inventory_model->set_status($row->unit_id, 'blocked');
		} else {
			if ($unit && $unit->status === 'on_hold') {
				$this->inventory_model->set_status($row->unit_id, 'available');
			}
		}
		$requester = $this->user_model->find($row->requested_by);
		if ($requester) {
			$event = $decision === 'approved' ? 'request.approved' : 'request.rejected';
			$this->mailer->notify_event($event, $requester->email, array(
				'unit_no' => $unit ? $unit->unit_no : '',
				'notes' => (string) request_value('review_notes')
			));
			$this->notify($requester->id, 'Block request ' . $decision, 'Unit ' . ($unit ? $unit->unit_no : '') . ' was ' . $decision);
		}
		$this->log_activity('request.review', 'Request #' . $id . ' ' . $decision, 'block_requests', $id);
		$this->api_response->ok($this->request_model->decorate($this->request_model->find($id)), 'Request ' . $decision . '.');
	}
}
