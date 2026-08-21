<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mailer
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model(array('setting_model', 'email_template_model'));
	}

	public function send($to, $subject, $body, $event = null)
	{
		$status = 'queued';
		$error = null;
		$enabled = $this->CI->setting_model->get('mail_enabled', '0') === '1';

		if ($enabled) {
			$this->_configure();
			$this->CI->email->from(
				$this->CI->setting_model->get('mail_from_email', 'info@superfinelabels.in'),
				$this->CI->setting_model->get('mail_from_name', 'Inventory')
			);
			$this->CI->email->to($to);
			$this->CI->email->subject($subject);
			$this->CI->email->message($this->_wrap($subject, $body));
			$ok = @$this->CI->email->send();
			if ($ok) {
				$status = 'sent';
			} else {
				$status = 'failed';
				$error = $this->CI->email->print_debugger(array('headers'));
			}
		} else {
			$status = 'queued';
		}

		$this->CI->db->insert('mail_logs', array(
			'to_email' => $to,
			'subject' => $subject,
			'body' => $body,
			'event' => $event,
			'status' => $status,
			'error_message' => $error,
			'created_at' => now_dt()
		));

		return $status !== 'failed';
	}

	/**
	 * Legacy helper — sets target_email then uses template recipient rules.
	 */
	public function notify_event($event, $to, $context = array())
	{
		if ($to) {
			$context['target_email'] = $to;
		}
		return $this->dispatch_event($event, $context);
	}

	/**
	 * Send an event email to everyone selected on the template (recipients).
	 * Context keys used for routing:
	 *  - target_email / target_user_id
	 *  - company_id
	 *  - project_id (resolves related companies)
	 *  - actor_user_id
	 *  - extra_emails (one-off list)
	 */
	public function dispatch_event($event, $context = array())
	{
		$fallback = $this->_fallback_templates();
		$row = $this->CI->email_template_model->find_by_event($event);
		if ($row) {
			if ((int) $row->is_active !== 1) {
				return false;
			}
			$subject = $row->subject;
			$tpl = $row->body;
			$recipients = $this->CI->email_template_model->parse_recipients($row);
		} elseif (isset($fallback[$event])) {
			list($subject, $tpl) = $fallback[$event];
			$recipients = $this->CI->email_template_model->default_recipients_for($event);
		} else {
			return false;
		}

		foreach ($context as $key => $value) {
			if (is_array($value) || is_object($value)) {
				continue;
			}
			$repl = (string) $value;
			$subject = str_ireplace(array('{{' . $key . '}}', '{' . $key . '}'), $repl, $subject);
			$tpl = str_ireplace(array('{{' . $key . '}}', '{' . $key . '}'), $repl, $tpl);
		}
		if (!empty($context['link'])) {
			$link = (string) $context['link'];
			if (strpos($tpl, $link) === false) {
				if (strpos($event, 'auth.') === 0 || $event === 'user.created') {
					$tpl .= "\n\nSet / reset password link (valid " . (isset($context['expires']) ? $context['expires'] : '48 hours') . "):\n" . $link;
				} else {
					$tpl .= "\n\nOpen in Syncr:\n" . $link;
				}
			}
		}
		if (in_array($event, array('inventory.status', 'inventory.available'), true) && !empty($context['currentStatus'])) {
			$statusText = (string) $context['currentStatus'];
			if (stripos($subject, $statusText) === false && stripos($subject, 'Available') === false) {
				$subject = rtrim($subject) . ' ' . $statusText;
			}
		}

		$emails = $this->resolve_recipient_emails($recipients, $context);
		if (!$emails) {
			return false;
		}
		$ok = true;
		foreach ($emails as $email) {
			if (!$this->send($email, $subject, $tpl, $event)) {
				$ok = false;
			}
		}
		return $ok;
	}

	public function resolve_recipient_emails($recipients, $context = array())
	{
		$emails = array();
		$company_id = !empty($context['company_id']) ? (int) $context['company_id'] : 0;
		$project_id = !empty($context['project_id']) ? (int) $context['project_id'] : 0;

		$company_ids = array();
		if ($company_id) {
			$company_ids[] = $company_id;
		}
		if ($project_id) {
			$rows = $this->CI->db->select('company_id')
				->from('company_project_assignments')
				->where('project_id', $project_id)
				->get()->result();
			foreach ($rows as $row) {
				$company_ids[] = (int) $row->company_id;
			}
		}
		$company_ids = array_values(array_unique(array_filter($company_ids)));

		if (!empty($recipients['target_user'])) {
			if (!empty($context['target_email'])) {
				$emails[] = $context['target_email'];
			} elseif (!empty($context['target_user_id'])) {
				$user = $this->CI->db->where('id', (int) $context['target_user_id'])
					->where('status', 'active')
					->where('deleted_at IS NULL', null, false)
					->get('users')->row();
				if ($user && $user->email) {
					$emails[] = $user->email;
				}
			}
		}

		if (!empty($recipients['promoter_admin'])) {
			$rows = $this->CI->db->where('role', 'promoter_admin')
				->where('status', 'active')
				->where('deleted_at IS NULL', null, false)
				->get('users')->result();
			foreach ($rows as $row) {
				if ($row->email) {
					$emails[] = $row->email;
				}
			}
		}

		// Requesting company admins+users who have access to this project.
		if (!empty($recipients['project_company_users']) && $project_id && $company_id) {
			$emails = array_merge($emails, $this->_project_access_emails($project_id, $company_id));
		}

		// All marketing admins/users (any company) with access to this project.
		if (!empty($recipients['project_all_users']) && $project_id) {
			$emails = array_merge($emails, $this->_project_access_emails($project_id, null));
		}

		$need_company_users = !empty($recipients['team_admin'])
			|| !empty($recipients['team_user'])
			|| !empty($recipients['company_all']);
		if ($need_company_users && $company_ids) {
			$this->CI->db->where_in('company_id', $company_ids)
				->where('status', 'active')
				->where('deleted_at IS NULL', null, false);
			if (!empty($recipients['company_all'])) {
				// all roles in company
			} elseif (!empty($recipients['team_admin']) && !empty($recipients['team_user'])) {
				$this->CI->db->where_in('role', array('marketing_team_admin', 'marketing_team_user'));
			} elseif (!empty($recipients['team_admin'])) {
				$this->CI->db->where('role', 'marketing_team_admin');
			} else {
				$this->CI->db->where('role', 'marketing_team_user');
			}
			$rows = $this->CI->db->get('users')->result();
			foreach ($rows as $row) {
				if ($row->email) {
					$emails[] = $row->email;
				}
			}
		}

		if (!empty($recipients['company_email']) && $company_ids) {
			$companies = $this->CI->db->where_in('id', $company_ids)
				->where('deleted_at IS NULL', null, false)
				->get('marketing_companies')->result();
			foreach ($companies as $company) {
				if (!empty($company->email)) {
					$emails[] = $company->email;
				}
			}
		}

		if (!empty($recipients['actor'])) {
			$actor_id = !empty($context['actor_user_id']) ? (int) $context['actor_user_id'] : 0;
			if (!$actor_id && !empty($this->CI->auth_user)) {
				$actor_id = (int) $this->CI->auth_user->id;
			}
			if ($actor_id) {
				$user = $this->CI->db->where('id', $actor_id)
					->where('status', 'active')
					->where('deleted_at IS NULL', null, false)
					->get('users')->row();
				if ($user && $user->email) {
					$emails[] = $user->email;
				}
			}
		}

		$extra = array();
		if (!empty($recipients['extra_emails'])) {
			$extra = array_merge($extra, $this->_split_emails($recipients['extra_emails']));
		}
		if (!empty($context['extra_emails'])) {
			$extra = array_merge($extra, $this->_split_emails($context['extra_emails']));
		}
		foreach ($extra as $email) {
			$emails[] = $email;
		}

		$clean = array();
		foreach ($emails as $email) {
			$email = strtolower(trim((string) $email));
			if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$clean[$email] = true;
			}
		}
		return array_keys($clean);
	}

	/**
	 * Marketing team admins/users who can access $project_id.
	 * Optional $company_id limits to that marketing company only.
	 */
	private function _project_access_emails($project_id, $company_id = null)
	{
		$project_id = (int) $project_id;
		if ($project_id <= 0) {
			return array();
		}
		$this->CI->db->where_in('role', array('marketing_team_admin', 'marketing_team_user'))
			->where('status', 'active')
			->where('deleted_at IS NULL', null, false);
		if ($company_id) {
			$this->CI->db->where('company_id', (int) $company_id);
		} else {
			$this->CI->db->where('company_id IS NOT NULL', null, false);
		}
		$users = $this->CI->db->get('users')->result();
		$out = array();
		foreach ($users as $user) {
			if ($this->_user_can_access_project($user, $project_id) && !empty($user->email)) {
				$out[] = $user->email;
			}
		}
		return $out;
	}

	/** Mirror Api_Controller::allowed_project_ids for a given user row. */
	private function _user_can_access_project($user, $project_id)
	{
		$project_id = (int) $project_id;
		$uid = (int) $user->id;
		$cid = !empty($user->company_id) ? (int) $user->company_id : 0;

		$personal = array();
		$rows = $this->CI->db->select('project_id')
			->from('user_project_assignments')
			->where('user_id', $uid)
			->get()->result();
		foreach ($rows as $row) {
			$personal[] = (int) $row->project_id;
		}

		$company = array();
		if ($cid) {
			$rows = $this->CI->db->select('project_id')
				->from('company_project_assignments')
				->where('company_id', $cid)
				->get()->result();
			foreach ($rows as $row) {
				$company[] = (int) $row->project_id;
			}
		}

		if ($user->role === 'marketing_team_admin') {
			$allowed = array_values(array_unique(array_merge($company, $personal)));
			return in_array($project_id, $allowed, true);
		}

		// Team user: personal assignments win; else company pool.
		if (!empty($personal)) {
			return in_array($project_id, $personal, true);
		}
		return in_array($project_id, $company, true);
	}

	private function _split_emails($value)
	{
		if (is_array($value)) {
			return $value;
		}
		$parts = preg_split('/[,;\s]+/', (string) $value);
		return is_array($parts) ? $parts : array();
	}

	private function _fallback_templates()
	{
		$out = array();
		foreach ($this->CI->email_template_model->defaults() as $row) {
			$out[$row['event_key']] = array($row['subject'], $row['body']);
		}
		return $out;
	}

	private function _configure()
	{
		$port = (int) $this->CI->setting_model->get('mail_smtp_port', '465');
		$crypto = $this->CI->setting_model->get('mail_smtp_crypto', $port === 465 ? 'ssl' : 'tls');
		$config = array(
			'protocol' => $this->CI->setting_model->get('mail_protocol', 'smtp'),
			'smtp_host' => $this->CI->setting_model->get('mail_smtp_host', 'smtp.hostinger.com'),
			'smtp_port' => $port,
			'smtp_user' => $this->CI->setting_model->get('mail_smtp_user', ''),
			'smtp_pass' => $this->CI->setting_model->get('mail_smtp_pass', ''),
			'smtp_crypto' => $crypto,
			'mailtype' => 'html',
			'charset' => 'utf-8',
			'newline' => "\r\n",
			'crlf' => "\r\n"
		);
		$this->CI->load->library('email');
		$this->CI->email->initialize($config);
		$this->CI->email->clear(true);
	}

	private function _wrap($subject, $body)
	{
		$brand = $this->CI->setting_model->get('mail_from_name', 'Inventory');
		$html = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
		$html = preg_replace(
			'#(https?://[^\s<]+)#i',
			'<a href="$1" style="color:#1f6f6d;word-break:break-all">$1</a>',
			$html
		);
		return '<div style="font-family:Arial,sans-serif;color:#2A3B52">'
			. '<div style="background:#1F6F6D;color:#fff;padding:16px 20px;font-weight:bold">' . htmlspecialchars($brand) . '</div>'
			. '<div style="padding:20px"><h2 style="color:#C5A059">' . htmlspecialchars($subject) . '</h2>'
			. '<div>' . $html . '</div></div>'
			. '<div style="padding:12px 20px;color:#8A94A6;font-size:12px">Real Estate Inventory Portal</div></div>';
	}
}
