<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$hook['pre_system'][] = array(
	'class'    => 'Cors_hook',
	'function' => 'handle',
	'filename' => 'Cors_hook.php',
	'filepath' => 'hooks'
);
