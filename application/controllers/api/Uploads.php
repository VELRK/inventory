<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Uploads extends Api_Controller
{
	const MAX_BYTES = 4194304;
	const MAX_LABEL = '4 MB';

	public function index()
	{
		$folder = (string) request_value('folder', 'projects');
		$allowed_folders = array('projects', 'users', 'units');
		if (!in_array($folder, $allowed_folders, true)) {
			$this->api_response->error('VALIDATION_ERROR', 'Invalid upload folder. Use projects, users, or units.', 422, array('folder' => 'Invalid folder.'));
		}
		if ($folder !== 'users') {
			$this->require_roles(array('promoter_admin'));
		}

		if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
			$this->api_response->error('VALIDATION_ERROR', 'Please choose an image to upload.', 422, array('file' => 'Please choose an image to upload.'));
		}

		$file = $_FILES['file'];
		$php_error = (int) $file['error'];
		if ($php_error === UPLOAD_ERR_NO_FILE) {
			$this->api_response->error('VALIDATION_ERROR', 'Please choose an image to upload.', 422, array('file' => 'Please choose an image to upload.'));
		}
		if (in_array($php_error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
			$this->api_response->error('FILE_TOO_LARGE', 'The file is too large. Maximum size is ' . self::MAX_LABEL . '.', 422, array(
				'file' => 'Maximum size is ' . self::MAX_LABEL . '.'
			));
		}
		if ($php_error !== UPLOAD_ERR_OK) {
			$this->api_response->error('UPLOAD_FAILED', 'The file could not be uploaded. Please try again.', 400, array('file' => 'Upload failed.'));
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

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($file['tmp_name']);
		$allowed_mime = array('image/jpeg' => true, 'image/png' => true, 'image/webp' => true);
		if (!isset($allowed_mime[$mime])) {
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

		$slug = slugify_filename($file['name']);
		$prefix = $folder . '_' . $slug . '_';
		$existing = glob($dir . $prefix . '*');
		$version = is_array($existing) ? count($existing) + 1 : 1;
		$stored = $prefix . date('YmdHis') . '_v' . $version . '.' . $ext;
		$dest = $dir . $stored;

		if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $dest)) {
			$this->api_response->error('UPLOAD_FAILED', 'The image could not be saved on the server. Please try again.', 500);
		}

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
			'mime' => $mime,
			'width' => isset($info[0]) ? (int) $info[0] : null,
			'height' => isset($info[1]) ? (int) $info[1] : null
		), 'Image uploaded (version ' . $version . ').');
	}
}
