<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registrations extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('registration_model', 'inventory_model'));
	}

	public function index()
	{
		$method = $this->http_method();
		$filters = $this->_filters();
		if ($method === 'GET') {
			list($page, $limit, $offset) = pagination_params();
			list($items, $total, $sum) = $this->registration_model->list_filtered($filters, $limit, $offset);
			$this->api_response->paginated($items, $total, $page, $limit, array(
				'total_value' => $sum,
				'total_value_formatted' => format_inr($sum)
			));
		}
		if ($method === 'POST') {
			$this->require_permission('registrations.manage');
			$unit_id = (int) request_value('unit_id');
			$unit = $this->inventory_model->find($unit_id);
			if (!$unit) {
				$this->api_response->error('NOT_FOUND', 'Unit not found.', 404);
			}
			if ($unit->status !== 'booked' && $unit->status !== 'registered') {
				$this->api_response->error('UNIT_NOT_REGISTERABLE', 'Only booked units can be registered. Create a booking first.', 409);
			}
			$name = trim((string) request_value('customer_name'));
			if ($name === '') {
				$this->api_response->validation(array('customer_name' => 'Customer name is required.'));
			}
			$booking_id = request_value('booking_id');
			if (!$booking_id) {
				$booking = $this->db->where('unit_id', $unit_id)
					->where('deleted_at IS NULL', null, false)
					->where('status <>', 'cancelled')
					->order_by('id', 'DESC')
					->get('bookings')->row();
				$booking_id = $booking ? $booking->id : null;
			}
			$this->db->insert('registrations', array(
				'unit_id' => $unit_id,
				'project_id' => $unit->project_id,
				'company_id' => request_value('company_id') ?: null,
				'booking_id' => $booking_id ?: null,
				'customer_name' => $name,
				'customer_phone' => request_value('customer_phone'),
				'customer_email' => request_value('customer_email'),
				'amount' => (float) request_value('amount', $unit->price),
				'registration_date' => request_value('registration_date', date('Y-m-d')),
				'status' => request_value('status', 'confirmed'),
				'payment_status' => request_value('payment_status', 'paid'),
				'notes' => request_value('notes'),
				'created_by' => $this->user_id(),
				'created_at' => now_dt()
			));
			if ($this->db->affected_rows() < 1) {
				$err = $this->db->error();
				$this->api_response->error('DB_ERROR', !empty($err['message']) ? $err['message'] : 'Failed to save registration.', 500);
			}
			$id = (int) $this->db->insert_id();
			if ($id < 1) {
				$this->api_response->error('DB_ERROR', 'Registration was not stored.', 500);
			}
			$this->inventory_model->set_status($unit_id, 'registered');
			$this->mailer->dispatch_event('registration.created', array(
				'customer' => $name,
				'unit_no' => $unit->unit_no,
				'company_id' => $this->company_id(),
				'project_id' => (int) $unit->project_id,
				'actor_user_id' => $this->user_id()
			));
			$this->log_activity('registration.create', 'Registration created for ' . $unit->unit_no, 'registrations', $id);
			$this->api_response->ok($this->registration_model->decorate($this->registration_model->find($id)), 'Registration created.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$this->require_permission('registrations.manage');
		$row = $this->registration_model->find($id);
		if (!$row) {
			$this->api_response->error('NOT_FOUND', 'Registration not found.', 404);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->registration_model->decorate($row));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$new_status = request_value('status', $row->status);
			$this->db->where('id', (int) $id)->update('registrations', array(
				'customer_name' => request_value('customer_name', $row->customer_name),
				'customer_phone' => request_value('customer_phone', $row->customer_phone),
				'customer_email' => request_value('customer_email', $row->customer_email),
				'company_id' => request_value('company_id', $row->company_id) ?: null,
				'registration_date' => request_value('registration_date', $row->registration_date),
				'status' => $new_status,
				'payment_status' => request_value('payment_status', $row->payment_status),
				'amount' => request_value('amount', $row->amount),
				'notes' => request_value('notes', $row->notes),
				'updated_at' => now_dt()
			));
			if ($new_status === 'cancelled' && $row->status !== 'cancelled') {
				$unit = $this->inventory_model->find($row->unit_id);
				if ($unit && $unit->status === 'registered') {
					$booking = $this->db->where('unit_id', $row->unit_id)
						->where('deleted_at IS NULL', null, false)
						->where('status <>', 'cancelled')
						->order_by('id', 'DESC')
						->get('bookings')->row();
					$this->inventory_model->set_status($row->unit_id, $booking ? 'booked' : 'available');
				}
			} elseif ($row->status === 'cancelled' && $new_status !== 'cancelled') {
				$this->inventory_model->set_status($row->unit_id, 'registered');
			}
			$this->log_activity('registration.update', 'Updated registration #' . $id, 'registrations', $id);
			$this->api_response->ok($this->registration_model->decorate($this->registration_model->find($id)), 'Registration updated.');
		}
		if ($method === 'DELETE') {
			$this->db->where('id', (int) $id)->update('registrations', array('deleted_at' => now_dt(), 'updated_at' => now_dt()));
			$unit = $this->inventory_model->find($row->unit_id);
			if ($unit && $unit->status === 'registered') {
				$booking = $this->db->where('unit_id', $row->unit_id)
					->where('deleted_at IS NULL', null, false)
					->where('status <>', 'cancelled')
					->order_by('id', 'DESC')
					->get('bookings')->row();
				$this->inventory_model->set_status($row->unit_id, $booking ? 'booked' : 'available');
			}
			$this->log_activity('registration.delete', 'Deleted registration #' . $id, 'registrations', $id);
			$this->api_response->ok(array(), 'Registration deleted.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function export()
	{
		$this->require_permission('registrations.manage');
		list($items) = $this->registration_model->list_filtered($this->_filters(), 1000, 0);
		$rows = array();
		foreach ($items as $item) {
			$rows[] = array($item['customer_name'], $item['unit_no'], $item['project_name'], $item['company_name'], $item['amount'], $item['registration_date'], $item['status']);
		}
		csv_download('registrations.csv', array('Customer','Unit','Project','Company','Amount','Date','Status'), $rows);
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
