<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['api'] = 'api/docs/index';

$route['api/auth/login'] = 'api/auth/login';
$route['api/auth/logout'] = 'api/auth/logout';
$route['api/auth/me'] = 'api/auth/me';
$route['api/auth/forgot'] = 'api/auth/forgot';
$route['api/auth/reset'] = 'api/auth/reset';
$route['api/auth/change-password'] = 'api/auth/change_password';

$route['api/dashboard'] = 'api/dashboard/index';
$route['api/dashboard/charts'] = 'api/dashboard/charts';

$route['api/projects'] = 'api/projects/index';
$route['api/projects/(:num)'] = 'api/projects/item/$1';
$route['api/projects/(:num)/assign'] = 'api/projects/assign/$1';

$route['api/inventory'] = 'api/inventory/index';
$route['api/inventory/stats'] = 'api/inventory/stats';
$route['api/inventory/bulk'] = 'api/inventory/bulk';
$route['api/inventory/(:num)'] = 'api/inventory/item/$1';

$route['api/companies'] = 'api/companies/index';
$route['api/companies/(:num)'] = 'api/companies/item/$1';
$route['api/companies/(:num)/projects'] = 'api/companies/projects/$1';

$route['api/users'] = 'api/users/index';
$route['api/users/(:num)'] = 'api/users/item/$1';

$route['api/requests'] = 'api/requests/index';
$route['api/requests/(:num)'] = 'api/requests/item/$1';
$route['api/requests/(:num)/review'] = 'api/requests/review/$1';

$route['api/bookings'] = 'api/bookings/index';
$route['api/bookings/export'] = 'api/bookings/export';
$route['api/bookings/(:num)'] = 'api/bookings/item/$1';

$route['api/registrations'] = 'api/registrations/index';
$route['api/registrations/export'] = 'api/registrations/export';
$route['api/registrations/(:num)'] = 'api/registrations/item/$1';

$route['api/reports'] = 'api/reports/index';
$route['api/reports/export'] = 'api/reports/export';
$route['api/reports/filters'] = 'api/reports/filters';

$route['api/activity'] = 'api/activity/index';
$route['api/notifications'] = 'api/notifications/index';
$route['api/notifications/read'] = 'api/notifications/read';

$route['api/settings'] = 'api/settings/index';
$route['api/settings/mail-test'] = 'api/settings/mail_test';
$route['api/settings/credentials'] = 'api/settings/credentials';

$route['api/email-templates'] = 'api/email_templates/index';
$route['api/email-templates/(:num)'] = 'api/email_templates/item/$1';
$route['api/email-templates/(:num)/reset'] = 'api/email_templates/reset/$1';

$route['api/schema'] = 'api/schema_studio/full';
$route['api/schema/full'] = 'api/schema_studio/full';
$route['api/schema/tables'] = 'api/schema_studio/tables';
$route['api/schema/columns'] = 'api/schema_studio/columns';
$route['api/schema/add-column'] = 'api/schema_studio/add_column';
$route['api/schema/query'] = 'api/schema_studio/query';
$route['api/schema/logs'] = 'api/schema_studio/logs';
$route['api/schema/delete-data'] = 'api/schema_studio/delete_data';

$route['api/docs'] = 'api/docs/index';
$route['api/docs/catalog'] = 'api/docs/catalog';

$route['api/upload'] = 'api/uploads/index';
