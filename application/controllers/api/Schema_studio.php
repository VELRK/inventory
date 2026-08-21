<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Schema_studio extends Api_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('schema_guard');
		$read = array('tables', 'columns', 'full', 'index');
		if (!in_array($this->router->method, $read, true)) {
			$this->require_roles(array('promoter_admin'));
		}
	}

	public function index()
	{
		$this->full();
	}

	public function full()
	{
		$db = $this->db->database;
		$tables = $this->db->query(
			'SELECT TABLE_NAME as name, TABLE_ROWS as row_estimate, ENGINE as engine, TABLE_COMMENT as comment
			FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
			array($db)
		)->result();
		$cols = $this->db->query(
			'SELECT TABLE_NAME as table_name, ORDINAL_POSITION as position, COLUMN_NAME as name,
				COLUMN_TYPE as type, DATA_TYPE as data_type, CHARACTER_MAXIMUM_LENGTH as max_length,
				NUMERIC_PRECISION as num_precision, NUMERIC_SCALE as num_scale,
				IS_NULLABLE as nullable, COLUMN_DEFAULT as col_default, COLUMN_KEY as col_key,
				EXTRA as extra, COLUMN_COMMENT as comment
			FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
			array($db)
		)->result();

		$grouped = array();
		foreach ($cols as $col) {
			$table = $col->table_name;
			unset($col->table_name);
			$col->position = (int) $col->position;
			$col->nullable = ($col->nullable === 'YES');
			$col->primary = ($col->col_key === 'PRI');
			$col->max_length = $col->max_length !== null ? (int) $col->max_length : null;
			$grouped[$table][] = $col;
		}

		$list = array();
		$column_count = 0;
		foreach ($tables as $table) {
			$columns = isset($grouped[$table->name]) ? $grouped[$table->name] : array();
			$column_count += count($columns);
			$list[] = array(
				'name' => $table->name,
				'engine' => $table->engine,
				'row_estimate' => (int) $table->row_estimate,
				'comment' => $table->comment,
				'column_count' => count($columns),
				'columns' => $columns
			);
		}

		$this->api_response->ok(array(
			'database' => $db,
			'table_count' => count($list),
			'column_count' => $column_count,
			'tables' => $list
		));
	}

	public function tables()
	{
		$db = $this->db->database;
		$rows = $this->db->query('SELECT TABLE_NAME as name, TABLE_ROWS as row_estimate, ENGINE as engine
			FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', array($db))->result();
		$this->api_response->ok($rows);
	}

	public function columns()
	{
		$table = $this->schema_guard->ident(request_value('table'));
		if ($table === '') {
			$this->api_response->validation(array('table' => 'Table is required.'));
		}
		$db = $this->db->database;
		$rows = $this->db->query(
			'SELECT ORDINAL_POSITION as position, COLUMN_NAME as name, COLUMN_TYPE as type, DATA_TYPE as data_type,
				CHARACTER_MAXIMUM_LENGTH as max_length, IS_NULLABLE as nullable, COLUMN_DEFAULT as col_default,
				COLUMN_KEY as col_key, EXTRA as extra, COLUMN_COMMENT as comment
			FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
			array($db, $table)
		)->result();
		foreach ($rows as $row) {
			$row->position = (int) $row->position;
			$row->nullable = ($row->nullable === 'YES');
			$row->primary = ($row->col_key === 'PRI');
			$row->max_length = $row->max_length !== null ? (int) $row->max_length : null;
		}
		$this->api_response->ok($rows);
	}

	public function add_column()
	{
		$table = request_value('table');
		$column = request_value('column');
		$type = request_value('type', 'VARCHAR');
		$length = request_value('length', '100');
		$nullable = (bool) request_value('nullable', true);
		$default = request_value('default');
		$after = request_value('after');

		list($ok, $sql_or_err) = $this->schema_guard->build_add_column($table, $column, $type, $length, $nullable, $default, $after);
		if (!$ok) {
			$this->_log($table, 'ADD_COLUMN', (string) $sql_or_err, 'blocked', $sql_or_err);
			$this->api_response->error('BLOCKED', $sql_or_err, 400);
		}
		list($safe, $reason) = $this->schema_guard->assert_safe($sql_or_err);
		if (!$safe) {
			$this->_log($table, 'ADD_COLUMN', $sql_or_err, 'blocked', $reason);
			$this->api_response->error('BLOCKED', $reason, 403);
		}
		try {
			$this->db->query($sql_or_err);
			$this->_log($table, 'ADD_COLUMN', $sql_or_err, 'success', 'Column added');
			$this->log_activity('schema.add_column', 'Added column ' . $column . ' to ' . $table, 'schema', 0);
			$this->api_response->ok(array('sql' => $sql_or_err), 'Column added.');
		} catch (Exception $e) {
			$this->_log($table, 'ADD_COLUMN', $sql_or_err, 'failed', $e->getMessage());
			$this->api_response->error('SQL_ERROR', $e->getMessage(), 400);
		}
	}

	public function query()
	{
		$sql = trim((string) request_value('sql'));
		if ($sql === '') {
			$this->api_response->validation(array('sql' => 'SQL is required.'));
		}
		list($safe, $reason) = $this->schema_guard->assert_safe($sql);
		if (!$safe) {
			$this->_log('-', 'QUERY', $sql, 'blocked', $reason);
			$this->api_response->error('BLOCKED', $reason, 403);
		}
		if (!$this->schema_guard->is_allowed_query($sql)) {
			$this->_log('-', 'QUERY', $sql, 'blocked', 'Only SELECT/SHOW/DESCRIBE/EXPLAIN/ALTER ADD COLUMN/DELETE are allowed.');
			$this->api_response->error('BLOCKED', 'Only SELECT, SHOW, DESCRIBE, EXPLAIN, ALTER ADD COLUMN, or DELETE FROM are allowed.', 403);
		}
		$normalized = strtoupper(ltrim($sql));
		if (strpos($normalized, 'ALTER TABLE') === 0 && !preg_match('/ALTER\s+TABLE\s+.+ADD\s+(COLUMN\s+)?/i', $sql)) {
			$this->_log('-', 'QUERY', $sql, 'blocked', 'ALTER is limited to ADD COLUMN.');
			$this->api_response->error('BLOCKED', 'ALTER is limited to ADD COLUMN. DROP/TRUNCATE are blocked.', 403);
		}
		if ($this->schema_guard->is_delete_query($sql) && !preg_match('/^\s*DELETE\s+FROM\s+`?[A-Za-z0-9_]+`?/i', $sql)) {
			$this->_log('-', 'QUERY', $sql, 'blocked', 'Invalid DELETE.');
			$this->api_response->error('BLOCKED', 'DELETE must be DELETE FROM table [WHERE ...].', 403);
		}
		try {
			$query = $this->db->query($sql);
			if ($query === false) {
				$err = $this->db->error();
				$msg = is_array($err) && !empty($err['message']) ? $err['message'] : 'Query failed.';
				$this->_log('-', 'QUERY', $sql, 'failed', $msg);
				$this->api_response->error('SQL_ERROR', $msg, 400);
			}
			if ($this->schema_guard->is_delete_query($sql)) {
				$affected = (int) $this->db->affected_rows();
				$this->_log('-', 'DELETE', $sql, 'success', $affected . ' rows deleted');
				$this->log_activity('schema.delete_data', 'Deleted table data via SQL (' . $affected . ' rows)', 'schema', 0, array('sql' => $sql, 'affected' => $affected));
				$this->api_response->ok(array('rows' => array(), 'count' => 0, 'affected' => $affected), $affected . ' row(s) deleted.');
			}
			$rows = is_object($query) ? $query->result_array() : array();
			$this->_log('-', 'QUERY', $sql, 'success', 'OK');
			$this->api_response->ok(array('rows' => $rows, 'count' => count($rows)));
		} catch (Exception $e) {
			$this->_log('-', 'QUERY', $sql, 'failed', $e->getMessage());
			$this->api_response->error('SQL_ERROR', $e->getMessage(), 400);
		}
	}

	public function delete_data()
	{
		$table = request_value('table');
		$ids = request_value('ids');
		$where = request_value('where', '');
		$confirm = (bool) request_value('confirm', false);
		list($ok, $sql_or_err) = $this->schema_guard->build_delete_data($table, $ids, $where);
		if (!$ok) {
			$this->api_response->error('VALIDATION', $sql_or_err, 400);
		}
		$clear_all = empty($ids) && trim((string) $where) === '';
		if ($clear_all && !$confirm) {
			$this->api_response->error('CONFIRM_REQUIRED', 'Set confirm=true to delete all rows from ' . $table . '.', 400);
		}
		list($safe, $reason) = $this->schema_guard->assert_safe($sql_or_err);
		if (!$safe) {
			$this->_log($table, 'DELETE', $sql_or_err, 'blocked', $reason);
			$this->api_response->error('BLOCKED', $reason, 403);
		}
		try {
			$this->db->query($sql_or_err);
			$affected = (int) $this->db->affected_rows();
			$this->_log($table, 'DELETE', $sql_or_err, 'success', $affected . ' rows deleted');
			$this->log_activity(
				'schema.delete_data',
				'Deleted ' . $affected . ' row(s) from ' . $table,
				'schema',
				0,
				array('table' => $table, 'affected' => $affected, 'sql' => $sql_or_err)
			);
			$this->api_response->ok(array('table' => $table, 'affected' => $affected, 'sql' => $sql_or_err), $affected . ' row(s) deleted from ' . $table . '.');
		} catch (Exception $e) {
			$this->_log($table, 'DELETE', $sql_or_err, 'failed', $e->getMessage());
			$this->api_response->error('SQL_ERROR', $e->getMessage(), 400);
		}
	}

	public function logs()
	{
		$rows = $this->db->order_by('id', 'DESC')->limit(50)->get('schema_change_logs')->result();
		$this->api_response->ok($rows);
	}

	private function _log($table, $op, $sql, $status, $message)
	{
		$this->db->insert('schema_change_logs', array(
			'user_id' => $this->user_id(),
			'table_name' => $table,
			'operation' => $op,
			'sql_text' => $sql,
			'status' => $status,
			'message' => $message,
			'created_at' => now_dt()
		));
	}
}
