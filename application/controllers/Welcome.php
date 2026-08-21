<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller
{
	public function index()
	{
		$this->output->set_content_type('application/json')->set_output(json_encode(array(
			'success' => true,
			'product' => 'SYNCR',
			'message' => 'Real Estate Inventory Management API',
			'docs' => base_url('api/docs'),
			'frontend' => 'http://localhost:5173'
		)));
	}
}
