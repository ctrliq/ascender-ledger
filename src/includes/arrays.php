<?php

$compares = array('eq' => 'Equals', 'ne' => 'Not Equals', 'gt' => 'Greater Than', 'lt' => 'Less Than', 'contains' => 'Contains', 'starts' => 'Starts With', 'ends' => 'Ends With');

$yn = array(
	0 => 'No',
	1 => 'Yes'
);

$reoccur = array(86400 => 'Daily', 604800 => 'Weekly', 2538000 => 'Monthly');

/*
	What a user or team can be allowed to do, as in Ascender Ledger Pro.  Administrator allows
	everything.  A user has their own permissions plus those of every enabled team they are in.
*/
$permissions = array(
	'super'          => 'Administrator',
	'changes'        => 'View Changes',
	'facts'          => 'View Facts',
	'packages'       => 'View Packages',
	'services'       => 'View Services',
	'reports'        => 'View Reports',
	'reports_create' => 'Create Reports',
);
