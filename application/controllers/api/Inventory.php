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
			$this->inventory_model->sync_status_from_transactions();
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
			// New units cannot be created as booked/registered — use Bookings / Registrations.
			if (in_array($data['status'], array('booked', 'registered'), true)) {
				$data['status'] = 'available';
			}
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
			$newStatus = $data['status'];
			// Changing to booked/registered must store a row in bookings/registrations.
			if ($newStatus !== $old && in_array($newStatus, array('booked', 'registered'), true)) {
				$name = trim((string) request_value('customer_name'));
				if ($name === '') {
					$this->api_response->validation(array(
						'customer_name' => 'Customer name is required when status is ' . $newStatus . '.'
					));
				}
				if ($newStatus === 'booked') {
					if (!in_array($old, array('available', 'on_hold', 'booked'), true)) {
						$this->api_response->error('UNIT_NOT_BOOKABLE', 'Only available or on-hold units can be booked.', 409);
					}
					$company_id = request_value('company_id');
					if ($this->is_team_admin()) {
						$company_id = $this->company_id();
					}
					$this->db->insert('bookings', array(
						'unit_id' => (int) $id,
						'project_id' => $unit->project_id,
						'company_id' => $company_id ?: null,
						'customer_name' => $name,
						'customer_phone' => request_value('customer_phone'),
						'customer_email' => request_value('customer_email'),
						'amount' => (float) request_value('amount', $data['price']),
						'booking_date' => request_value('booking_date', date('Y-m-d')),
						'status' => 'confirmed',
						'payment_status' => request_value('payment_status', 'partial'),
						'notes' => request_value('notes', $data['remarks']),
						'created_by' => $this->user_id(),
						'created_at' => now_dt()
					));
					if ($this->db->affected_rows() < 1) {
						$this->api_response->error('DB_ERROR', 'Failed to save booking for this unit.', 500);
					}
				}
				if ($newStatus === 'registered') {
					if (!in_array($old, array('booked', 'registered', 'available', 'on_hold'), true)) {
						$this->api_response->error('UNIT_NOT_REGISTERABLE', 'Cannot register this unit.', 409);
					}
					// Ensure a booking exists when jumping straight to registered.
					if ($old !== 'booked' && $old !== 'registered') {
						$company_id = request_value('company_id');
						if ($this->is_team_admin()) {
							$company_id = $this->company_id();
						}
						$this->db->insert('bookings', array(
							'unit_id' => (int) $id,
							'project_id' => $unit->project_id,
							'company_id' => $company_id ?: null,
							'customer_name' => $name,
							'customer_phone' => request_value('customer_phone'),
							'customer_email' => request_value('customer_email'),
							'amount' => (float) request_value('amount', $data['price']),
							'booking_date' => request_value('booking_date', date('Y-m-d')),
							'status' => 'confirmed',
							'payment_status' => request_value('payment_status', 'paid'),
							'notes' => request_value('notes', $data['remarks']),
							'created_by' => $this->user_id(),
							'created_at' => now_dt()
						));
					}
					$booking = $this->db->where('unit_id', (int) $id)
						->where('deleted_at IS NULL', null, false)
						->where('status <>', 'cancelled')
						->order_by('id', 'DESC')
						->get('bookings')->row();
					$company_id = request_value('company_id');
					if ($this->is_team_admin()) {
						$company_id = $this->company_id();
					}
					$this->db->insert('registrations', array(
						'unit_id' => (int) $id,
						'project_id' => $unit->project_id,
						'company_id' => $company_id ?: null,
						'booking_id' => $booking ? $booking->id : null,
						'customer_name' => $name,
						'customer_phone' => request_value('customer_phone'),
						'customer_email' => request_value('customer_email'),
						'amount' => (float) request_value('amount', $data['price']),
						'registration_date' => request_value('registration_date', date('Y-m-d')),
						'status' => 'confirmed',
						'payment_status' => request_value('payment_status', 'paid'),
						'notes' => request_value('notes', $data['remarks']),
						'created_by' => $this->user_id(),
						'created_at' => now_dt()
					));
					if ($this->db->affected_rows() < 1) {
						$this->api_response->error('DB_ERROR', 'Failed to save registration for this unit.', 500);
					}
				}
			}
			// Moving back to available/on_hold: cancel active booking/registration rows so counts stay correct.
			if ($newStatus !== $old && in_array($newStatus, array('available', 'on_hold'), true)
				&& in_array($old, array('booked', 'registered'), true)) {
				$now = now_dt();
				$this->db->where('unit_id', (int) $id)
					->where('deleted_at IS NULL', null, false)
					->where('status <>', 'cancelled')
					->update('registrations', array('status' => 'cancelled', 'updated_at' => $now));
				$this->db->where('unit_id', (int) $id)
					->where('deleted_at IS NULL', null, false)
					->where('status <>', 'cancelled')
					->update('bookings', array('status' => 'cancelled', 'updated_at' => $now));
			}
			$data['updated_at'] = now_dt();
			$this->db->where('id', (int) $id)->update('inventory_units', $data);
			$fresh = $this->inventory_model->find($id);
			if ($old !== $newStatus) {
				$label = status_label($newStatus);
				if ($label === '') {
					$label = (string) $newStatus;
				}
				$prevLabel = status_label($old);
				$project = $this->db->get_where('projects', array('id' => $fresh->project_id))->row();
				$projectName = $project ? $project->name : '';
				$superName = $this->auth_user ? $this->auth_user->name : 'Super Admin';
				$link = frontend_app_url('/inventory?project_id=' . (int) $fresh->project_id);
				$ctx = array(
					'projectName' => $projectName,
					'siteNumber' => $fresh->unit_no,
					'unit_no' => $fresh->unit_no,
					'previousStatus' => $prevLabel,
					'currentStatus' => $label,
					'status' => $label,
					'status_label' => $label,
					'superAdminName' => $superName,
					'updatedDate' => date('d M Y, h:i A'),
					'link' => $link,
					'project_id' => (int) $fresh->project_id,
					'actor_user_id' => $this->user_id()
				);
				$this->log_activity('inventory.status', $fresh->unit_no . ' status changed from ' . $prevLabel . ' to ' . $label, 'inventory_units', $id);
				if ($newStatus === 'available' && $old !== 'available') {
					$this->inventory_model->notify_available($fresh, $old);
				} else {
					$this->mailer->dispatch_event('inventory.status', $ctx);
				}
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
		$this->inventory_model->sync_status_from_transactions();
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
			if (in_array($status, array('booked', 'registered'), true)) {
				$this->api_response->error(
					'USE_BOOKING_FLOW',
					'Bulk status cannot set booked/registered. Create bookings or registrations instead.',
					422
				);
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
				$old = $unit->status;
				$data['status'] = $status;
				$this->db->where('id', (int) $id)->update('inventory_units', $data);
				if ($status === 'available' && $old !== 'available') {
					$unit->status = $status;
					$this->inventory_model->notify_available($unit, $old);
				} elseif ($old !== $status) {
					$project = $this->db->get_where('projects', array('id' => $unit->project_id))->row();
					$this->mailer->dispatch_event('inventory.status', array(
						'projectName' => $project ? $project->name : '',
						'siteNumber' => $unit->unit_no,
						'unit_no' => $unit->unit_no,
						'previousStatus' => status_label($old),
						'currentStatus' => status_label($status),
						'status' => status_label($status),
						'superAdminName' => $this->auth_user ? $this->auth_user->name : 'Super Admin',
						'updatedDate' => date('d M Y, h:i A'),
						'link' => frontend_app_url('/inventory?project_id=' . (int) $unit->project_id),
						'project_id' => (int) $unit->project_id,
						'actor_user_id' => $this->user_id()
					));
				}
			} else {
				if ($remarks !== null && $remarks !== '') {
					$data['remarks'] = $remarks;
				}
				$this->db->where('id', (int) $id)->update('inventory_units', $data);
			}
			if ($action === 'change_status' && $remarks !== null && $remarks !== '') {
				$this->db->where('id', (int) $id)->update('inventory_units', array('remarks' => $remarks, 'updated_at' => now_dt()));
			}
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
