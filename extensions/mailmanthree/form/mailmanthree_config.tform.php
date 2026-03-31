<?php

/*
	Form Definition — Mailman3 Configuration

	Tabledefinition

	Datatypes:
	- INTEGER (Forces the input to Int)
	- DOUBLE
	- CURRENCY (Formats the values to currency notation)
	- VARCHAR (no format check, maxlength: 255)
	- TEXT (no format check)
	- DATE (Dateformat, automatic conversion to timestamps)

	Formtype:
	- TEXT (Textfield)
	- TEXTAREA (Textarea)
	- PASSWORD (Password textfield, input is not shown when edited)
	- SELECT (Select option field)
	- RADIO
	- CHECKBOX
	- CHECKBOXARRAY
	- FILE

	VALUE:
	- Wert oder Array

	Hint:
	The ID field of the database table is not part of the datafield definition.
	The ID field must be always auto increment (int or bigint).
*/

$form["title"]          = "Mailman3 Settings";
$form["description"]    = "";
$form["name"]           = "mailmanthree_config";
$form["action"]         = "mailmanthree_config_edit.php";
$form["db_table"]       = "mailmanthree_config";
$form["db_table_idx"]   = "mailmanthree_config_id";
$form["db_history"]     = "no";
$form["tab_default"]    = "config";
$form["list_default"]   = "mailmanthree_list_list.php";
$form["auth"]           = 'no';

$form["auth_preset"]["userid"]     = 0;
$form["auth_preset"]["groupid"]    = 0;
$form["auth_preset"]["perm_user"]  = 'riud';
$form["auth_preset"]["perm_group"] = '';
$form["auth_preset"]["perm_other"] = '';

$form["tabs"]['config'] = array(
	'title'    => "Mailman3 Settings",
	'width'    => 100,
	'template' => "templates/mailmanthree_config_edit.htm",
	'fields'   => array(
		//#################################
		// Begin Datatable fields
		//#################################
		'api_url' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'TEXT',
			'default'    => 'http://127.0.0.1:8001/3.1',
			'validators' => array(
				0 => array(
					'type'   => 'NOTEMPTY',
					'errmsg' => 'api_url_error_empty',
				),
			),
			'value'     => '',
			'width'     => '40',
			'maxlength' => '255',
		),
		'api_user' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'TEXT',
			'default'    => 'restadmin',
			'validators' => array(
				0 => array(
					'type'   => 'NOTEMPTY',
					'errmsg' => 'api_user_error_empty',
				),
			),
			'value'     => '',
			'width'     => '30',
			'maxlength' => '255',
		),
		'api_pass' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'PASSWORD',
			'encryption' => 'CLEARTEXT',
			'default'    => '',
			'validators' => array(
				0 => array(
					'type'   => 'NOTEMPTY',
					'errmsg' => 'api_pass_error_empty',
				),
			),
			'value'     => '',
			'width'     => '30',
			'maxlength' => '255',
		),
		'postorius_url' => array(
			'datatype'  => 'VARCHAR',
			'formtype'  => 'TEXT',
			'default'   => '/postorius',
			'value'     => '',
			'width'     => '40',
			'maxlength' => '255',
		),
		'hyperkitty_url' => array(
			'datatype'  => 'VARCHAR',
			'formtype'  => 'TEXT',
			'default'   => '/hyperkitty',
			'value'     => '',
			'width'     => '40',
			'maxlength' => '255',
		),
		//#################################
		// END Datatable fields
		//#################################
	),
);
