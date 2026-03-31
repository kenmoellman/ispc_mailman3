<?php

/*
	Datatypes:
	- INTEGER
	- DOUBLE
	- CURRENCY
	- VARCHAR
	- TEXT
	- DATE
*/

// Name of the list
$liste["name"]              = "mailmanthree_list";

// Database table
$liste["table"]             = "mailmanthree_list";

// Index index field of the database table
$liste["table_idx"]         = "mailmanthree_list_id";

// Search Field Prefix
$liste["search_prefix"]     = "search_";

// Records per page
$liste["records_per_page"]  = "15";

// Script File of the list
$liste["file"]              = "mailmanthree_list_list.php";

// Script file of the edit form
$liste["edit_file"]         = "mailmanthree_list_edit.php";

// Script File of the delete script
$liste["delete_file"]       = "mailmanthree_list_del.php";

// Paging Template
$liste["paging_tpl"]        = "templates/paging.tpl.htm";

// Enable auth
$liste["auth"]              = "yes";


/*****************************************************
* Search fields
*****************************************************/
$liste["item"][] = array(
	'field'    => "list_name",
	'datatype' => "VARCHAR",
	'formtype' => "TEXT",
	'op'       => "like",
	'prefix'   => "%",
	'suffix'   => "%",
	'width'    => "",
	'value'    => "",
);

$liste["item"][] = array(
	'field'    => "domain",
	'datatype' => "VARCHAR",
	'formtype' => "TEXT",
	'op'       => "like",
	'prefix'   => "%",
	'suffix'   => "%",
	'width'    => "",
	'value'    => "",
);

$liste["item"][] = array(
	'field'    => "member_count",
	'datatype' => "INTEGER",
	'formtype' => "TEXT",
	'op'       => "=",
	'prefix'   => "",
	'suffix'   => "",
	'width'    => "",
	'value'    => "",
);

$liste["item"][] = array(
	'field'    => "active",
	'datatype' => "VARCHAR",
	'formtype' => "SELECT",
	'op'       => "=",
	'prefix'   => "",
	'suffix'   => "",
	'width'    => "",
	'value'    => array('y' => $app->lng('yes_txt'), 'n' => $app->lng('no_txt')),
);
