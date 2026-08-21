<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends Api_Controller
{
	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			list($page, $limit, $offset) = pagination_params();
			$filters = array(
				'q' => request_value('q'),
				'role' => request_value('role'),
				'company_id' => request_value('company_id')
			);
			if (!$this->is_admin()) {
				$filters['company_id'] = $this->company_id();
			}
			list($items, $total) = $this->user_model->list_filtered($filters, $limit, $offset);
			$this->api_response->paginated($items, $total, $page, $limit);
		}
		if ($method === 'POST') {
			if (!$this->is_admin() && !$this->is_team_admin()) {
				$this->api_response->error('FORBIDDEN', 'You cannot create users.', 403);
			}
			$name = trim((string) request_value('name'));
			$email = trim((string) request_value('email'));
			$password = (string) request_value('password');
			$role = request_value('role', 'marketing_team_user');
			if ($name === '' || $email === '' || strlen($password) < 6) {
				$this->api_response->validation(array(
					'name' => 'Name is required.',
					'email' => 'Email is required.',
					'password' => 'Password must be at least 6 characters.'
				));
			}
			if ($this->user_model->find_by_email($email)) {
				$this->api_response->error('DUPLICATE', 'Email already exists.', 409);
			}
			$company_id = $this->is_admin() ? request_value('company_id') : $this->company_id();
			if ($this->is_team_admin()) {
				$role = 'marketing_team_user';
			}
			if ($role === 'promoter_admin' && !$this->is_admin()) {
				$this->api_response->error('FORBIDDEN', 'Cannot create promoter admin.', 403);
			}
			$id = $this->user_model->create(array(
				'company_id' => $company_id ?: null,
				'name' => $name,
				'email' => $email,
				'password_hash' => password_hash($password, PASSWORD_BCRYPT),
				'phone' => request_value('phone'),
				'avatar' => request_value('avatar'),
				'role' => $role,
				'status' => request_value('status', 'active'),
				'created_at' => now_dt()
			));
			$project_ids = request_value('project_ids', array());
			if ($this->is_team_admin()) {
				$allowed = $this->allowed_project_ids();
				$project_ids = array_values(array_intersect((array) $project_ids, $allowed ? $allowed : array()));
			}
			if ($project_ids) {
				$this->user_model->set_projects($id, $project_ids);
			}
			$this->mailer->notify_event('user.created', $email, array('email' => $email));
			$this->log_activity('user.create', 'Created user ' . $name, 'users', $id);
			$this->api_response->ok($this->user_model->public_user($this->user_model->find($id)), 'User created.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$user = $this->user_model->find($id);
		if (!$user) {
			$this->api_response->error('NOT_FOUND', 'User not found.', 404);
		}
		if (!$this->is_admin() && (int) $user->company_id !== $this->company_id()) {
			$this->api_response->error('FORBIDDEN', 'You can only manage users in your company.', 403);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->user_model->public_user($user));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$is_self = ((int) $id === $this->user_id());
			$can_manage = $this->is_admin() || $this->is_team_admin();
			if (!$can_manage && !$is_self) {
				$this->api_response->error('FORBIDDEN', 'You cannot update users.', 403);
			}
			$data = array('updated_at' => now_dt());
			$body = json_body();
			if ($can_manage) {
				$data['name'] = request_value('name', $user->name);
				$data['phone'] = request_value('phone', $user->phone);
				$data['status'] = request_value('status', $user->status);
				if ($this->is_admin() && request_value('role')) {
					$data['role'] = request_value('role');
				}
				if ($this->is_admin() && request_value('company_id') !== null) {
					$data['company_id'] = request_value('company_id') ?: null;
				}
			} else {
				if (request_value('name')) {
					$data['name'] = request_value('name', $user->name);
				}
				if (request_value('phone') !== null) {
					$data['phone'] = request_value('phone', $user->phone);
				}
			}
			if (array_key_exists('avatar', $body)) {
				$data['avatar'] = $body['avatar'] !== '' ? $body['avatar'] : null;
			}
			$password = request_value('password');
			if ($password) {
				$data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
			}
			$this->user_model->update_user($id, $data);
			if ($can_manage && request_value('project_ids') !== null) {
				$this->user_model->set_projects($id, request_value('project_ids', array()));
			}
			$this->log_activity('user.update', 'Updated user ' . $user->name, 'users', $id);
			$this->api_response->ok($this->user_model->public_user($this->user_model->find($id)), 'User updated.');
		}
		if ($method === 'DELETE') {
			if (!$this->is_admin() && !$this->is_team_admin()) {
				$this->api_response->error('FORBIDDEN', 'You cannot delete users.', 403);
			}
			if ((int) $id === $this->user_id()) {
				$this->api_response->error('FORBIDDEN', 'You cannot delete your own account.', 403);
			}
			if ($user->role === 'promoter_admin' && !$this->is_admin()) {
				$this->api_response->error('FORBIDDEN', 'Cannot delete a promoter admin.', 403);
			}
			$this->user_model->update_user($id, array('deleted_at' => now_dt(), 'status' => 'inactive', 'updated_at' => now_dt()));
			$this->log_activity('user.delete', 'Deleted user ' . $user->name, 'users', $id);
			$this->api_response->ok(array(), 'User deleted.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}
}
