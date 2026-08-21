<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bookings extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('booking_model', 'inventory_model'));
	}

	public function index()
	{
		$method = $this->http_method();
		$filters = $this->_filters();
		if ($method === 'GET') {
			list($page, $limit, $offset) = pagination_params();
			list($items, $total, $sum) = $this->booking_model->list_filtered($filters, $limit, $offset);
			$this->api_response->paginated($items, $total, $page, $limit, array(
				'total_value' => $sum,
				'total_value_formatted' => format_inr($sum)
			));
		}
		if ($method === 'POST') {
			$this->require_permission('bookings.manage');
			$unit_id = (int) request_value('unit_id');
			$unit = $this->inventory_model->find($unit_id);
			if (!$unit) {
				$this->api_response->error('NOT_FOUND', 'Unit not found.', 404);
			}
			if (!in_array($unit->status, array('available', 'on_hold'), true)) {
				$this->api_response->error('UNIT_NOT_BOOKABLE', 'Only available or on-hold units can be booked.', 409);
			}
			$allowed = $this->allowed_project_ids();
			if ($allowed !== null && !in_array((int) $unit->project_id, $allowed, true)) {
				$this->api_response->error('FORBIDDEN', 'This unit is not assigned to you.', 403);
			}
			$name = trim((string) request_value('customer_name'));
			if ($name === '') {
				$this->api_response->validation(array('customer_name' => 'Customer name is required.'));
			}
			$company_id = request_value('company_id');
			if ($this->is_team_admin()) {
				$company_id = $this->company_id();
			}
			$this->db->insert('bookings', array(
				'unit_id' => $unit_id,
				'project_id' => $unit->project_id,
				'company_id' => $company_id ?: null,
				'customer_name' => $name,
				'customer_phone' => request_value('customer_phone'),
				'customer_email' => request_value('customer_email'),
				'amount' => (float) request_value('amount', $unit->price),
				'booking_date' => request_value('booking_date', date('Y-m-d')),
				'status' => request_value('status', 'confirmed'),
				'payment_status' => request_value('payment_status', 'partial'),
				'notes' => request_value('notes'),
				'created_by' => $this->user_id(),
				'created_at' => now_dt()
			));
			$id = $this->db->insert_id();
			$this->inventory_model->set_status($unit_id, 'booked');
			$this->mailer->dispatch_event('booking.created', array(
				'customer' => $name,
				'unit_no' => $unit->unit_no,
				'amount' => format_inr(request_value('amount', $unit->price)),
				'company_id' => $company_id ? (int) $company_id : 0,
				'project_id' => (int) $unit->project_id,
				'actor_user_id' => $this->user_id()
			));
			$this->log_activity('booking.create', 'Booking created for ' . $unit->unit_no, 'bookings', $id);
			$this->api_response->ok($this->booking_model->decorate($this->booking_model->find($id)), 'Booking created.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$this->require_permission('bookings.manage');
		$row = $this->booking_model->find($id);
		if (!$row) {
			$this->api_response->error('NOT_FOUND', 'Booking not found.', 404);
		}
		if ($this->is_team_admin() && (int) $row->company_id !== (int) $this->company_id()) {
			$this->api_response->error('FORBIDDEN', 'You can only manage your company bookings.', 403);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->booking_model->decorate($row));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$company_id = request_value('company_id', $row->company_id);
			if ($this->is_team_admin()) {
				$company_id = $this->company_id();
			}
			$this->db->where('id', (int) $id)->update('bookings', array(
				'customer_name' => request_value('customer_name', $row->customer_name),
				'customer_phone' => request_value('customer_phone', $row->customer_phone),
				'customer_email' => request_value('customer_email', $row->customer_email),
				'company_id' => $company_id ?: null,
				'booking_date' => request_value('booking_date', $row->booking_date),
				'status' => request_value('status', $row->status),
				'payment_status' => request_value('payment_status', $row->payment_status),
				'amount' => request_value('amount', $row->amount),
				'notes' => request_value('notes', $row->notes),
				'updated_at' => now_dt()
			));
			$this->log_activity('booking.update', 'Updated booking #' . $id, 'bookings', $id);
			$this->api_response->ok($this->booking_model->decorate($this->booking_model->find($id)), 'Booking updated.');
		}
		if ($method === 'DELETE') {
			$this->db->where('id', (int) $id)->update('bookings', array('deleted_at' => now_dt(), 'updated_at' => now_dt()));
			$unit = $this->inventory_model->find($row->unit_id);
			if ($unit && $unit->status === 'booked') {
				$this->inventory_model->set_status($row->unit_id, 'available');
			}
			$this->log_activity('booking.delete', 'Deleted booking #' . $id, 'bookings', $id);
			$this->api_response->ok(array(), 'Booking deleted.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function export()
	{
		$this->require_permission('bookings.manage');
		list($items) = $this->booking_model->list_filtered($this->_filters(), 1000, 0);
		$rows = array();
		foreach ($items as $item) {
			$rows[] = array($item['customer_name'], $item['unit_no'], $item['project_name'], $item['company_name'], $item['amount'], $item['booking_date'], $item['status'], $item['payment_status']);
		}
		csv_download('bookings.csv', array('Customer','Unit','Project','Company','Amount','Date','Status','Payment'), $rows);
	}

	private function _filters()
	{
		$filters = array(
			'company_id' => request_value('company_id'),
			'project_id' => request_value('project_id'),
			'status' => request_value('status'),
			'payment_status' => request_value('payment_status'),
			'from' => request_value('from'),
			'to' => request_value('to'),
			'q' => request_value('q')
		);
		if (!$this->is_admin()) {
			$filters['company_id'] = $this->company_id();
		}
		return $filters;
	}
}
