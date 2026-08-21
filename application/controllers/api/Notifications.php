<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifications extends Api_Controller
{
	public function index()
	{
		$rows = $this->db->where('user_id', $this->user_id())
			->order_by('id', 'DESC')
			->limit(30)
			->get('notifications')->result();
		$unread = (int) $this->db->where('user_id', $this->user_id())->where('is_read', 0)->count_all_results('notifications');
		$this->api_response->ok(array('items' => $rows, 'unread' => $unread));
	}

	public function read()
	{
		$id = request_value('id');
		$this->db->where('user_id', $this->user_id());
		if ($id) {
			$this->db->where('id', (int) $id);
		}
		$this->db->update('notifications', array('is_read' => 1));
		$this->api_response->ok(array(), 'Marked as read.');
	}
}
