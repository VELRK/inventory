<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity extends Api_Controller
{
	public function index()
	{
		list($page, $limit, $offset) = pagination_params();
		$filters = array(
			'q' => request_value('q'),
			'from' => request_value('from'),
			'to' => request_value('to')
		);
		if (!$this->is_admin()) {
			$filters['company_id'] = $this->company_id();
		}
		list($items, $total) = $this->activity_model->list_filtered($filters, $limit, $offset);
		$this->api_response->paginated($items, $total, $page, $limit);
	}
}
