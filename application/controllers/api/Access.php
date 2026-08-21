<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Access extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('role_permission_model');
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->require_permission('nav.access');
			$this->api_response->ok($this->role_permission_model->matrix());
		}
		if ($method === 'PUT' || $method === 'POST') {
			$this->require_permission('access.manage');
			$matrix = request_value('matrix', array());
			if (!is_array($matrix) || empty($matrix)) {
				$this->api_response->validation(array('matrix' => 'Permission matrix is required.'));
			}
			$data = $this->role_permission_model->save_matrix($matrix);
			$this->log_activity('access.update', 'Updated role access matrix', 'role_permissions', null);
			$this->api_response->ok($data, 'Access permissions saved.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}
}
