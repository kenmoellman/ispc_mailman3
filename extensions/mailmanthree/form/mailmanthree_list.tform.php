<?php

/*
	Form Definition — Mailman3 Mailing List

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

	Search:
	- searchable = 1 or searchable = 2 include the field in the search
	- searchable = 1: this field will be the title of the search result
	- searchable = 2: this field will be included in the description of the search result
*/

$form["title"]          = "Mailing List (Mailman3)";
$form["description"]    = "";
$form["name"]           = "mailmanthree_list";
$form["action"]         = "mailmanthree_list_edit.php";
$form["db_table"]       = "mailmanthree_list";
$form["db_table_idx"]   = "mailmanthree_list_id";
$form["db_history"]     = "no";
$form["tab_default"]    = "list";
$form["list_default"]   = "mailmanthree_list_list.php";
$form["auth"]           = 'yes';

$form["auth_preset"]["userid"]     = 0;
$form["auth_preset"]["groupid"]    = 0;
$form["auth_preset"]["perm_user"]  = 'riud';
$form["auth_preset"]["perm_group"] = 'riud';
$form["auth_preset"]["perm_other"] = '';

$form["tabs"]['list'] = array(
	'title'    => "Mailing List",
	'width'    => 100,
	'template' => "templates/mailmanthree_list_edit.htm",
	'fields'   => array(
		//#################################
		// Begin Datatable fields
		//#################################
		'server_id' => array(
			'datatype'   => 'INTEGER',
			'formtype'   => 'SELECT',
			'default'    => '',
			'datasource' => array(
				'type'       => 'SQL',
				'querystring' => 'SELECT server_id, server_name FROM server WHERE mail_server = 1 AND mirror_server_id = 0 AND {AUTHSQL} ORDER BY server_name',
				'keyfield'   => 'server_id',
				'valuefield' => 'server_name',
			),
			'value' => '',
		),
		'domain' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'SELECT',
			'default'    => '',
			'datasource' => array(
				'type'       => 'SQL',
				'querystring' => "SELECT domain, domain as domain_name FROM mail_domain WHERE {AUTHSQL} AND active = 'y' ORDER BY domain",
				'keyfield'   => 'domain',
				'valuefield' => 'domain_name',
			),
			'validators' => array(
				0 => array(
					'type'   => 'NOTEMPTY',
					'errmsg' => 'domain_error_empty',
				),
			),
			'value'      => '',
			'width'      => '30',
			'maxlength'  => '255',
			'searchable' => 2,
		),
		'list_name' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'TEXT',
			'filters'    => array(
				0 => array(
					'event' => 'SAVE',
					'type'  => 'TOLOWER',
				),
				1 => array(
					'event' => 'SAVE',
					'type'  => 'STRIPTAGS',
				),
				2 => array(
					'event' => 'SAVE',
					'type'  => 'STRIPNL',
				),
			),
			'validators' => array(
				0 => array(
					'type'   => 'NOTEMPTY',
					'errmsg' => 'list_name_error_empty',
				),
				1 => array(
					'type'   => 'REGEX',
					'regex'  => '/^[a-z][a-z0-9\-_]{0,254}$/',
					'errmsg' => 'list_name_error_regex',
				),
			),
			'default'    => '',
			'value'      => '',
			'width'      => '30',
			'maxlength'  => '255',
			'searchable' => 1,
		),
		'list_id' => array(
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default'  => '',
			'value'    => '',
		),
		'fqdn_listname' => array(
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default'  => '',
			'value'    => '',
		),
		'display_name' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'TEXT',
			'default'    => '',
			'value'      => '',
			'width'      => '30',
			'maxlength'  => '255',
		),
		'description' => array(
			'datatype' => 'TEXT',
			'formtype' => 'TEXTAREA',
			'default'  => '',
			'value'    => '',
		),
		'owner_email' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'TEXT',
			'validators' => array(
				0 => array(
					'type'   => 'ISEMAIL',
					'errmsg' => 'owner_email_error_isemail',
				),
			),
			'default'    => '',
			'value'      => '',
			'width'      => '30',
			'maxlength'  => '255',
		),
		'password' => array(
			'datatype'   => 'VARCHAR',
			'formtype'   => 'PASSWORD',
			'encryption' => 'CLEARTEXT',
			'default'    => '',
			'value'      => '',
			'width'      => '30',
			'maxlength'  => '255',
		),
		'active' => array(
			'datatype'  => 'VARCHAR',
			'formtype'  => 'CHECKBOX',
			'default'   => 'y',
			'value'     => array(1 => 'y', 0 => 'n'),
		),
		//#################################
		// END Datatable fields
		//#################################
	),
);
