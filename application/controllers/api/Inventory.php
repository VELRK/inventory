<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('inventory_model');
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			list($page, $limit, $offset) = pagination_params(12);
			$filters = array(
				'q' => request_value('q'),
				'status' => request_value('status'),
				'project_id' => request_value('project_id')
			);
			list($items, $total) = $this->inventory_model->list_filtered($filters, $limit, $offset, $this->allowed_project_ids());
			$stats = $this->inventory_model->stats($this->allowed_project_ids(), $filters['project_id']);
			$this->api_response->paginated($items, $total, $page, $limit, array('stats' => $stats));
		}
		if ($method === 'POST') {
			$this->require_permission('inventory.create');
			$data = $this->_payload(null);
			if ($data['unit_no'] === '' || !$data['project_id']) {
				$this->api_response->validation(array('unit_no' => 'Unit number is required.', 'project_id' => 'Project is required.'));
			}
			$exists = $this->db->get_where('inventory_units', array('project_id' => $data['project_id'], 'unit_no' => $data['unit_no'], 'deleted_at' => null))->row();
			if ($exists) {
				$this->api_response->error('DUPLICATE', 'This unit number already exists in the project.', 409);
			}
			$data['created_at'] = now_dt();
			$this->db->insert('inventory_units', $data);
			$id = $this->db->insert_id();
			$this->log_activity('inventory.create', 'Created unit ' . $data['unit_no'], 'inventory_units', $id);
			$this->api_response->ok($this->inventory_model->decorate($this->inventory_model->find($id)), 'Unit created.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$unit = $this->inventory_model->find($id);
		if (!$unit) {
			$this->api_response->error('NOT_FOUND', 'Unit not found.', 404);
		}
		$allowed = $this->allowed_project_ids();
		if ($allowed !== null && !in_array((int) $unit->project_id, $allowed, true)) {
			$this->api_response->error('FORBIDDEN', 'You cannot access this unit.', 403);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->inventory_model->decorate($unit));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$this->require_permission('inventory.edit');
			$old = $unit->status;
			$data = $this->_payload($unit);
			// Team admin cannot move a unit to a project outside their assignment.
			if ($this->is_team_admin()) {
				$allowed = $this->allowed_project_ids();
				$new_project = (int) $data['project_id'];
				if ($allowed !== null && !in_array($new_project, $allowed, true)) {
					$this->api_response->error('FORBIDDEN', 'You cannot move this unit to an unassigned project.', 403);
				}
			}
			$data['updated_at'] = now_dt();
			$this->db->where('id', (int) $id)->update('inventory_units', $data);
			$fresh = $this->inventory_model->find($id);
			$newStatus = $data['status'];
			if ($old !== $newStatus) {
				$label = status_label($newStatus);
				if ($label === '') {
					$label = (string) $newStatus;
				}
				$this->log_activity('inventory.status', $fresh->unit_no . ' status changed from ' . status_label($old) . ' to ' . $label, 'inventory_units', $id);
				$this->mailer->dispatch_event('inventory.status', array(
					'unit_no' => $fresh->unit_no,
					'status' => $label,
					'status_label' => $label,
					'project_id' => (int) $fresh->project_id,
					'actor_user_id' => $this->user_id()
				));
			}
			$this->api_response->ok($this->inventory_model->decorate($fresh), 'Unit updated.');
		}
		if ($method === 'DELETE') {
			$this->require_permission('inventory.delete');
			$this->db->where('id', (int) $id)->update('inventory_units', array('deleted_at' => now_dt()));
			$this->log_activity('inventory.archive', 'Archived unit ' . $unit->unit_no, 'inventory_units', $id);
			$this->api_response->ok(array(), 'Unit archived.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function stats()
	{
		$project_id = request_value('project_id');
		$allowed = $this->allowed_project_ids();
		$global = $this->inventory_model->stats($allowed, $project_id);

		$this->db->select('p.id, p.name, p.city');
		$this->db->from('projects p')->where('p.deleted_at IS NULL', null, false);
		if ($allowed !== null) {
			if (empty($allowed)) {
				$this->api_response->ok(array('stats' => $global, 'projects' => array()));
			}
			$this->db->where_in('p.id', $allowed);
		}
		$projects = $this->db->get()->result();
		$list = array();
		foreach ($projects as $p) {
			$s = $this->inventory_model->stats(null, $p->id);
			$list[] = array(
				'id' => (int) $p->id,
				'name' => $p->name,
				'city' => $p->city,
				'counts' => $s
			);
		}
		$this->api_response->ok(array('stats' => $global, 'projects' => $list));
	}

	public function bulk()
	{
		$this->require_permission('inventory.edit');
		$ids = request_value('ids', array());
		$action = request_value('action', 'change_status');
		$status = request_value('status');
		$remarks = request_value('remarks');
		if (!is_array($ids) || empty($ids)) {
			$this->api_response->validation(array('ids' => 'Select at least one unit.'));
		}
		if ($action === 'change_status') {
			$status = normalize_unit_status($status);
			if ($status === null) {
				$this->api_response->validation(array('status' => 'Status must be available, on_hold, booked, or registered.'));
			}
		}
		$project_scope = $this->allowed_project_ids();
		$updated = 0;
		foreach ($ids as $id) {
			$unit = $this->inventory_model->find($id);
			if (!$unit) {
				continue;
			}
			if ($project_scope !== null && !in_array((int) $unit->project_id, $project_scope, true)) {
				continue;
			}
			$data = array('updated_at' => now_dt());
			if ($action === 'change_status') {
				$data['status'] = $status;
			}
			if ($remarks !== null && $remarks !== '') {
				$data['remarks'] = $remarks;
			}
			$this->db->where('id', (int) $id)->update('inventory_units', $data);
			$this->log_activity('inventory.bulk', $unit->unit_no . ' bulk updated to ' . status_label($status), 'inventory_units', $id);
			$updated++;
		}
		$this->api_response->ok(array('updated' => $updated), $updated . ' units updated.');
	}

	private function _payload($unit = null)
	{
		$area = (float) request_value('area_sqft', $unit ? $unit->area_sqft : 0);
		$price = (float) request_value('price', $unit ? $unit->price : 0);
		$pps = request_value('price_per_sqft', $unit ? $unit->price_per_sqft : null);
		if (($pps === null || $pps === '') && $area > 0) {
			$pps = round($price / $area, 2);
		}
		$fallbackStatus = $unit && $unit->status !== '' && $unit->status !== null
			? $unit->status
			: 'available';
		$rawStatus = request_value('status', $fallbackStatus);
		$normalized = normalize_unit_status($rawStatus);
		if ($normalized === null) {
			$this->api_response->validation(array(
				'status' => 'Status must be available, on_hold, booked, or registered.'
			));
		}
		return array(
			'project_id' => (int) request_value('project_id', $unit ? $unit->project_id : 0),
			'unit_no' => trim((string) request_value('unit_no', $unit ? $unit->unit_no : '')),
			'block_phase' => request_value('block_phase', $unit ? $unit->block_phase : null),
			'plot_type' => request_value('plot_type', $unit ? $unit->plot_type : 'Residential Plot'),
			'area_sqft' => $area,
			'facing' => request_value('facing', $unit ? $unit->facing : null),
			'road_width_ft' => request_value('road_width_ft', $unit ? $unit->road_width_ft : null),
			'dimensions' => request_value('dimensions', $unit ? $unit->dimensions : null),
			'price' => $price,
			'price_per_sqft' => (float) $pps,
			'is_premium' => request_value('is_premium', $unit ? $unit->is_premium : 0) ? 1 : 0,
			'is_corner' => request_value('is_corner', $unit ? $unit->is_corner : 0) ? 1 : 0,
			'approval_details' => request_value('approval_details', $unit ? $unit->approval_details : null),
			'remarks' => request_value('remarks', $unit ? $unit->remarks : null),
			'status' => $normalized
		);
	}
}
