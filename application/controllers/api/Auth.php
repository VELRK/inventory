<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends Api_Controller
{
	protected $public_methods = array('login', 'forgot', 'reset');

	public function __construct()
	{
		parent::__construct();
	}

	public function login()
	{
		if ($this->http_method() !== 'POST') {
			$this->api_response->error('METHOD_NOT_ALLOWED', 'Use POST.', 405);
		}
		$email = trim((string) request_value('email'));
		$password = (string) request_value('password');
		if ($email === '' || $password === '') {
			$this->api_response->validation(array(
				'email' => 'Email is required.',
				'password' => 'Password is required.'
			));
		}
		$user = $this->user_model->find_by_email($email);
		if (!$user || !password_verify($password, $user->password_hash)) {
			$this->api_response->error('INVALID_CREDENTIALS', 'Email or password is incorrect.', 401);
		}
		if ($user->status !== 'active') {
			$this->api_response->error('ACCOUNT_DISABLED', 'This account is inactive.', 403);
		}
		$token = $this->token_model->issue($user->id);
		$this->user_model->update_user($user->id, array('last_login_at' => now_dt()));
		$this->log_activity('auth.login', $user->name . ' logged in', 'users', $user->id);
		$this->api_response->ok(array(
			'token' => $token,
			'expires_in' => 86400,
			'user' => $this->user_model->public_user($user)
		), 'Login successful.');
	}

	public function logout()
	{
		$header = $this->input->get_request_header('Authorization', true);
		$token = null;
		if ($header && preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
			$token = $m[1];
		}
		$this->token_model->revoke($token);
		$this->log_activity('auth.logout', 'User logged out', 'users', $this->user_id());
		$this->api_response->ok(array(), 'Logged out.');
	}

	public function me()
	{
		$this->api_response->ok($this->user_model->public_user($this->auth_user));
	}

	public function forgot()
	{
		$email = trim((string) request_value('email'));
		if ($email === '') {
			$this->api_response->validation(array('email' => 'Email is required.'));
		}
		$user = $this->user_model->find_by_email($email);
		if ($user) {
			$token = bin2hex(random_bytes(16));
			$this->db->insert('password_resets', array(
				'user_id' => $user->id,
				'token' => $token,
				'expires_at' => date('Y-m-d H:i:s', time() + 3600),
				'created_at' => now_dt()
			));
			$this->mailer->notify_event('auth.forgot', $user->email, array('token' => $token));
		}
		$this->api_response->ok(array(), 'If the email exists, a reset token has been sent.');
	}

	public function reset()
	{
		$token = (string) request_value('token');
		$password = (string) request_value('password');
		if (strlen($password) < 6) {
			$this->api_response->validation(array('password' => 'Password must be at least 6 characters.'));
		}
		$row = $this->db->where('token', $token)
			->where('used_at IS NULL', null, false)
			->where('expires_at >=', now_dt())
			->get('password_resets')->row();
		if (!$row) {
			$this->api_response->error('INVALID_TOKEN', 'Reset token is invalid or expired.', 400);
		}
		$this->user_model->update_user($row->user_id, array(
			'password_hash' => password_hash($password, PASSWORD_BCRYPT),
			'updated_at' => now_dt()
		));
		$this->db->where('id', $row->id)->update('password_resets', array('used_at' => now_dt()));
		$this->api_response->ok(array(), 'Password updated. You can login now.');
	}

	public function change_password()
	{
		$current = (string) request_value('current_password');
		$new = (string) request_value('new_password');
		if (!password_verify($current, $this->auth_user->password_hash)) {
			$this->api_response->error('INVALID_PASSWORD', 'Current password is incorrect.', 400);
		}
		if (strlen($new) < 6) {
			$this->api_response->validation(array('new_password' => 'Password must be at least 6 characters.'));
		}
		$this->user_model->update_user($this->user_id(), array(
			'password_hash' => password_hash($new, PASSWORD_BCRYPT),
			'updated_at' => now_dt()
		));
		$this->api_response->ok(array(), 'Password changed.');
	}
}
