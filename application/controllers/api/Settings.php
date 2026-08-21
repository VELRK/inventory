<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_roles(array('promoter_admin'));
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok(array(
				'groups' => $this->setting_model->all_grouped(),
				'mail_log' => $this->db->order_by('id', 'DESC')->limit(20)->get('mail_logs')->result()
			));
		}
		if ($method === 'POST' || $method === 'PUT') {
			$values = request_value('values', array());
			if (!is_array($values)) {
				$this->api_response->validation(array('values' => 'Expected key/value map.'));
			}
			foreach ($values as $key => $value) {
				$key = preg_replace('/[^a-z0-9_]/i', '', $key);
				if ($key === '') {
					continue;
				}
				$existing = $this->db->get_where('settings', array('setting_key' => $key))->row();
				if ($existing && (int) $existing->is_secret === 1 && ($value === '' || strpos((string) $value, '*') === 0)) {
					continue;
				}
				$this->setting_model->put($key, is_array($value) ? json_encode($value) : $value);
			}
			$this->log_activity('settings.update', 'Updated application settings', 'settings', 0);
			$this->api_response->ok($this->setting_model->all_grouped(), 'Settings saved.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function credentials()
	{
		$this->api_response->ok($this->setting_model->credentials());
	}

	public function mail_test()
	{
		$to = request_value('to', $this->auth_user->email);
		$ok = $this->mailer->notify_event('mail.test', $to, array());
		$this->api_response->ok(array('queued_or_sent' => $ok), $ok ? 'Test mail processed. Check mail logs.' : 'Mail send failed.');
	}
}
