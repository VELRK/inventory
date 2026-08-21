<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token_model extends CI_Model
{
	public function issue($user_id, $hours = 24)
	{
		$token = bin2hex(random_bytes(32));
		$this->db->insert('api_tokens', array(
			'user_id' => (int) $user_id,
			'token' => $token,
			'expires_at' => date('Y-m-d H:i:s', time() + ($hours * 3600)),
			'created_at' => now_dt()
		));
		return $token;
	}

	public function user_by_token($token)
	{
		if (!$token) {
			return null;
		}
		return $this->db->select('u.*')
			->from('api_tokens t')
			->join('users u', 'u.id = t.user_id')
			->where('t.token', $token)
			->where('t.expires_at >=', now_dt())
			->where('u.deleted_at IS NULL', null, false)
			->get()->row();
	}

	public function revoke($token)
	{
		if (!$token) {
			return;
		}
		$this->db->where('token', $token)->delete('api_tokens');
	}
}
