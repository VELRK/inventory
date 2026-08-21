<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Docs extends Api_Controller
{
	protected $require_auth = false;

	public function index()
	{
		$this->catalog();
	}

	public function catalog()
	{
		$base = rtrim(base_url(), '/') . '/api';
		$endpoints = array(
			array(
				'group' => 'Auth',
				'method' => 'POST',
				'path' => '/auth/login',
				'auth' => false,
				'summary' => 'Login with email and password',
				'input' => array('email' => 'admin@syncr.test', 'password' => 'Admin@123'),
				'output' => array('success' => true, 'data' => array('token' => 'hex', 'user' => array('id' => 1, 'role' => 'promoter_admin')))
			),
			array('group' => 'Auth', 'method' => 'POST', 'path' => '/auth/logout', 'auth' => true, 'summary' => 'Revoke current token', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Auth', 'method' => 'GET', 'path' => '/auth/me', 'auth' => true, 'summary' => 'Current user profile', 'input' => new stdClass(), 'output' => array('success' => true, 'data' => array('email' => 'admin@syncr.test'))),
			array('group' => 'Auth', 'method' => 'POST', 'path' => '/auth/forgot', 'auth' => false, 'summary' => 'Email password reset link (all roles: admin, team admin, team user)', 'input' => array('email' => 'admin@syncr.test'), 'output' => array('success' => true)),
			array('group' => 'Auth', 'method' => 'POST', 'path' => '/auth/reset', 'auth' => false, 'summary' => 'Set new password from email link token; sends confirmation mail', 'input' => array('token' => 'abc', 'password' => 'NewPass@123', 'password_confirm' => 'NewPass@123'), 'output' => array('success' => true)),
			array('group' => 'Auth', 'method' => 'POST', 'path' => '/auth/change-password', 'auth' => true, 'summary' => 'Change password while logged in; sends confirmation mail', 'input' => array('current_password' => 'Old@123', 'new_password' => 'New@123', 'new_password_confirm' => 'New@123'), 'output' => array('success' => true)),
			array('group' => 'Dashboard', 'method' => 'GET', 'path' => '/dashboard', 'auth' => true, 'summary' => 'KPI cards and recent projects', 'input' => new stdClass(), 'output' => array('total_projects' => 3, 'inventory' => array('available' => 6))),
			array('group' => 'Dashboard', 'method' => 'GET', 'path' => '/dashboard/charts', 'auth' => true, 'summary' => 'Chart series for status pie, project bars, monthly bookings', 'input' => new stdClass(), 'output' => array('status_pie' => array())),
			array('group' => 'Projects', 'method' => 'GET', 'path' => '/projects', 'auth' => true, 'summary' => 'Paginated projects. Query: q, status, page, limit', 'input' => array('q' => 'Royal', 'page' => 1), 'output' => array('items' => array(), 'total' => 3)),
			array('group' => 'Projects', 'method' => 'POST', 'path' => '/projects', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Create project', 'input' => array('name' => 'Sunset Heights', 'city' => 'Coimbatore', 'location' => 'Avinashi Road'), 'output' => array('id' => 4)),
			array('group' => 'Projects', 'method' => 'GET', 'path' => '/projects/{id}', 'auth' => true, 'summary' => 'Project detail with inventory counts', 'input' => new stdClass(), 'output' => array('name' => 'Royal City', 'counts' => array())),
			array('group' => 'Projects', 'method' => 'PUT', 'path' => '/projects/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Update project', 'input' => array('description' => 'Updated'), 'output' => array('success' => true)),
			array('group' => 'Projects', 'method' => 'DELETE', 'path' => '/projects/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Soft-delete (archive) a project', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Inventory', 'method' => 'GET', 'path' => '/inventory', 'auth' => true, 'summary' => 'Units list. Query: project_id, status, q, page', 'input' => array('status' => 'available'), 'output' => array('items' => array(), 'stats' => array())),
			array('group' => 'Inventory', 'method' => 'POST', 'path' => '/inventory', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Create unit', 'input' => array('project_id' => 1, 'unit_no' => 'A-110', 'area_sqft' => 1200, 'price' => 3600000, 'facing' => 'East'), 'output' => array('id' => 13)),
			array('group' => 'Inventory', 'method' => 'GET', 'path' => '/inventory/{id}', 'auth' => true, 'summary' => 'Unit detail', 'input' => new stdClass(), 'output' => array('unit_no' => 'A-101')),
			array('group' => 'Inventory', 'method' => 'PUT', 'path' => '/inventory/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Update unit / status / price', 'input' => array('status' => 'on_hold', 'price' => 3700000), 'output' => array('success' => true)),
			array('group' => 'Inventory', 'method' => 'DELETE', 'path' => '/inventory/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Archive a unit', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Inventory', 'method' => 'GET', 'path' => '/inventory/stats', 'auth' => true, 'summary' => 'Per-project status breakdown', 'input' => new stdClass(), 'output' => array('stats' => array(), 'projects' => array())),
			array('group' => 'Inventory', 'method' => 'POST', 'path' => '/inventory/bulk', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Bulk status update', 'input' => array('ids' => array(1, 2), 'action' => 'change_status', 'status' => 'on_hold', 'remarks' => 'Hold batch'), 'output' => array('updated' => 2)),
			array('group' => 'Companies', 'method' => 'GET', 'path' => '/companies', 'auth' => true, 'summary' => 'List marketing companies. Query: q, status, page, limit', 'input' => array('q' => 'ABC', 'page' => 1, 'limit' => 10), 'output' => array('items' => array(), 'total' => 5)),
			array('group' => 'Companies', 'method' => 'POST', 'path' => '/companies', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Create marketing company', 'input' => array('name' => 'Zenith Realty', 'email' => 'zenith@test.com', 'phone' => '9843000000', 'city' => 'Chennai', 'address' => 'OMR', 'status' => 'active', 'permissions' => array('view_inventory','submit_block_requests','manage_users'), 'project_ids' => array(1)), 'output' => array('id' => 3, 'name' => 'Zenith Realty')),
			array('group' => 'Companies', 'method' => 'GET', 'path' => '/companies/{id}', 'auth' => true, 'summary' => 'Company detail with assigned projects and permissions', 'input' => new stdClass(), 'output' => array('id' => 1, 'name' => 'ABC Realty', 'email' => 'admin@abc.test', 'phone' => '', 'address' => '', 'city' => 'Chennai', 'status' => 'active', 'permissions' => array('view_inventory'), 'project_ids' => array(1))),
			array('group' => 'Companies', 'method' => 'PUT', 'path' => '/companies/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Update company (name, email, phone, address, city, status, permissions, project_ids)', 'input' => array('name' => 'ABC Realty', 'email' => 'admin@abc.test', 'phone' => '9843000001', 'city' => 'Coimbatore', 'address' => 'Avinashi Road', 'status' => 'active', 'permissions' => array('view_inventory','submit_block_requests','manage_users'), 'project_ids' => array(1, 2)), 'output' => array('success' => true)),
			array('group' => 'Companies', 'method' => 'DELETE', 'path' => '/companies/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Soft-delete / archive marketing company', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Users', 'method' => 'GET', 'path' => '/users', 'auth' => true, 'summary' => 'Users (scoped to own company for team admin)', 'input' => array('company_id' => 1), 'output' => array('items' => array())),
			array('group' => 'Users', 'method' => 'POST', 'path' => '/users', 'auth' => true, 'summary' => 'Create user. Team admin can only create team users in own company.', 'input' => array('name' => 'Sales Exec', 'email' => 'exec@abc.test', 'password' => 'TeamUser@123', 'role' => 'marketing_team_user', 'company_id' => 1, 'project_ids' => array(1)), 'output' => array('id' => 5)),
			array('group' => 'Users', 'method' => 'PUT', 'path' => '/users/{id}', 'auth' => true, 'summary' => 'Update user name, phone, status, optional password', 'input' => array('name' => 'Sales Exec', 'status' => 'active'), 'output' => array('success' => true)),
			array('group' => 'Users', 'method' => 'DELETE', 'path' => '/users/{id}', 'auth' => true, 'summary' => 'Soft-delete a user (cannot delete yourself)', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Requests', 'method' => 'GET', 'path' => '/requests', 'auth' => true, 'summary' => 'Block requests. Query: status=pending|approved|rejected', 'input' => array('status' => 'pending'), 'output' => array('items' => array())),
			array('group' => 'Requests', 'method' => 'POST', 'path' => '/requests', 'auth' => true, 'roles' => array('marketing_team_admin', 'marketing_team_user'), 'summary' => 'Submit block request. Unit must be available.', 'input' => array('unit_id' => 1, 'customer_name' => 'Anita', 'customer_phone' => '9843000000', 'expected_booking_date' => '2026-09-01'), 'output' => array('status' => 'pending')),
			array('group' => 'Requests', 'method' => 'PUT', 'path' => '/requests/{id}', 'auth' => true, 'summary' => 'Edit a pending block request', 'input' => array('customer_name' => 'Anita', 'customer_phone' => '9843000000'), 'output' => array('success' => true)),
			array('group' => 'Requests', 'method' => 'DELETE', 'path' => '/requests/{id}', 'auth' => true, 'summary' => 'Delete a pending request and release the unit hold', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Requests', 'method' => 'POST', 'path' => '/requests/{id}/review', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Approve or reject. Approved keeps unit on_hold for booking.', 'input' => array('decision' => 'approved', 'review_notes' => 'Hold 7 days'), 'output' => array('status' => 'approved')),
			array('group' => 'Bookings', 'method' => 'GET', 'path' => '/bookings', 'auth' => true, 'summary' => 'Bookings with filters and total_value', 'input' => array('from' => '2024-05-01', 'to' => '2024-05-31'), 'output' => array('items' => array(), 'total_value' => 0)),
			array('group' => 'Bookings', 'method' => 'POST', 'path' => '/bookings', 'auth' => true, 'roles' => array('promoter_admin', 'marketing_team_admin'), 'summary' => 'Book available or on_hold unit', 'input' => array('unit_id' => 1, 'customer_name' => 'Ravi Kumar', 'amount' => 3600000, 'company_id' => 1), 'output' => array('id' => 3)),
			array('group' => 'Bookings', 'method' => 'PUT', 'path' => '/bookings/{id}', 'auth' => true, 'roles' => array('promoter_admin', 'marketing_team_admin'), 'summary' => 'Update booking customer, amount, status, payment', 'input' => array('status' => 'confirmed', 'payment_status' => 'paid'), 'output' => array('success' => true)),
			array('group' => 'Bookings', 'method' => 'DELETE', 'path' => '/bookings/{id}', 'auth' => true, 'roles' => array('promoter_admin', 'marketing_team_admin'), 'summary' => 'Soft-delete a booking and release the unit if still booked', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Bookings', 'method' => 'GET', 'path' => '/bookings/export', 'auth' => true, 'roles' => array('promoter_admin', 'marketing_team_admin'), 'summary' => 'CSV export of filtered bookings', 'input' => new stdClass(), 'output' => 'text/csv'),
			array('group' => 'Registrations', 'method' => 'GET', 'path' => '/registrations', 'auth' => true, 'summary' => 'Registrations list', 'input' => new stdClass(), 'output' => array('items' => array())),
			array('group' => 'Registrations', 'method' => 'POST', 'path' => '/registrations', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Create registration and set unit registered', 'input' => array('unit_id' => 4, 'customer_name' => 'Suresh Nair', 'amount' => 2800000), 'output' => array('id' => 2)),
			array('group' => 'Registrations', 'method' => 'PUT', 'path' => '/registrations/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Update registration details', 'input' => array('status' => 'confirmed', 'payment_status' => 'paid'), 'output' => array('success' => true)),
			array('group' => 'Registrations', 'method' => 'DELETE', 'path' => '/registrations/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Soft-delete a registration', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Reports', 'method' => 'GET', 'path' => '/reports', 'auth' => true, 'roles' => array('promoter_admin', 'marketing_team_admin'), 'summary' => 'Combined bookings/registrations report with quick stats', 'input' => array('type' => 'bookings', 'company_id' => 1), 'output' => array('quick_stats' => array())),
			array('group' => 'Reports', 'method' => 'GET', 'path' => '/reports/filters', 'auth' => true, 'summary' => 'Dropdown options for report filters', 'input' => new stdClass(), 'output' => array('companies' => array(), 'projects' => array())),
			array('group' => 'Activity', 'method' => 'GET', 'path' => '/activity', 'auth' => true, 'summary' => 'Audit trail', 'input' => array('q' => 'status'), 'output' => array('items' => array())),
			array('group' => 'Settings', 'method' => 'GET', 'path' => '/settings', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Mail config and app settings', 'input' => new stdClass(), 'output' => array('groups' => array())),
			array('group' => 'Settings', 'method' => 'POST', 'path' => '/settings', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Save settings map', 'input' => array('values' => array('mail_smtp_host' => 'smtp.gmail.com', 'mail_enabled' => '1')), 'output' => array('success' => true)),
			array('group' => 'Settings', 'method' => 'GET', 'path' => '/settings/credentials', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Test login emails and passwords', 'input' => new stdClass(), 'output' => array(array('role' => 'Promoter / Admin', 'email' => 'admin@syncr.test', 'password' => 'Admin@123'))),
			array('group' => 'Settings', 'method' => 'POST', 'path' => '/settings/mail-test', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Send/queue a test email', 'input' => array('to' => 'admin@syncr.test'), 'output' => array('queued_or_sent' => true)),
			array('group' => 'Email templates', 'method' => 'GET', 'path' => '/email-templates', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'List editable email templates', 'input' => new stdClass(), 'output' => array('items' => array())),
			array('group' => 'Email templates', 'method' => 'PUT', 'path' => '/email-templates/{id}', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Update subject/body/active', 'input' => array('subject' => 'Reset password', 'body' => 'Hello {name}', 'is_active' => true), 'output' => array('success' => true)),
			array('group' => 'Email templates', 'method' => 'POST', 'path' => '/email-templates/{id}/reset', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Restore default template text', 'input' => new stdClass(), 'output' => array('success' => true)),
			array('group' => 'Uploads', 'method' => 'POST', 'path' => '/upload', 'auth' => true, 'summary' => 'Upload JPG/PNG/WEBP image (max 4 MB). folder=projects|users|units. Stored as folder_name_YYYYMMDDHHMMSS_vN.ext', 'input' => array('folder' => 'projects', 'file' => '(multipart file)'), 'output' => array('path' => 'uploads/projects/projects_cover_20260814172400_v1.jpg', 'url' => 'http://localhost:8080/inventory/uploads/...', 'version' => 1)),
			array('group' => 'Schema', 'method' => 'GET', 'path' => '/schema', 'auth' => true, 'summary' => 'Full database catalog: every table with every column (name, type, nullable, key, default)', 'input' => new stdClass(), 'output' => array('database' => 'syncr_inventory', 'table_count' => 18, 'tables' => array())),
			array('group' => 'Schema', 'method' => 'GET', 'path' => '/schema/tables', 'auth' => true, 'summary' => 'List tables', 'input' => new stdClass(), 'output' => array(array('name' => 'projects'))),
			array('group' => 'Schema', 'method' => 'GET', 'path' => '/schema/columns?table=projects', 'auth' => true, 'summary' => 'List columns for one table', 'input' => array('table' => 'projects'), 'output' => array(array('name' => 'id', 'type' => 'int'))),
			array('group' => 'Schema', 'method' => 'POST', 'path' => '/schema/add-column', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'ALTER TABLE ADD COLUMN (DROP/TRUNCATE blocked)', 'input' => array('table' => 'projects', 'column' => 'rera_no', 'type' => 'VARCHAR', 'length' => '50', 'nullable' => true), 'output' => array('sql' => 'ALTER TABLE ...')),
			array('group' => 'Schema', 'method' => 'POST', 'path' => '/schema/delete-data', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Delete selected rows or clear table data (logged to activity)', 'input' => array('table' => 'mail_logs', 'ids' => array(1, 2), 'confirm' => false), 'output' => array('affected' => 2)),
			array('group' => 'Schema', 'method' => 'POST', 'path' => '/schema/query', 'auth' => true, 'roles' => array('promoter_admin'), 'summary' => 'Run SELECT or DELETE FROM. DROP/TRUNCATE blocked.', 'input' => array('sql' => 'SELECT id, name FROM projects LIMIT 5'), 'output' => array('rows' => array(), 'count' => 0))
		);

		$this->api_response->ok(array(
			'name' => 'SYNCR API',
			'base_url' => $base,
			'auth' => 'Authorization: Bearer {token}',
			'error_shape' => array(
				'success' => false,
				'error' => array('code' => 'VALIDATION_ERROR', 'message' => '...', 'details' => new stdClass())
			),
			'endpoints' => $endpoints
		));
	}
}
