<?php
/**
 * School Year functions
 *
 * @package RosarioSIS
 * @subpackage functions
 */

/**
 * Get School Year
 * Return School Year corresponding to `$date`
 *
 * @example GetSyear( $start_date );
 *
 * @param  string $date DB Date.
 *
 * @return string School Year
 */
function GetSyear( $date )
{
	//$RET = DBGet(DBQuery("SELECT SYEAR FROM SCHOOL_MARKING_PERIODS WHERE MP='FY' AND '".$date."' BETWEEN START_DATE AND END_DATE"));

	// Get greatest SYEAR where START_DATE <= $date.
	$RET = DBGet( DBQuery( "SELECT max(SYEAR) AS SYEAR
		FROM SCHOOL_MARKING_PERIODS
		WHERE MP='FY'
		AND START_DATE<='" . $date . "'" ) );

	return $RET[1]['SYEAR'];
}


/**
 * Format School Year
 * If school year over two calendar years, return "[SYEAR]-[SYEAR+1]"
 *
 * @see School Setup > School Configuration > School
 *
 * @example FormatSyear( UserSyear(), Config( 'SCHOOL_SYEAR_OVER_2_YEARS' ) )
 *
 * @param  string  $syear                School Year.
 * @param  boolean $syear_over_two_years School Year over two calendar years?
 *
 * @return string  Formatted School Year
 */
function FormatSyear( $syear, $syear_over_two_years = true )
{
	if ( $syear_over_two_years )
	{
		return $syear . '-' . ( $syear + 1 );
	}
	else
		return $syear;
}
