<?php
/**
 * Grades Dashboard module
 *
 * @package RosarioSIS
 * @subpackage modules
 */

/**
 * Dashboard Default Grades module
 *
 * @since 4.0
 *
 * @param  boolean $export   Exporting data, defaults to false. Optional.
 * @return string  Dashboard module HTML.
 */
function DashboardDefaultGrades()
{
	require_once 'ProgramFunctions/DashboardModule.fnc.php';

	$profile = User( 'PROFILE' );

	$data = '';

	if ( $profile === 'admin' )
	{
		$data = DashboardGradesAdmin();
	}

	return DashboardModule( 'Grades', $data );
}

if ( ! function_exists( 'DashboardGradesAdmin' ) )
{
	/**
	 * Dashboard data
	 * Grades module & admin profile
	 *
	 * You have to Caluclate GPA for the Quarter first!
	 *
	 * @since 4.0
	 *
	 * @return array Dashboard data
	 */
	function DashboardGradesAdmin()
	{
		$gpa_RET = DBGet( DBQuery( "SELECT ROUND(AVG(CUM_WEIGHTED_GPA)) AS CUM_WEIGHTED_GPA,
		ROUND(AVG(UNWEIGHTED_GPA)) AS CUM_UNWEIGHTED_GPA
		FROM TRANSCRIPT_GRADES
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND MARKING_PERIOD_ID='" . UserMP() . "'" ) );

		// GPA for MP, if graded.
		$gpa = 0;

		$postgresql_version = pg_version();

		if ( ! isset( $gpa_RET[1]['CUM_WEIGHTED_GPA'] )
			&& version_compare( $postgresql_version['server'], '8.4', '>=' ) )
		{
			// PostgreSQL version >= 8.4 required for ARRAY_TO_STRING() function.
			// Assignments.
			$assignments_RET = DBGet( DBQuery( "SELECT COUNT(ASSIGNMENT_ID) AS ASSIGNMENTS_NB,
			ARRAY_TO_STRING(ARRAY_AGG(ASSIGNMENT_ID), ',') AS ASSIGNMENTS_LIST,
			DUE_DATE
			FROM GRADEBOOK_ASSIGNMENTS
			WHERE MARKING_PERIOD_ID='" . UserMP() . "'
			GROUP BY DUE_DATE
			ORDER BY DUE_DATE DESC
			LIMIT 7" ) );

			$assignments_today = 0;

			if ( ! empty( $assignments_RET[1] )
				&& $assignments_RET[1]['DUE_DATE'] === DBDate() )
			{
				// Assignments due today.
				$assignments_today = (int) $assignments_RET[1]['ASSIGNMENTS_NB'];
			}

			$assignments_data[ _( 'Assignments' ) ] = $assignments_today;

			$sql_submissions = array();

			foreach ( $assignments_RET as $assignments )
			{
				$proper_date = ProperDate( $assignments['DUE_DATE'] );

				$assignments_data[ $proper_date ] = $assignments['ASSIGNMENTS_NB'];

				$sql_submissions[] = "SUM(CASE WHEN ASSIGNMENT_ID IN (" . $assignments['ASSIGNMENTS_LIST'] .
					") THEN 1 END) AS " . DBEscapeIdentifier( $assignments['DUE_DATE'] );
			}

			if ( ! $assignments_today
				&& count( $assignments_today ) < 2 )
			{
				return array();
			}

			// Assignments submissions.
			$submissions_RET = DBGet( DBQuery( "SELECT " .
			implode( ',', $sql_submissions ) .
			" FROM STUDENT_ASSIGNMENTS
			GROUP BY STUDENT_ID" ) );

			foreach ( $assignments_RET as $assignments )
			{
				if ( ! empty( $submissions_RET[1][ $assignments['DUE_DATE'] ] ) )
				{
					$proper_date = ProperDate( $assignments['DUE_DATE'] );

					$submissions_nb = $submissions_RET[1][ $assignments['DUE_DATE'] ];

					$assignments_data[ $proper_date ] .= ' &mdash; ' . sprintf(
						'%d %s',
						$submissions_nb,
						ngettext( 'Submission', 'Submissions', $submissions_nb )
					);
				}
			}

			return $assignments_data;
		}

		$gpa = $gpa_RET[1]['CUM_WEIGHTED_GPA'];

		$label = _( 'GPA' ) . ' &mdash; ' . GetMP( UserMP(), 'SHORT_NAME' );

		$gpa_data = array(
			$label => ( $gpa ? number_format( $gpa, 2 ) : _( 'N/A' ) ),
		);

		$gpa_gradelevel_RET = DBGet( DBQuery( "SELECT ROUND(AVG(CUM_WEIGHTED_GPA)) AS CUM_WEIGHTED_GPA,
		ROUND(AVG(UNWEIGHTED_GPA)) AS CUM_UNWEIGHTED_GPA,
		GRADE_LEVEL_SHORT
		FROM TRANSCRIPT_GRADES
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'
		AND MARKING_PERIOD_ID='" . UserMP() . "'
		GROUP BY GRADE_LEVEL_SHORT" ), array(), array( 'GRADE_LEVEL_SHORT' ) );

		foreach ( (array) $gpa_gradelevel_RET as $gradelevel => $gpa_gradelevel )
		{
			if ( empty( $gpa_gradelevel[1]['CUM_WEIGHTED_GPA'] ) )
			{
				continue;
			}

			// GPA detail by Grade Level.
			$gpa_data[ $gradelevel ] = number_format( $gpa_gradelevel[1]['CUM_WEIGHTED_GPA'], 2 );
		}

		if ( ! $gpa
			&& ! $gpa_data )
		{
			return array();
		}

		return $gpa_data;
	}
}
