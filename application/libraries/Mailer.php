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

	public function notify_event($event, $to, $context = array())
	{
		$fallback = $this->_fallback_templates();
		$row = $this->CI->email_template_model->find_by_event($event);
		if ($row) {
			if ((int) $row->is_active !== 1) {
				return false;
			}
			$subject = $row->subject;
			$tpl = $row->body;
		} elseif (isset($fallback[$event])) {
			list($subject, $tpl) = $fallback[$event];
		} else {
			return false;
		}

		foreach ($context as $key => $value) {
			$subject = str_replace('{' . $key . '}', (string) $value, $subject);
			$tpl = str_replace('{' . $key . '}', (string) $value, $tpl);
		}
		// Always guarantee a clickable set/reset password URL is present.
		if (!empty($context['link'])) {
			$link = (string) $context['link'];
			if (strpos($tpl, $link) === false) {
				$tpl .= "\n\nSet / reset password link (valid " . (isset($context['expires']) ? $context['expires'] : '48 hours') . "):\n" . $link;
			}
		}
		return $this->send($to, $subject, $tpl, $event);
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
