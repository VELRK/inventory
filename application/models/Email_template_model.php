<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Email_template_model extends CI_Model
{
	/**
	 * Who can receive a system email — toggled per template in the UI.
	 */
	public function recipient_catalog()
	{
		return array(
			array(
				'key' => 'target_user',
				'label' => 'Target user only',
				'hint' => 'The related person (invitee, requester, password reset user, etc.)'
			),
			array(
				'key' => 'promoter_admin',
				'label' => 'Promoter admin (super admin)',
				'hint' => 'All active promoter_admin accounts'
			),
			array(
				'key' => 'project_company_users',
				'label' => 'Requesting company · project access',
				'hint' => 'Team admins + users of the requesting marketing company who can access this project'
			),
			array(
				'key' => 'project_all_users',
				'label' => 'All companies · project access',
				'hint' => 'Every team admin/user (any company) who has access to this project'
			),
			array(
				'key' => 'team_admin',
				'label' => 'Company team admins (no project filter)',
				'hint' => 'All marketing_team_admin in the related company'
			),
			array(
				'key' => 'team_user',
				'label' => 'Company team users (no project filter)',
				'hint' => 'All marketing_team_user in the related company'
			),
			array(
				'key' => 'company_all',
				'label' => 'All company users (no project filter)',
				'hint' => 'Every active user in the related marketing company'
			),
			array(
				'key' => 'company_email',
				'label' => 'Company contact email',
				'hint' => 'Email stored on the marketing company record'
			),
			array(
				'key' => 'actor',
				'label' => 'Action performer',
				'hint' => 'The logged-in user who triggered this event'
			),
		);
	}

	/** Sensible default recipient flags per event. */
	public function default_recipients_for($event_key)
	{
		$map = array(
			'auth.forgot' => array('target_user' => true),
			'auth.reset_done' => array('target_user' => true),
			'auth.password_changed' => array('target_user' => true),
			'user.created' => array('target_user' => true),
			'request.submitted' => array('promoter_admin' => true),
			'request.approved' => array('project_company_users' => true),
			'request.rejected' => array('project_company_users' => true),
			'inventory.available' => array('project_all_users' => true),
			'inventory.status' => array('promoter_admin' => true),
			'booking.created' => array('promoter_admin' => true, 'company_email' => true, 'project_company_users' => true),
			'registration.created' => array('promoter_admin' => true),
			'company.created' => array('promoter_admin' => true, 'company_email' => true),
			'mail.test' => array('target_user' => true),
		);
		$base = array();
		foreach ($this->recipient_catalog() as $row) {
			$base[$row['key']] = false;
		}
		$extra = array('extra_emails' => '');
		if (!isset($map[$event_key])) {
			return array_merge($base, $extra);
		}
		return array_merge($base, $map[$event_key], $extra);
	}

	/** Events where target_user must always stay on (security / account emails). */
	public function locked_recipient_keys($event_key)
	{
		$locked = array(
			'auth.forgot' => array('target_user'),
			'auth.reset_done' => array('target_user'),
			'auth.password_changed' => array('target_user'),
			'user.created' => array('target_user'),
			'mail.test' => array('target_user'),
		);
		return isset($locked[$event_key]) ? $locked[$event_key] : array();
	}

	public function defaults()
	{
		$rows = array(
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
				'name' => 'Block request → Super Admin',
				'subject' => 'Action Required: New Plot Block Request – {projectName} / Plot {siteNumber}',
				'body' => "A new plot block request has been submitted by {marketingAdminName} from {marketingCompanyName}.\n\nProject: {projectName}\nPlot / Site No.: {siteNumber}\nRequested By: {marketingAdminName}\nMarketing Company: {marketingCompanyName}\nRequested On: {requestDate}\n\nThe request is currently Pending Approval. Please review and Approve or Reject it from the Syncr admin portal.\n\nOpen requests: {link}",
				'placeholders' => 'projectName, siteNumber, marketingAdminName, marketingCompanyName, requestDate, link'
			),
			array(
				'event_key' => 'request.approved',
				'name' => 'Block approved → Requesting company',
				'subject' => '{projectName} – Plot {siteNumber} Booked by Your Marketing Team',
				'body' => "This is to inform you that Plot {siteNumber} in {projectName} has been booked by your marketing team, {marketingCompanyName}.\n\nProject: {projectName}\nPlot / Site No.: {siteNumber}\nMarketing Team: {marketingCompanyName}\nBooked By: {marketingUserName}\nBooking Date: {bookingDate}\n\nThe plot status has been updated to Booked / On Hold in the project inventory. Please consider this plot unavailable for further booking requests from your marketing team.\n\nView inventory: {link}",
				'placeholders' => 'projectName, siteNumber, marketingCompanyName, marketingUserName, bookingDate, link'
			),
			array(
				'event_key' => 'request.rejected',
				'name' => 'Block rejected → Requesting company',
				'subject' => 'Plot {siteNumber} Block Request Rejected – {projectName}',
				'body' => "This is to inform you that the block request for Plot {siteNumber} in {projectName}, submitted by {marketingCompanyName}, has been rejected by the Super Admin.\n\nProject: {projectName}\nPlot / Site No.: {siteNumber}\nMarketing Company: {marketingCompanyName}\nRequested By: {marketingAdminName}\nReviewed By: {superAdminName}\nStatus: Rejected\nReason: {rejectionReason}\n\nThe plot remains available in the project inventory and can be requested again, subject to availability.\n\nView inventory: {link}",
				'placeholders' => 'projectName, siteNumber, marketingCompanyName, marketingAdminName, superAdminName, rejectionReason, link'
			),
			array(
				'event_key' => 'inventory.available',
				'name' => 'Plot now Available → Project access users',
				'subject' => 'Plot {siteNumber} Now Available – {projectName}',
				'body' => "Please be informed that Plot {siteNumber} in {projectName} is now available.\n\nThe plot status has been updated to Available by the Super Admin and is now open for new enquiries, block requests, or bookings, subject to the project's availability rules.\n\nProject: {projectName}\nPlot / Site No.: {siteNumber}\nPrevious Status: {previousStatus}\nCurrent Status: Available\nUpdated By: {superAdminName}\nUpdated On: {updatedDate}\n\nView inventory: {link}",
				'placeholders' => 'projectName, siteNumber, previousStatus, superAdminName, updatedDate, link'
			),
			array(
				'event_key' => 'inventory.status',
				'name' => 'Inventory status update (other)',
				'subject' => 'Unit {siteNumber} is now {currentStatus} – {projectName}',
				'body' => "Hello,\n\nInventory status was updated.\n\nProject: {projectName}\nUnit: {siteNumber}\nPrevious status: {previousStatus}\nNew status: {currentStatus}\nUpdated by: {superAdminName}\n\nView inventory: {link}",
				'placeholders' => 'projectName, siteNumber, previousStatus, currentStatus, superAdminName, status, unit_no, link'
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
		foreach ($rows as &$row) {
			$row['recipients'] = $this->default_recipients_for($row['event_key']);
		}
		unset($row);
		return $rows;
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
			`recipients` TEXT NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`updated_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uk_email_event` (`event_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		// Older installs — add recipients column if missing.
		$cols = $this->db->query("SHOW COLUMNS FROM `email_templates` LIKE 'recipients'")->result();
		if (!$cols) {
			$this->db->query("ALTER TABLE `email_templates` ADD COLUMN `recipients` TEXT NULL AFTER `placeholders`");
		}
	}

	public function ensure_seeded()
	{
		$this->ensure_table();
		// Syncr block/available notification copy + recipients — keep in sync with product rules.
		$force_sync = array(
			'request.submitted',
			'request.approved',
			'request.rejected',
			'inventory.available',
			'inventory.status'
		);
		foreach ($this->defaults() as $row) {
			$exists = $this->db->get_where('email_templates', array('event_key' => $row['event_key']))->row();
			if ($exists) {
				$needsLink = in_array($row['event_key'], array('user.created', 'auth.forgot'), true)
					&& (!$exists->body || strpos($exists->body, '{link}') === false);
				$force = in_array($row['event_key'], $force_sync, true)
					&& (!$exists->body || strpos($exists->body, '{projectName}') === false || strpos($exists->body, '{siteNumber}') === false);
				$needsRecipients = empty($exists->recipients)
					|| (in_array($row['event_key'], $force_sync, true)
						&& $exists->recipients
						&& strpos($exists->recipients, 'project_') === false
						&& in_array($row['event_key'], array('request.approved', 'request.rejected', 'inventory.available'), true));
				$patch = array('updated_at' => now_dt());
				if ($needsLink || $force) {
					$patch['name'] = $row['name'];
					$patch['subject'] = $row['subject'];
					$patch['body'] = $row['body'];
					$patch['placeholders'] = $row['placeholders'];
					$patch['recipients'] = json_encode($row['recipients']);
					$patch['is_active'] = 1;
				} elseif ($needsRecipients) {
					$patch['recipients'] = json_encode($row['recipients']);
				}
				if (count($patch) > 1) {
					$this->db->where('id', (int) $exists->id)->update('email_templates', $patch);
				}
				continue;
			}
			$this->db->insert('email_templates', array(
				'event_key' => $row['event_key'],
				'name' => $row['name'],
				'subject' => $row['subject'],
				'body' => $row['body'],
				'placeholders' => $row['placeholders'],
				'recipients' => json_encode($row['recipients']),
				'is_active' => 1,
				'created_at' => now_dt(),
				'updated_at' => now_dt()
			));
		}
	}

	public function parse_recipients($row)
	{
		$defaults = $this->default_recipients_for($row ? $row->event_key : '');
		$raw = $row && !empty($row->recipients) ? $row->recipients : null;
		$decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
		if (!is_array($decoded)) {
			return $defaults;
		}
		$out = $defaults;
		foreach ($this->recipient_catalog() as $item) {
			$key = $item['key'];
			if (array_key_exists($key, $decoded)) {
				$out[$key] = !empty($decoded[$key]);
			}
		}
		if (isset($decoded['extra_emails'])) {
			$out['extra_emails'] = is_array($decoded['extra_emails'])
				? implode(', ', $decoded['extra_emails'])
				: (string) $decoded['extra_emails'];
		}
		foreach ($this->locked_recipient_keys($row->event_key) as $lock) {
			$out[$lock] = true;
		}
		return $out;
	}

	public function normalize_recipients_input($event_key, $input)
	{
		$out = $this->default_recipients_for($event_key);
		if (!is_array($input)) {
			return $out;
		}
		foreach ($this->recipient_catalog() as $item) {
			$key = $item['key'];
			if (array_key_exists($key, $input)) {
				$out[$key] = !empty($input[$key]);
			}
		}
		if (array_key_exists('extra_emails', $input)) {
			$out['extra_emails'] = is_array($input['extra_emails'])
				? implode(', ', $input['extra_emails'])
				: trim((string) $input['extra_emails']);
		}
		foreach ($this->locked_recipient_keys($event_key) as $lock) {
			$out[$lock] = true;
		}
		return $out;
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
