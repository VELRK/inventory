<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Companies extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('company_model');
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			if (!$this->is_admin()) {
				$one = $this->company_model->find($this->company_id());
				$this->api_response->paginated(array($this->company_model->decorate($one)), 1, 1, 1);
			}
			list($page, $limit, $offset) = pagination_params(12);
			list($items, $total) = $this->company_model->list_filtered(array(
				'q' => request_value('q'),
				'status' => request_value('status')
			), $limit, $offset);
			$this->api_response->paginated($items, $total, $page, $limit);
		}
		if ($method === 'POST') {
			$this->require_permission('companies.manage');
			$name = trim((string) request_value('name'));
			$email = trim((string) request_value('email'));
			if ($name === '' || $email === '') {
				$this->api_response->validation(array('name' => 'Name is required.', 'email' => 'Email is required.'));
			}
			$this->db->insert('marketing_companies', array(
				'name' => $name,
				'email' => $email,
				'phone' => request_value('phone'),
				'address' => request_value('address'),
				'city' => request_value('city'),
				'status' => request_value('status', 'active'),
				'permissions' => json_encode(request_value('permissions', array('view_inventory','submit_block_requests','manage_users'))),
				'created_at' => now_dt()
			));
			$id = $this->db->insert_id();
			$project_ids = request_value('project_ids', array());
			if ($project_ids) {
				$this->company_model->set_projects($id, $project_ids);
			}
			$this->mailer->dispatch_event('company.created', array(
				'name' => $name,
				'target_email' => $email,
				'company_id' => (int) $id,
				'actor_user_id' => $this->user_id()
			));
			$this->log_activity('company.create', 'Created marketing company ' . $name, 'marketing_companies', $id);
			$this->api_response->ok($this->company_model->decorate($this->company_model->find($id)), 'Company created.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		if (!$this->is_admin() && (int) $id !== $this->company_id()) {
			$this->api_response->error('FORBIDDEN', 'You cannot access another company.', 403);
		}
		$company = $this->company_model->find($id);
		if (!$company) {
			$this->api_response->error('NOT_FOUND', 'Company not found.', 404);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->company_model->decorate($company));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$this->require_permission('companies.manage');
			$permissions = request_value('permissions', $company->permissions ? json_decode($company->permissions, true) : array());
			if (!is_array($permissions)) {
				$permissions = array();
			}
			$this->db->where('id', (int) $id)->update('marketing_companies', array(
				'name' => request_value('name', $company->name),
				'email' => request_value('email', $company->email),
				'phone' => request_value('phone', $company->phone),
				'address' => request_value('address', $company->address),
				'city' => request_value('city', $company->city),
				'status' => request_value('status', $company->status),
				'permissions' => json_encode(array_values($permissions)),
				'updated_at' => now_dt()
			));
			if (request_value('project_ids') !== null) {
				$this->company_model->set_projects($id, request_value('project_ids', array()));
			}
			$this->log_activity('company.update', 'Updated company ' . $company->name, 'marketing_companies', $id);
			$this->api_response->ok($this->company_model->decorate($this->company_model->find($id)), 'Company updated.');
		}
		if ($method === 'DELETE') {
			$this->require_permission('companies.manage');
			$this->db->where('id', (int) $id)->update('marketing_companies', array('deleted_at' => now_dt(), 'status' => 'inactive', 'updated_at' => now_dt()));
			$this->log_activity('company.delete', 'Archived company ' . $company->name, 'marketing_companies', $id);
			$this->api_response->ok(array(), 'Company deleted.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function projects($id)
	{
		$this->require_permission('companies.manage');
		$this->company_model->set_projects($id, request_value('project_ids', array()));
		$this->api_response->ok($this->company_model->decorate($this->company_model->find($id)), 'Projects assigned.');
	}
}
