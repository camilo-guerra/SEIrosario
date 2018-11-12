<?php
/**
 * Users module Menu entries
 *
 * @uses $menu global var
 *
 * @see  Menu.php in root folder
 * 
 * @package RosarioSIS
 * @subpackage modules
 */

$menu['Users']['admin'] = array(
	'title' => _( 'Users' ),
	'default' => 'Users/User.php',
	'Users/User.php' => _( 'User Info' ),
	'Users/User.php&staff_id=new' => _( 'Add a User' ),
	'Users/AddStudents.php' => _( 'Associate Students with Parents' ),
	'Users/Preferences.php' => _( 'My Preferences' ),
	1 => _( 'Setup' ),
	'Users/Profiles.php' => _( 'User Profiles' ),
	'Users/Exceptions.php' => _( 'User Permissions' ),
	'Users/UserFields.php' => _( 'User Fields' ),
	2 => _( 'Teacher Programs' ),
);

$menu['Users']['teacher'] = array(
	'title' => _( 'Users' ),
	'default' => 'Users/User.php',
	'Users/User.php' => _( 'User Info' ),
	'Users/Preferences.php' => _( 'My Preferences' )
);

$menu['Users']['parent'] = array(
	'title' => _( 'Users' ),
	'default' => 'Users/User.php',
	'Users/User.php' => _( 'User Info' ),
	'Users/Preferences.php' => _( 'My Preferences' )
);

// FJ enable password change for students
if ( User( 'PROFILE' ) == 'student' )
	unset( $menu['Users']['parent']['Users/User.php'] );

$exceptions['Users'] = array(
	'Users/User.php&staff_id=new' => true
);
