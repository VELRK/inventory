<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api_Controller extends CI_Controller
{
	protected $auth_user = null;
	protected $require_auth = true;
	protected $public_methods = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->library(array('api_response', 'mailer'));
		$this->load->model(array(
			'user_model',
			'token_model',
			'activity_model',
			'setting_model'
		));
		$this->output->set_content_type('application/json');
		$this->_authenticate();
	}

	protected function _authenticate()
	{
		$method = $this->router->method;
		if (!$this->require_auth || in_array($method, $this->public_methods, true)) {
			return;
		}

		$header = $this->input->get_request_header('Authorization', true);
		$token = null;
		if ($header && preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
			$token = $m[1];
		}
		if (!$token) {
			$token = $this->input->get_request_header('X-Api-Token', true);
		}

		$user = $this->token_model->user_by_token($token);
		if (!$user) {
			$this->api_response->error('UNAUTHORIZED', 'Invalid or expired token. Please login again.', 401);
		}
		if ($user->status !== 'active') {
			$this->api_response->error('ACCOUNT_DISABLED', 'This account is inactive.', 403);
		}
		$this->auth_user = $user;
	}

	protected function require_roles($roles)
	{
		if (!$this->auth_user) {
			$this->api_response->error('UNAUTHORIZED', 'Authentication required.', 401);
		}
		if (!in_array($this->auth_user->role, (array) $roles, true)) {
			$this->api_response->error('FORBIDDEN', 'You do not have permission for this action.', 403);
		}
	}

	protected function is_admin()
	{
		return $this->auth_user && $this->auth_user->role === 'promoter_admin';
	}

	protected function is_team_admin()
	{
		return $this->auth_user && $this->auth_user->role === 'marketing_team_admin';
	}

	protected function company_id()
	{
		return $this->auth_user ? (int) $this->auth_user->company_id : 0;
	}

	protected function user_id()
	{
		return $this->auth_user ? (int) $this->auth_user->id : 0;
	}

	protected function log_activity($action, $description, $entity_type = null, $entity_id = null, $meta = null)
	{
		$this->activity_model->add(array(
			'user_id' => $this->user_id() ?: null,
			'company_id' => $this->company_id() ?: null,
			'action' => $action,
			'entity_type' => $entity_type,
			'entity_id' => $entity_id,
			'description' => $description,
			'meta' => $meta ? json_encode($meta) : null,
			'ip_address' => $this->input->ip_address(),
			'created_at' => now_dt()
		));
	}

	protected function notify($user_id, $title, $message)
	{
		$this->db->insert('notifications', array(
			'user_id' => (int) $user_id,
			'title' => $title,
			'message' => $message,
			'is_read' => 0,
			'created_at' => now_dt()
		));
	}

	protected function http_method()
	{
		$override = $this->input->get_request_header('X-HTTP-Method-Override', true);
		if ($override) {
			return strtoupper($override);
		}
		return strtoupper($this->input->method(true));
	}

	protected function allowed_project_ids()
	{
		if ($this->is_admin()) {
			return null;
		}
		$ids = array();
		if ($this->company_id()) {
			$rows = $this->db->select('project_id')
				->from('company_project_assignments')
				->where('company_id', $this->company_id())
				->get()->result();
			foreach ($rows as $row) {
				$ids[] = (int) $row->project_id;
			}
		}
		if ($this->auth_user->role === 'marketing_team_user') {
			$user_ids = array();
			$rows = $this->db->select('project_id')
				->from('user_project_assignments')
				->where('user_id', $this->user_id())
				->get()->result();
			foreach ($rows as $row) {
				$user_ids[] = (int) $row->project_id;
			}
			if (!empty($user_ids)) {
				$ids = array_values(array_intersect($ids, $user_ids));
			}
		}
		return $ids;
	}
}
