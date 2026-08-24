<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Projects extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('project_model', 'inventory_model'));
	}

	public function index()
	{
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->inventory_model->sync_status_from_transactions();
			list($page, $limit, $offset) = pagination_params(12);
			$filters = array(
				'q' => request_value('q'),
				'status' => request_value('status')
			);
			list($items, $total) = $this->project_model->list_filtered($filters, $limit, $offset, $this->allowed_project_ids());
			$this->api_response->paginated($items, $total, $page, $limit);
		}
		if ($method === 'POST') {
			$this->require_permission('projects.manage');
			$data = $this->_payload();
			if ($data['name'] === '' || $data['city'] === '') {
				$this->api_response->validation(array('name' => 'Name is required.', 'city' => 'City is required.'));
			}
			$data['created_at'] = now_dt();
			$this->db->insert('projects', $data);
			$id = $this->db->insert_id();
			$this->log_activity('project.create', 'Created project ' . $data['name'], 'projects', $id);
			$this->api_response->ok($this->project_model->with_stats($this->project_model->find($id)), 'Project created.', 201);
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function item($id)
	{
		$project = $this->project_model->find($id);
		if (!$project) {
			$this->api_response->error('NOT_FOUND', 'Project not found.', 404);
		}
		$allowed = $this->allowed_project_ids();
		if ($allowed !== null && !in_array((int) $id, $allowed, true)) {
			$this->api_response->error('FORBIDDEN', 'You cannot access this project.', 403);
		}
		$method = $this->http_method();
		if ($method === 'GET') {
			$this->api_response->ok($this->project_model->with_stats($project));
		}
		if ($method === 'PUT' || $method === 'POST') {
			$this->require_permission('projects.manage');
			$data = $this->_payload($project);
			$data['updated_at'] = now_dt();
			$this->db->where('id', (int) $id)->update('projects', $data);
			$this->log_activity('project.update', 'Updated project ' . $project->name, 'projects', $id);
			$this->api_response->ok($this->project_model->with_stats($this->project_model->find($id)), 'Project updated.');
		}
		if ($method === 'DELETE') {
			$this->require_permission('projects.manage');
			$this->db->where('id', (int) $id)->update('projects', array('deleted_at' => now_dt(), 'status' => 'inactive'));
			$this->log_activity('project.archive', 'Archived project ' . $project->name, 'projects', $id);
			$this->api_response->ok(array(), 'Project archived.');
		}
		$this->api_response->error('METHOD_NOT_ALLOWED', 'Unsupported method.', 405);
	}

	public function assign($id)
	{
		$this->require_permission('projects.manage');
		$ids = request_value('company_ids', array());
		$this->db->where('project_id', (int) $id)->delete('company_project_assignments');
		foreach ((array) $ids as $cid) {
			$this->db->insert('company_project_assignments', array(
				'company_id' => (int) $cid,
				'project_id' => (int) $id,
				'created_at' => now_dt()
			));
		}
		$this->api_response->ok(array(), 'Assignments saved.');
	}

	private function _payload($project = null)
	{
		$body = json_body();
		$cover = $this->_resolve_cover($project, $body);
		return array(
			'name' => trim((string) request_value('name', $project ? $project->name : '')),
			'location' => trim((string) request_value('location', $project ? $project->location : '')),
			'city' => trim((string) request_value('city', $project ? $project->city : '')),
			'project_type' => request_value('project_type', $project ? $project->project_type : 'Residential Plot'),
			'description' => request_value('description', $project ? $project->description : null),
			'approval_details' => request_value('approval_details', $project ? $project->approval_details : null),
			'contact_name' => request_value('contact_name', $project ? $project->contact_name : null),
			'contact_phone' => request_value('contact_phone', $project ? $project->contact_phone : null),
			'contact_email' => request_value('contact_email', $project ? $project->contact_email : null),
			'cover_image' => $cover,
			'status' => request_value('status', $project ? $project->status : 'active')
		);
	}

	/**
	 * Prefer real multipart file upload (Flutter / form-data).
	 * Fallback: base64 data URI. Do not require a pre-uploaded path.
	 */
	private function _resolve_cover($project = null, $body = array())
	{
		$file = request_uploaded_image(array('cover_image', 'coverImage', 'file', 'image', 'photo', 'cover'));
		if ($file) {
			$err = null;
			$stored = store_uploaded_file($file, 'projects', $err);
			if ($stored === false) {
				$this->api_response->validation(array('cover_image' => $err ?: 'Cover image upload failed.'));
			}
			return $stored;
		}

		$hasCover = array_key_exists('cover_image', $body)
			|| array_key_exists('coverImage', $body)
			|| array_key_exists('cover_image_base64', $body)
			|| array_key_exists('coverImageBase64', $body)
			|| (isset($_POST['cover_image']) && $_POST['cover_image'] !== '')
			|| (isset($_POST['clear_cover_image']) && (string) $_POST['clear_cover_image'] === '1');

		if (!$hasCover) {
			$existing = $project ? $project->cover_image : null;
			if ($existing && (stripos($existing, 'data:') === 0 || strlen($existing) > 240)) {
				return null;
			}
			return $existing;
		}

		$rawCover = '';
		foreach (array('cover_image', 'coverImage', 'cover_image_base64', 'coverImageBase64') as $k) {
			if (array_key_exists($k, $body) && $body[$k] !== null && $body[$k] !== '') {
				$rawCover = (string) $body[$k];
				break;
			}
		}
		if ($rawCover === '' && isset($_POST['cover_image'])) {
			$rawCover = (string) $_POST['cover_image'];
		}
		if ($rawCover === '' || (isset($_POST['clear_cover_image']) && (string) $_POST['clear_cover_image'] === '1')) {
			return null;
		}
		// Ignore clients re-posting a truncated data: URI already in DB.
		if (stripos($rawCover, 'data:') === 0 && strlen($rawCover) < 500) {
			$existing = $project ? $project->cover_image : null;
			return ($existing && stripos($existing, 'data:') !== 0) ? $existing : null;
		}
		$err = null;
		$stored = store_image_input($rawCover, 'projects', $err);
		if ($stored === false) {
			$this->api_response->validation(array('cover_image' => $err ?: 'Invalid cover image.'));
		}
		return $stored;
	}
}
