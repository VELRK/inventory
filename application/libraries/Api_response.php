<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api_response
{
	public function ok($data = array(), $message = 'OK', $code = 200)
	{
		$this->_send(array(
			'success' => true,
			'message' => $message,
			'data' => $data
		), $code);
	}

	public function paginated($items, $total, $page, $limit, $extra = array(), $message = 'OK')
	{
		$payload = array_merge(array(
			'items' => $items,
			'total' => (int) $total,
			'page' => (int) $page,
			'limit' => (int) $limit,
			'pages' => $limit > 0 ? (int) ceil($total / $limit) : 0
		), $extra);
		$this->ok($payload, $message);
	}

	public function error($code_key, $message, $http = 400, $details = null)
	{
		$payload = array(
			'success' => false,
			'error' => array(
				'code' => $code_key,
				'message' => $message
			)
		);
		if ($details !== null) {
			$payload['error']['details'] = $details;
		}
		$this->_send($payload, $http);
	}

	public function validation($errors)
	{
		$this->error('VALIDATION_ERROR', 'Please correct the highlighted fields.', 422, $errors);
	}

	private function _send($payload, $http)
	{
		$ci =& get_instance();
		$ci->output
			->set_status_header($http)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$ci->output->_display();
		exit;
	}
}
