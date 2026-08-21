<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Email_template_model extends CI_Model
{
	public function defaults()
	{
		return array(
			array(
				'event_key' => 'auth.forgot',
				'name' => 'Password reset request',
				'subject' => 'Set your Inventory password',
				'body' => "Hello {name},\n\nUse this link to set or reset your Inventory password (valid {expires}):\n{link}\n\nLogin email: {email}\n\nSign in after setting the password:\n{login_link}\n\nIf you did not request this, you can ignore this email.",
				'placeholders' => 'name, email, link, expires, token, login_link'
			),
			array(
				'event_key' => 'auth.reset_done',
				'name' => 'Password reset confirmation',
				'subject' => 'Your Inventory password was updated',
				'body' => "Hello {name},\n\nYour password was set successfully.\n\nSign in here:\n{login_link}\n\nIf you did not do this, contact your administrator immediately.",
				'placeholders' => 'name, login_link'
			),
			array(
				'event_key' => 'auth.password_changed',
				'name' => 'Password change confirmation',
				'subject' => 'Your Inventory password was changed',
				'body' => "Hello {name},\n\nYour account password was changed from the Inventory portal.\n\nIf this was not you, reset your password from the login page immediately.",
				'placeholders' => 'name'
			),
			array(
				'event_key' => 'user.created',
				'name' => 'New user welcome',
				'subject' => 'Set your Inventory password',
				'body' => "Hello {name},\n\nYour Inventory account was created.\nLogin email: {email}\n\nUse this link to set your password (valid {expires}):\n{link}\n\nThen sign in here:\n{login_link}\n\nIf you did not expect this email, contact your administrator.",
				'placeholders' => 'name, email, link, expires, token, login_link'
			),
			array(
				'event_key' => 'request.submitted',
				'name' => 'Hold request submitted (admin)',
				'subject' => 'New hold request · {unit_no}',
				'body' => "Hello,\n\nA hold request was submitted.\n\nUnit: {unit_no}\nProject: {project}\nCompany: {company}\n\nPlease review it in the Inventory portal.",
				'placeholders' => 'unit_no, project, company'
			),
			array(
				'event_key' => 'request.approved',
				'name' => 'Hold request approved',
				'subject' => 'Hold request approved · {unit_no}',
				'body' => "Hello,\n\nYour hold request for unit {unit_no} was approved.\nYou can proceed to book the unit with the customer.",
				'placeholders' => 'unit_no, notes'
			),
			array(
				'event_key' => 'request.rejected',
				'name' => 'Hold request rejected',
				'subject' => 'Hold request rejected · {unit_no}',
				'body' => "Hello,\n\nYour hold request for unit {unit_no} was rejected.\nNotes: {notes}",
				'placeholders' => 'unit_no, notes'
			),
			array(
				'event_key' => 'inventory.status',
				'name' => 'Inventory status update',
				'subject' => 'Unit {unit_no} is now {status}',
				'body' => "Hello,\n\nInventory status was updated.\n\nUnit: {unit_no}\nNew status: {status}",
				'placeholders' => 'unit_no, status'
			),
			array(
				'event_key' => 'booking.created',
				'name' => 'Booking created',
				'subject' => 'New booking · {unit_no}',
				'body' => "Hello,\n\nA booking was recorded.\n\nCustomer: {customer}\nUnit: {unit_no}\nAmount: {amount}\n\nThank you.",
				'placeholders' => 'customer, unit_no, amount'
			),
			array(
				'event_key' => 'registration.created',
				'name' => 'Registration created',
				'subject' => 'New registration · {unit_no}',
				'body' => "Hello,\n\nA registration was recorded.\n\nCustomer: {customer}\nUnit: {unit_no}\n\nThank you.",
				'placeholders' => 'customer, unit_no'
			),
			array(
				'event_key' => 'company.created',
				'name' => 'Marketing company added',
				'subject' => 'Company added · {name}',
				'body' => "Hello,\n\nMarketing company {name} has been added to Inventory.",
				'placeholders' => 'name'
			),
			array(
				'event_key' => 'mail.test',
				'name' => 'SMTP test email',
				'subject' => 'Inventory mail test',
				'body' => "Hello,\n\nThis is a test email from Inventory.\nIf you received this, SMTP is working correctly.",
				'placeholders' => ''
			),
		);
	}

	public function ensure_table()
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `email_templates` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`event_key` VARCHAR(80) NOT NULL,
			`name` VARCHAR(120) NOT NULL,
			`subject` VARCHAR(200) NOT NULL,
			`body` TEXT NOT NULL,
			`placeholders` VARCHAR(500) NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`updated_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uk_email_event` (`event_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	public function ensure_seeded()
	{
		$this->ensure_table();
		foreach ($this->defaults() as $row) {
			$exists = $this->db->get_where('email_templates', array('event_key' => $row['event_key']))->row();
			if ($exists) {
				// Keep password-link templates in sync (invite + forgot must include {link}).
				if (in_array($row['event_key'], array('user.created', 'auth.forgot'), true)
					&& (!$exists->body || strpos($exists->body, '{link}') === false)
				) {
					$this->db->where('id', (int) $exists->id)->update('email_templates', array(
						'name' => $row['name'],
						'subject' => $row['subject'],
						'body' => $row['body'],
						'placeholders' => $row['placeholders'],
						'is_active' => 1,
						'updated_at' => now_dt()
					));
				}
				continue;
			}
			$this->db->insert('email_templates', array(
				'event_key' => $row['event_key'],
				'name' => $row['name'],
				'subject' => $row['subject'],
				'body' => $row['body'],
				'placeholders' => $row['placeholders'],
				'is_active' => 1,
				'created_at' => now_dt(),
				'updated_at' => now_dt()
			));
		}
	}

	public function all()
	{
		$this->ensure_seeded();
		return $this->db->order_by('name', 'ASC')->get('email_templates')->result();
	}

	public function find($id)
	{
		return $this->db->get_where('email_templates', array('id' => (int) $id))->row();
	}

	public function find_by_event($event)
	{
		$this->ensure_seeded();
		return $this->db->get_where('email_templates', array('event_key' => $event))->row();
	}

	public function update_template($id, $data)
	{
		$data['updated_at'] = now_dt();
		$this->db->where('id', (int) $id)->update('email_templates', $data);
		return $this->find($id);
	}
}
