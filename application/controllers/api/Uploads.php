<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Uploads extends Api_Controller
{
	const MAX_BYTES = 4194304;
	const MAX_LABEL = '4 MB';

	public function index()
	{
		if ($this->http_method() !== 'POST') {
			$this->api_response->error('METHOD_NOT_ALLOWED', 'Use POST multipart/form-data with file + folder.', 405);
		}

		$folder = (string) (
			(isset($_POST['folder']) && $_POST['folder'] !== '') ? $_POST['folder'] : request_value('folder', 'projects')
		);
		$folder = strtolower(trim($folder));
		$allowed_folders = array('projects', 'users', 'units');
		if (!in_array($folder, $allowed_folders, true)) {
			$this->api_response->error('VALIDATION_ERROR', 'Invalid upload folder. Use projects, users, or units.', 422, array('folder' => 'Invalid folder.'));
		}
		if ($folder === 'users') {
			// Profile photos: any logged-in user can upload.
		} elseif ($folder === 'projects') {
			$this->require_permission('projects.manage');
		} elseif ($folder === 'units') {
			$this->require_permission('inventory.edit');
		}

		$file = null;
		foreach (array('file', 'image', 'photo', 'avatar', 'cover') as $key) {
			if (!empty($_FILES[$key]) && isset($_FILES[$key]['error'])) {
				$file = $_FILES[$key];
				break;
			}
		}
		if (!$file) {
			$this->api_response->error('VALIDATION_ERROR', 'Please choose an image to upload.', 422, array('file' => 'Please choose an image to upload.'));
		}

		$php_error = (int) $file['error'];
		if ($php_error === UPLOAD_ERR_NO_FILE) {
			$this->api_response->error('VALIDATION_ERROR', 'Please choose an image to upload.', 422, array('file' => 'Please choose an image to upload.'));
		}
		if (in_array($php_error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
			$this->api_response->error('FILE_TOO_LARGE', 'The file is too large. Maximum size is ' . self::MAX_LABEL . '.', 422, array(
				'file' => 'Maximum size is ' . self::MAX_LABEL . '.'
			));
		}
		if ($php_error === UPLOAD_ERR_PARTIAL) {
			$this->api_response->error('UPLOAD_FAILED', 'Upload was interrupted. Please try again.', 422, array('file' => 'Partial upload.'));
		}
		if ($php_error === UPLOAD_ERR_NO_TMP_DIR) {
			$this->api_response->error('UPLOAD_FAILED', 'Server temp folder is missing. Contact hosting support.', 500, array('file' => 'No tmp dir.'));
		}
		if ($php_error === UPLOAD_ERR_CANT_WRITE) {
			$this->api_response->error('UPLOAD_FAILED', 'Server could not write the temp file. Check disk space / permissions.', 500, array('file' => 'Cant write.'));
		}
		if ($php_error !== UPLOAD_ERR_OK) {
			$this->api_response->error('UPLOAD_FAILED', 'The file could not be uploaded (PHP error ' . $php_error . ').', 422, array('file' => 'Upload failed.'));
		}

		$size = (int) $file['size'];
		if ($size <= 0) {
			$this->api_response->error('VALIDATION_ERROR', 'The selected file is empty.', 422, array('file' => 'The selected file is empty.'));
		}
		if ($size > self::MAX_BYTES) {
			$mb = round($size / 1048576, 1);
			$this->api_response->error('FILE_TOO_LARGE', 'The file is ' . $mb . ' MB. Maximum size is ' . self::MAX_LABEL . '. Compress the image and try again.', 422, array(
				'file' => 'Maximum size is ' . self::MAX_LABEL . '.'
			));
		}

		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$allowed_ext = array('jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true);
		if ($ext === '' || !isset($allowed_ext[$ext])) {
			$this->api_response->error('INVALID_TYPE', 'Invalid file type. Upload a JPG, PNG, or WEBP image.', 422, array(
				'file' => 'Allowed types: JPG, PNG, WEBP.'
			));
		}
		if ($ext === 'jpeg') {
			$ext = 'jpg';
		}

		$mime = null;
		if (class_exists('finfo')) {
			$finfo = @new finfo(FILEINFO_MIME_TYPE);
			if ($finfo) {
				$mime = @$finfo->file($file['tmp_name']);
			}
		}
		if (!$mime && function_exists('mime_content_type')) {
			$mime = @mime_content_type($file['tmp_name']);
		}
		$allowed_mime = array('image/jpeg' => true, 'image/png' => true, 'image/webp' => true);
		if ($mime && !isset($allowed_mime[$mime])) {
			$this->api_response->error('INVALID_TYPE', 'Invalid file type (' . $mime . '). Upload a JPG, PNG, or WEBP image.', 422, array(
				'file' => 'Allowed types: JPG, PNG, WEBP.'
			));
		}

		$info = @getimagesize($file['tmp_name']);
		if ($info === false) {
			$this->api_response->error('INVALID_TYPE', 'This file is not a valid image. Upload a JPG, PNG, or WEBP file.', 422, array(
				'file' => 'File is not a valid image.'
			));
		}

		$dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;
		if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
			$this->api_response->error('UPLOAD_FAILED', 'Upload folder could not be created. Check folder permissions on uploads/' . $folder . '.', 500);
		}
		if (!is_writable($dir)) {
			@chmod($dir, 0775);
			if (!is_writable($dir)) {
				$this->api_response->error('UPLOAD_FAILED', 'Upload folder is not writable: uploads/' . $folder . '. Set chmod 775 on the host.', 500);
			}
		}

		$slug = slugify_filename($file['name']);
		$prefix = $folder . '_' . $slug . '_';
		$existing = glob($dir . $prefix . '*');
		$version = is_array($existing) ? count($existing) + 1 : 1;
		$stored = $prefix . date('YmdHis') . '_v' . $version . '.' . $ext;
		$dest = $dir . $stored;

		if (!is_uploaded_file($file['tmp_name']) || !@move_uploaded_file($file['tmp_name'], $dest)) {
			$this->api_response->error('UPLOAD_FAILED', 'The image could not be saved on the server. Please try again.', 500);
		}
		@chmod($dest, 0644);

		$path = 'uploads/' . $folder . '/' . $stored;
		$this->log_activity('upload.create', 'Uploaded ' . $stored . ' (v' . $version . ')', $folder, 0);
		$this->api_response->ok(array(
			'path' => $path,
			'url' => media_url($path),
			'folder' => $folder,
			'original_name' => $file['name'],
			'stored_name' => $stored,
			'version' => $version,
			'size' => $size,
			'mime' => $mime ?: (isset($info['mime']) ? $info['mime'] : null),
			'width' => isset($info[0]) ? (int) $info[0] : null,
			'height' => isset($info[1]) ? (int) $info[1] : null
		), 'Image uploaded (version ' . $version . ').');
	}
}
