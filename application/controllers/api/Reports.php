<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('booking_model', 'registration_model', 'company_model', 'project_model'));
		$this->require_roles(array('promoter_admin'));
	}

	public function index()
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
		$type = request_value('type', 'bookings');
		list($page, $limit, $offset) = pagination_params();

		if ($type === 'registrations') {
			list($items, $total, $sum) = $this->registration_model->list_filtered($filters, $limit, $offset);
		} else {
			list($items, $total, $sum) = $this->booking_model->list_filtered($filters, $limit, $offset);
		}

		$book_sum = $this->booking_model->list_filtered($filters, 1, 0);
		$reg_sum = $this->registration_model->list_filtered($filters, 1, 0);
		$customers = array();
		foreach (array_merge($book_sum[0], $reg_sum[0]) as $row) {
			$customers[$row['customer_name']] = true;
		}

		$this->api_response->ok(array(
			'type' => $type,
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'limit' => $limit,
			'pages' => $limit ? (int) ceil($total / $limit) : 0,
			'total_value' => $sum,
			'total_value_formatted' => format_inr($sum),
			'quick_stats' => array(
				'total_bookings' => $book_sum[1],
				'total_registrations' => $reg_sum[1],
				'total_value' => (float) $book_sum[2] + (float) $reg_sum[2],
				'total_value_formatted' => format_inr((float) $book_sum[2] + (float) $reg_sum[2]),
				'total_customers' => count($customers) > 0 ? count($customers) : ($book_sum[1] + $reg_sum[1])
			)
		));
	}

	public function filters()
	{
		$companies = $this->db->select('id, name')->where('deleted_at IS NULL', null, false)->get('marketing_companies')->result();
		$projects = $this->db->select('id, name, city')->where('deleted_at IS NULL', null, false)->get('projects')->result();
		$this->api_response->ok(array(
			'companies' => $companies,
			'projects' => $projects,
			'statuses' => array('pending', 'confirmed', 'cancelled'),
			'payment_statuses' => array('unpaid', 'partial', 'paid')
		));
	}

	public function export()
	{
		$type = request_value('type', 'bookings');
		$filters = array(
			'company_id' => request_value('company_id'),
			'project_id' => request_value('project_id'),
			'status' => request_value('status'),
			'payment_status' => request_value('payment_status'),
			'from' => request_value('from'),
			'to' => request_value('to')
		);
		if ($type === 'registrations') {
			list($items) = $this->registration_model->list_filtered($filters, 2000, 0);
			$date_key = 'registration_date';
		} else {
			list($items) = $this->booking_model->list_filtered($filters, 2000, 0);
			$date_key = 'booking_date';
		}
		$rows = array();
		foreach ($items as $item) {
			$rows[] = array($item['customer_name'], $item['unit_no'], $item['project_name'], $item['company_name'], $item['amount'], $item[$date_key], $item['status'], $item['payment_status']);
		}
		csv_download($type . '.csv', array('Customer','Unit','Project','Company','Amount','Date','Status','Payment'), $rows);
	}
}
