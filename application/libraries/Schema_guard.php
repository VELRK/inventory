<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Schema_guard
{
	protected $blocked = array('DROP', 'TRUNCATE', 'DELETE', 'REPLACE', 'GRANT', 'REVOKE', 'SHUTDOWN', 'KILL');

	public function is_blocked($sql)
	{
		$normalized = strtoupper(preg_replace('/\s+/', ' ', trim($sql)));
		foreach ($this->blocked as $word) {
			if (preg_match('/\b' . $word . '\b/', $normalized)) {
				return $word;
			}
		}
		if (preg_match('/\bALTER\s+TABLE\b.+\bDROP\b/', $normalized)) {
			return 'DROP COLUMN/INDEX';
		}
		return false;
	}

	public function assert_safe($sql)
	{
		$blocked = $this->is_blocked($sql);
		if ($blocked) {
			return array(false, $blocked . ' statements are not allowed from the frontend schema studio.');
		}
		return array(true, null);
	}

	public function allowed_add_column_types()
	{
		return array(
			'VARCHAR', 'CHAR', 'TEXT', 'MEDIUMTEXT',
			'INT', 'BIGINT', 'SMALLINT', 'TINYINT',
			'DECIMAL', 'FLOAT', 'DOUBLE',
			'DATE', 'DATETIME', 'TIMESTAMP',
			'ENUM', 'BOOLEAN'
		);
	}

	public function ident($value)
	{
		return preg_replace('/[^A-Za-z0-9_]/', '', (string) $value);
	}

	public function build_add_column($table, $column, $type, $length, $nullable, $default, $after)
	{
		$table = $this->ident($table);
		$column = $this->ident($column);
		$type = strtoupper(trim($type));
		$allowed = $this->allowed_add_column_types();
		if (!in_array($type, $allowed, true)) {
			return array(false, 'Column type is not allowed.');
		}
		if ($table === '' || $column === '') {
			return array(false, 'Table and column names are required.');
		}

		$def = $type;
		if (in_array($type, array('VARCHAR', 'CHAR', 'DECIMAL'), true) && $length) {
			$def .= '(' . preg_replace('/[^0-9,]/', '', $length) . ')';
		} elseif ($type === 'ENUM' && $length) {
			$parts = array_map('trim', explode(',', $length));
			$quoted = array();
			foreach ($parts as $part) {
				$quoted[] = "'" . str_replace("'", "''", $part) . "'";
			}
			$def .= '(' . implode(',', $quoted) . ')';
		}

		$sql = 'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $def;
		$sql .= $nullable ? ' NULL' : ' NOT NULL';
		if ($default !== null && $default !== '') {
			if (strtoupper($default) === 'NULL' || strtoupper($default) === 'CURRENT_TIMESTAMP') {
				$sql .= ' DEFAULT ' . $default;
			} else {
				$sql .= " DEFAULT '" . str_replace("'", "''", $default) . "'";
			}
		}
		if ($after) {
			$sql .= ' AFTER `' . $this->ident($after) . '`';
		}
		return array(true, $sql);
	}
}
