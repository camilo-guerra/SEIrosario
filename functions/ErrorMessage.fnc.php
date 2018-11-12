<?php
/**
 * Error Message function
 *
 * @package RosarioSIS
 * @subpackage functions
 */

// Declare Error & Note & Warning global arrays.
$note = $error = $warning = array();

/**
 * Error Message
 *
 * Use 'fatal' code to exit program.
 * Use 'note' code for Notes and Update messages.
 *
 * If there are missing vals or similar, show them a msg.
 * Pass in an array with error messages and this will display them
 * in a standard fashion.
 * In a program you may have:
 *
 * @example if ( ! $sch ) $error[] = _( 'School not provided.' );
 * @example if ( $count === 0 ) $error[] = _( 'Number of students is zero.' ); ErrorMessage( $error );
 *
 * Why use this? It will tell the user if they have multiple errors
 * without them having to re-run the program each time finding new
 * problems.  Also, the error display will be standardized.
 *
 * @global string $print_data PDF print data
 *
 * @param  array  $errors Array of errors or notes.
 * @param  string $code   error|fatal|note (optional). Defaults to 'error'.
 * @return string Error / Note Message, exits if 'fatal' code
 */
function ErrorMessage( $errors, $code = 'error' )
{
	if ( ! $errors
		|| ! is_array( $errors ) )
	{
		return '';
	}

	$return = '';

	// Error.

	if ( $code === 'error'
		|| $code === 'fatal' )
	{
		$return .= '<div class="error"><p>' . button( 'x' ) . '&nbsp;<b>' . _( 'Error' ) . ':</b> ';
	}

	// Warning.
	elseif ( $code === 'warning' )
	{
		$return .= '<div class="error"><p>' . button( 'warning' ) . '&nbsp;<b>' . _( 'Warning' ) . ':</b> ';
	}

	// Note / Update.
	else
	{
		$return .= '<div class="updated"><p><b>' . _( 'Note' ) . ':</b> ';
	}

	if ( count( $errors ) === 1 )
	{
		$return .= ( isset( $errors[0] ) ? $errors[0] : $errors[1] ) . '</p>';
	}

	// More than one error: list.
	else
	{
		$return .= '</p><ul>';

		foreach ( (array) $errors as $error )
		{
			$return .= '<li>' . $error . '</li>';
		}

		$return .= '</ul>';
	}

	$return .= '</div>';

	// Fatal error, display error and exit.

	if ( $code === 'fatal' )
	{
		echo $return;

		if ( ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
		{
			Warehouse( 'footer' );
		}
		else
		{
			// FJ force PDF on fatal error.
			global $print_data;

			PDFStop( $print_data );
		}

		exit;
	}

	return $return;
}

/**
 * Send Error by Email
 * Send email to $RosarioErrorsAddress if set {@see config.inc.php}.
 *
 * @since 4.0
 *
 * @param array  $error Error messages. Optional.
 * @param $title string Email title. Optional.
 */
function ErrorSendEmail( $error = array(), $title = 'PHP Fatal error' )
{
	global $RosarioErrorsAddress;

	chdir( dirname( __FILE__ ) . '/../' );

	require_once 'ProgramFunctions/SendEmail.fnc.php';

	if ( ! $error )
	{
		$last_error = error_get_last();

		if ( $last_error['type'] !== 1 )
		{
			return false;
		}

		// Fatal error.
		$error = array(
			'PHP Fatal error: ' . $last_error['message'],
			'File: ' . $last_error['file'],
			'Line: ' . $last_error['line'],
		);

		if ( ROSARIO_DEBUG )
		{
			print_r( $error );
		}
	}

	// Send email to $RosarioErrorsAddress if set {@see config.inc.php}.

	if ( ! filter_var( $RosarioErrorsAddress, FILTER_VALIDATE_EMAIL ) )
	{
		return false;
	}

	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
	{
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	else
	{
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	$debug_backtrace = debug_backtrace();

	// User function would call ErrorMessage in an infinite loop if we are not logged in yet.
	$username = empty( $_SESSION['STAFF_ID'] ) && empty( $_SESSION['STUDENT_ID'] ) ?
		'[no user in session yet]' :
		User( 'USERNAME' );

	$message = 'System: ' . ParseMLField( Config( 'TITLE' ) ) . "\n";
	$message .= 'IP: ' . $ip . "\n";
	$message .= 'Date: ' . date( 'Y-m-d H:i:s' ) . "\n";
	$message .= 'User: ' . $username . "\n";
	$message .= 'Page: ' . $_SERVER['PHP_SELF'] . "\n";
	$message .= 'Query string: ' . $_SERVER['QUERY_STRING'] . "\n";

	$message .= "\n\n" . 'Error: ' . "\n" . print_r( $error, true );
	$message .= "\n\n" . 'Request Array: ' . "\n" . print_r( $_REQUEST, true );
	$message .= "\n\n" . 'Session Array: ' . "\n" . print_r( $_SESSION, true );
	$message .= "\n\n" . 'Debug Backtrace: ' . "\n" . print_r( $debug_backtrace, true );

	if ( mb_strlen( $message ) !== mb_strlen( strip_tags( $message ) ) )
	{
		// Format message if has HTML tags.
		$message = nl2br( $message );
	}

	SendEmail( $RosarioErrorsAddress, $title, $message );

	return true;
}
