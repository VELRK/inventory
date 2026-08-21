<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mailer
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('setting_model');
	}

	public function send($to, $subject, $body, $event = null)
	{
		$status = 'queued';
		$error = null;
		$enabled = $this->CI->setting_model->get('mail_enabled', '0') === '1';

		if ($enabled) {
			$this->_configure();
			$this->CI->email->from(
				$this->CI->setting_model->get('mail_from_email', 'noreply@syncr.test'),
				$this->CI->setting_model->get('mail_from_name', 'SYNCR Portal')
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

	public function notify_event($event, $to, $context = array())
	{
		$templates = array(
			'user.created' => array('Welcome to SYNCR', 'Your SYNCR account is ready. Email: {email}'),
			'auth.forgot' => array('Reset your SYNCR password', 'Use this reset token: {token}. It expires in 60 minutes.'),
			'request.submitted' => array('New block request', 'A block request was submitted for unit {unit_no} ({project}) by {company}.'),
			'request.approved' => array('Block request approved', 'Your block request for {unit_no} was approved.'),
			'request.rejected' => array('Block request rejected', 'Your block request for {unit_no} was rejected. Notes: {notes}'),
			'inventory.status' => array('Inventory status updated', 'Unit {unit_no} is now {status}.'),
			'booking.created' => array('New booking recorded', 'Booking created for {customer} on unit {unit_no}. Amount {amount}.'),
			'registration.created' => array('New registration recorded', 'Registration created for {customer} on unit {unit_no}.'),
			'company.created' => array('Marketing company added', 'Company {name} has been added to SYNCR.'),
			'mail.test' => array('SYNCR mail test', 'This is a test email from SYNCR. Mail configuration is working.')
		);

		if (!isset($templates[$event])) {
			return false;
		}
		list($subject, $tpl) = $templates[$event];
		$body = $tpl;
		foreach ($context as $key => $value) {
			$body = str_replace('{' . $key . '}', (string) $value, $body);
		}
		return $this->send($to, $subject, $body, $event);
	}

	private function _configure()
	{
		$config = array(
			'protocol' => $this->CI->setting_model->get('mail_protocol', 'smtp'),
			'smtp_host' => $this->CI->setting_model->get('mail_smtp_host', 'smtp.gmail.com'),
			'smtp_port' => (int) $this->CI->setting_model->get('mail_smtp_port', '587'),
			'smtp_user' => $this->CI->setting_model->get('mail_smtp_user', ''),
			'smtp_pass' => $this->CI->setting_model->get('mail_smtp_pass', ''),
			'smtp_crypto' => $this->CI->setting_model->get('mail_smtp_crypto', 'tls'),
			'mailtype' => 'html',
			'charset' => 'utf-8',
			'newline' => "\r\n",
			'crlf' => "\r\n"
		);
		$this->CI->email->initialize($config);
		$this->CI->email->clear(true);
	}

	private function _wrap($subject, $body)
	{
		return '<div style="font-family:Arial,sans-serif;color:#2A3B52">'
			. '<div style="background:#1F6F6D;color:#fff;padding:16px 20px;font-weight:bold">SYNCR</div>'
			. '<div style="padding:20px"><h2 style="color:#C5A059">' . htmlspecialchars($subject) . '</h2>'
			. '<p>' . nl2br(htmlspecialchars($body)) . '</p></div>'
			. '<div style="padding:12px 20px;color:#8A94A6;font-size:12px">Real Estate Inventory Portal</div></div>';
	}
}
