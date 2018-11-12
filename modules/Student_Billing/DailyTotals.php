<?php
/**
 * Daily Totals program
 *
 * @package RosarioSIS
 * @subpackage modules
 */

DrawHeader( ProgramTitle() );

// Set start date.
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

if ( empty( $start_date ) )
{
	$start_date = date( 'Y-m' ) . '-01';
}

// Set end date.
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

if ( empty( $end_date ) )
{
	$end_date = DBDate();
}

echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] . '" method="GET">';

DrawHeader( '<b>' . _( 'Report Timeframe' ) . ': </b>' .
	PrepareDate( $start_date, '_start' ) . ' - ' .
	PrepareDate( $end_date, '_end' ), SubmitButton( _( 'Go' ) ) );

echo '</form>';

$billing_payments = DBGet( DBQuery( "SELECT sum(AMOUNT) AS AMOUNT
	FROM BILLING_PAYMENTS
	WHERE SYEAR='" . UserSyear() . "'
	AND SCHOOL_ID='" . UserSchool() . "'
	AND PAYMENT_DATE BETWEEN '" . $start_date . "'
	AND '" . $end_date . "'" ) );

$billing_fees = DBGet( DBQuery( "SELECT sum(f.AMOUNT) AS AMOUNT
	FROM BILLING_FEES f
	WHERE  f.SYEAR='" . UserSyear() . "'
	AND f.SCHOOL_ID='" . UserSchool() . "'
	AND f.ASSIGNED_DATE BETWEEN '" . $start_date . "'
	AND '" . $end_date . "'" ) );

echo '<br />';

PopTable( 'header', _( 'Totals' ) );

echo '<table class="cellspacing-5 align-right">';

echo '<tr><td>' . _( 'Payments' ) . ': ' .
	'</td><td>' . Currency( $billing_payments[1]['AMOUNT'] ) . '</td></tr>';

echo '<tr><td>' . _( 'Less' ) . ': ' . _( 'Fees' ) . ': ' .
	'</td><td>' . Currency( $billing_fees[1]['AMOUNT'] ) . '</td></tr>';

echo '<tr><td><b>' . _( 'Total' ) . ': ' . '</b></td>' .
	'<td><b>' . Currency( ( $billing_payments[1]['AMOUNT'] - $billing_fees[1]['AMOUNT'] ) ) . '</b></td></tr>';

echo '</table>';

PopTable( 'footer' );
