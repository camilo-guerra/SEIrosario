<?php
/**
 * Get Grade Level Info function
 *
 * @package RosarioSIS
 * @subpackage functions
 */

/**
 * Get Grade Level Info
 *
 * @param  integer $grade  Grade Level ID.
 * @param  string  $column TITLE|SHORT_NAME|SORT_ORDER|NEXT_GRADE_ID Column name (optional). Defaults to TITLE.
 *
 * @return string  Grade Level Column content
 */
function GetGrade( $grade, $column = 'TITLE' )
{
	static $grades = null;

	// Column defaults to TITLE.
	if ( $column !== 'TITLE'
		&& ( $column === 'GRADE_ID' // Default from GetStuList().
			|| ! in_array( $column, array( 'TITLE', 'SHORT_NAME', 'SORT_ORDER', 'NEXT_GRADE_ID' ) ) ) )
	{
		$column = 'TITLE';
	}

	if ( ! $grades )
	{
		$grades = DBGet( DBQuery( "SELECT ID,TITLE,SHORT_NAME,SORT_ORDER,NEXT_GRADE_ID
			FROM SCHOOL_GRADELEVELS" ), array(), array( 'ID' ) );
	}

	$extra = '';

	if ( $column === 'TITLE' )
	{
		$extra = '<!-- ' . $grades[ $grade ][1]['SORT_ORDER'] . ' -->';
	}

	return $extra . $grades[ $grade ][1][ $column ];
}
