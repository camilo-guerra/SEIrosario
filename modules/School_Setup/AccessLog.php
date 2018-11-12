<?php
/**
 * Access Log program
 *
 * @since 3.0
 *
 * Original module:
 * @copyright @dpredster
 * @link https://github.com/dpredster/Access_Log/ (Original extra module, now deprecated)
 *
 * @package RosarioSIS
 * @subpackage modules
 */

require_once 'ProgramFunctions/UserAgent.fnc.php';

DrawHeader( ProgramTitle() );

// Requested start date.
if ( isset( $_REQUEST['day_start'] )
	&& isset( $_REQUEST['month_start'] )
	&& isset( $_REQUEST['year_start'] ) )
{
	$start_date = RequestedDate(
		$_REQUEST['year_start'],
		$_REQUEST['month_start'],
		$_REQUEST['day_start']
	);
}

if ( ! isset( $start_date )
	|| ! $start_date )
{
	// Set start date as the 1st of the month.
	// $start_date = date( 'Y-m' ) . '-01';

	// Set start date as yesterday, prevents having long list on first load.
	$start_date = date( 'Y-m-d', time() - 60 * 60 * 24 );
}

// Requested end date.
if ( isset( $_REQUEST['day_end'] )
	&& isset( $_REQUEST['month_end'] )
	&& isset( $_REQUEST['year_end'] ) )
{
	$end_date = RequestedDate(
		$_REQUEST['year_end'],
		$_REQUEST['month_end'],
		$_REQUEST['day_end']
	);
}

if ( ! isset( $end_date )
	|| ! $end_date )
{
	//  Set end date as current day.
	$end_date = DBDate();
}


if ( $_REQUEST['modfunc'] === 'delete' )
{
	// Prompt before deleting log.
	if ( DeletePrompt( _( 'Access Log' ) ) )
	{
		DBQuery( 'DELETE FROM ACCESS_LOG' );

		$note[] = _( 'Access Log cleared.' );

		// Unset modfunc & redirect URL.
		RedirectURL( 'modfunc' );
	}
}

echo ErrorMessage( $note, 'note' );

if ( ! $_REQUEST['modfunc'] )
{
	echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] . '" method="GET">';

	DrawHeader(
		_( 'From' ) . ' ' . DateInput( $start_date, 'start', '', false, false ) . ' - ' .
		_( 'To' ) . ' ' . DateInput( $end_date, 'end', '', false, false ) .
		Buttons( _( 'Go' ) )
	);

	echo '</form>';

	// Format DB data.
	$alllogs_functions = array(
		'STATUS' => '_makeAccessLogStatus', // Translate status.
		'PROFILE' => '_makeAccessLogProfile', // Translate profile.
		'USERNAME' => '_makeAccessLogUsername', // Add link to user info.
		'LOGIN_TIME' => 'ProperDateTime', // Display localized & preferred Date & Time.
		'USER_AGENT' => '_makeAccessLogUserAgent', // Display Browser & OS.
	);

	$alllogs_RET = DBGet( DBQuery( "SELECT
		DISTINCT USERNAME,PROFILE,LOGIN_TIME,IP_ADDRESS,STATUS,USER_AGENT
		FROM ACCESS_LOG
		WHERE LOGIN_TIME >='" . $start_date . "'
		AND LOGIN_TIME <='" . $end_date . ' 23:59:59' . "'
		ORDER BY LOGIN_TIME DESC" ), $alllogs_functions );

	echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=delete" method="POST">';

	DrawHeader( '', SubmitButton( _( 'Clear Log' ) ) );

	ListOutput(
		$alllogs_RET,
		array(
			'LOGIN_TIME' => _( 'Date' ),
			'USERNAME' => _( 'Username' ),
			'PROFILE' => _( 'User Profile' ),
			'STATUS' => _( 'Status' ),
			'IP_ADDRESS' => _( 'IP Address' ),
			'USER_AGENT' => _( 'Browser' ),
		),
		'Login record',
		'Login records',
		array(),
		array(),
		array( 'count' => true, 'save' => true )
	);

	echo '<div class="center">' . SubmitButton( _( 'Clear Log' ) ) . '</div>';

	echo '</form>';

	// When clicking on Username, go to Student or User Info. ?>
<script>
	$('.al-username').attr('href', function(){
		var url = 'Modules.php?modname=Users/User.php&search_modfunc=list&next_modname=Users/User.php&';

		if ( $(this).hasClass('student') ) {
			url = url.replace( /Users\/User\.php/g, 'Students/Student.php' ) + 'cust[USERNAME]=';
		} else {
			url += 'username=';
		}

		return url + this.firstChild.data;
	});
</script>
	<?php
}


/**
 * Make Status
 * Successful Login or Failed Login
 *
 * Local function
 * DBGet callback
 *
 * @since 3.0
 * @since 3.5 Banned status.
 *
 * @param  string $value   Field value.
 * @param  string $name    'STATUS'.
 *
 * @return string          Success or Banned or Fail.
 */
function _makeAccessLogStatus( $value, $column )
{
	if ( $value === 'B' )
	{
		return '<span style="color: red;">' . _( 'Banned' ) . '</span>';
	}

	if ( $value
		&& $value !== 'Failed Login' ) // Compatibility with version 1.1.
	{
		return _( 'Success' );
	}

	return _( 'Fail' );
}


/**
 * Make Profile
 * Only for successful logins.
 *
 * Local function
 * DBGet callback
 *
 * @since 3.0
 *
 * @param  string $value   Field value.
 * @param  string $name    'PROFILE'.
 *
 * @return string          Student, Administrator, Teacher, Parent, or No Access.
 */
function _makeAccessLogProfile( $value, $column )
{
	$profile_options = array(
		'student' => _( 'Student' ),
		'admin' => _( 'Administrator' ),
		'teacher' => _( 'Teacher' ),
		'parent' => _( 'Parent' ),
		'none' => _( 'No Access' ),
	);

	if ( ! isset( $profile_options[ $value ] ) )
	{
		return '';
	}

	return $profile_options[ $value ];
}


/**
 * Make Username
 * Links to user info page.
 *
 * Local function
 * DBGet callback
 *
 * @since 3.0
 *
 * @param  string $value   Field value.
 * @param  string $name    'USERNAME'.
 *
 * @return string          USername linking to user info page.
 */
function _makeAccessLogUsername( $value, $column )
{
	global $THIS_RET;

	if ( ! $value )
	{
		return '';
	}

	if ( isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		return $value;
	}

	return '<a class="al-username ' .
		( $THIS_RET['PROFILE'] === 'student' ? 'student' : '' ) .
		'" href="#">' . $value . '</a>';
}


/**
 * Make User Agent
 *
 * Local function
 * DBGet callback
 *
 * @since 3.0
 *
 * @link http://php.net/get-browser
 *
 * @param  string $value   Field value.
 * @param  string $name    'USER_AGENT'.
 *
 * @return string          Browser (OS).
 */
function _makeAccessLogUserAgent( $value, $column )
{
	if ( empty( $value ) )
	{
		return $value;
	}

	$os = GetUserAgentOS( $value );

	if ( $os )
	{
		$os = ' (' . $os . ')';
	}

	return GetUserAgentBrowser( $value ) . $os;
}
