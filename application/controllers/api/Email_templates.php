<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Email_templates extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('email_template_model');
		$this->require_permission('nav.email_templates');
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			$rows = $this->email_template_model->all();
			$list = array();
			foreach ($rows as $row) {
				$list[] = $this->_decorate($row);
			}
			$this->api_response->ok(array(
				'items' => $list,
				'total' => count($list),
				'hint' => 'Placeholders like {name} are replaced when the email is sent.'
			));
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$row = $this->email_template_model->find($id);
		if (!$row) {
			$this->api_response->error('NOT_FOUND', 'Template not found.', 404);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->_decorate($row));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$subject = trim((string) request_value('subject', $row->subject));
			$body = (string) request_value('body', $row->body);
			$name = trim((string) request_value('name', $row->name));
			if ($subject === '' || trim($body) === '') {
				$this->api_response->validation(array(
					'subject' => 'Subject is required.',
					'body' => 'Body is required.'
				));
			}
			$active = request_value('is_active');
			$is_active = $active === null ? (int) $row->is_active : ((int) $active ? 1 : 0);
			$updated = $this->email_template_model->update_template($id, array(
				'name' => $name !== '' ? $name : $row->name,
				'subject' => $subject,
				'body' => $body,
				'is_active' => $is_active
			));
			$this->log_activity('email_template.update', 'Updated email template ' . $row->event_key, 'email_templates', $id);
			$this->api_response->ok($this->_decorate($updated), 'Template saved.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function reset($id)
	{
		$row = $this->email_template_model->find($id);
		if (!$row) {
			$this->api_response->error('NOT_FOUND', 'Template not found.', 404);
		}
		$defaults = $this->email_template_model->defaults();
		$match = null;
		foreach ($defaults as $d) {
			if ($d['event_key'] === $row->event_key) {
				$match = $d;
				break;
			}
		}
		if (!$match) {
			$this->api_response->error('NOT_FOUND', 'No default template for this event.', 404);
		}
		$updated = $this->email_template_model->update_template($id, array(
			'name' => $match['name'],
			'subject' => $match['subject'],
			'body' => $match['body'],
			'placeholders' => $match['placeholders'],
			'is_active' => 1
		));
		$this->log_activity('email_template.reset', 'Reset email template ' . $row->event_key, 'email_templates', $id);
		$this->api_response->ok($this->_decorate($updated), 'Template restored to default.');
	}

	private function _decorate($row)
	{
		return array(
			'id' => (int) $row->id,
			'event_key' => $row->event_key,
			'name' => $row->name,
			'subject' => $row->subject,
			'body' => $row->body,
			'placeholders' => $row->placeholders,
			'is_active' => (int) $row->is_active === 1,
			'updated_at' => $row->updated_at,
			'created_at' => $row->created_at
		);
	}
}
