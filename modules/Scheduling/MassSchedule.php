<?php

require_once 'modules/Scheduling/includes/calcSeats0.fnc.php';

require_once 'modules/Scheduling/functions.inc.php';

if ( ! $_REQUEST['modfunc']
	&& $_REQUEST['search_modfunc'] !== 'list' )
{
	$_SESSION['MassSchedule.php'] = array();
}

if ( $_REQUEST['modfunc'] !== 'choose_course' )
{
	DrawHeader( ProgramTitle() );
}

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit() )
{
	if ( $_SESSION['MassSchedule.php'] )
	{
		if ( ! empty( $_REQUEST['student'] ) )
		{
			$start_date = RequestedDate(
				$_REQUEST['year_start'],
				$_REQUEST['month_start'],
				$_REQUEST['day_start']
			);

			if ( $start_date )
			{
				foreach ( (array) $_SESSION['MassSchedule.php'] as $cp_id => $course_to_add )
				{
					$course_period_RET = DBGet( DBQuery( "SELECT MARKING_PERIOD_ID,TOTAL_SEATS,
						COURSE_PERIOD_ID,CALENDAR_ID
						FROM COURSE_PERIODS
						WHERE COURSE_PERIOD_ID='" . $course_to_add['course_period_id'] . "'" ) );

					$course_mp = $course_period_RET[1]['MARKING_PERIOD_ID'];
					$course_mp_table = GetMP( $course_mp,'MP' );

					if ( $course_mp_table === 'FY'
						|| $course_mp === $_REQUEST['marking_period_id']
						|| mb_strpos( GetChildrenMP( $course_mp_table, $course_mp ), "'" . $_REQUEST['marking_period_id'] . "'" ) !== false )
					{
						// Check available seats:
						if ( $course_period_RET[1]['TOTAL_SEATS'] )
						{
							$seats = calcSeats0( $course_period_RET[1], $start_date );

							if ( $seats != ''
								&& $seats >= $course_period_RET[1]['TOTAL_SEATS'] )
							{
								$warnings[] = _( 'The number of selected students exceeds the available seats.' );
							}
						}

						// FJ check if Available Seats < selected students.
						if ( empty( $warnings )
							|| Prompt(
								'Confirm',
								_( 'There is a conflict.' ) . ' ' .
									sprintf( _( 'Are you sure you want to add %s?' ), $course_to_add['course_period_title'] ),
								ErrorMessage( $warnings, 'warning' )
							) )
						{
							$mp_table = GetMP( $_REQUEST['marking_period_id'], 'MP' );

							$current_RET = DBGet( DBQuery( "SELECT STUDENT_ID
								FROM SCHEDULE
								WHERE COURSE_PERIOD_ID='" . $course_to_add['course_period_id'] . "'
								AND SYEAR='" . UserSyear() . "'
								AND (('" . $start_date . "'	BETWEEN START_DATE AND END_DATE OR END_DATE IS NULL)
									AND '" . $start_date . "'>=START_DATE)" ), array(), array( 'STUDENT_ID' ) );

							foreach ( (array) $_REQUEST['student'] as $student_id => $yes )
							{
								if ( $current_RET[ $student_id ] )
								{
									continue;
								}

								DBQuery( "INSERT INTO SCHEDULE
									(SYEAR,SCHOOL_ID,STUDENT_ID,COURSE_ID,COURSE_PERIOD_ID,MP,
										MARKING_PERIOD_ID,START_DATE)
									values('" . UserSyear() . "','" . UserSchool() . "',
										'" . $student_id . "','" . $course_to_add['course_id'] . "',
										'" . $course_to_add['course_period_id'] . "',
										'" . $mp_table . "','" . $_REQUEST['marking_period_id'] . "',
										'" . $start_date . "')" );

								// Hook.
								do_action( 'Scheduling/MassSchedule.php|schedule_student' );
							}

							$note[] = sprintf( _( 'The %s course has been added to the selected students\' schedules.' ), $course_to_add['course_title'] );
						}
						else
							exit();
					}
					else
					{
						$error[] = _( 'You cannot schedule a student into this course during this marking period.' ) .
							' ' . sprintf( _( 'The %s course meets on %s.' ), $course_to_add['course_title'], GetMP( $course_mp ) );
					}

					unset( $_SESSION['MassSchedule.php'][ $cp_id ] );
				}
			}
			else
				$error[] = _( 'The date you entered is not valid' );
		}
		else
			$error[] = _( 'You must choose at least one student.' );
	}
	else
		$error[] = _( 'You must choose a course.' );

	// Unset modfunc redirect URL.
	RedirectURL( 'modfunc' );

	$_SESSION['MassSchedule.php'] = array();
}

echo ErrorMessage( $error );

echo ErrorMessage( $note, 'note' );

if ( ! $_REQUEST['modfunc'] )
{
	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save" method="POST">';

		DrawHeader( '', SubmitButton( _( 'Add Courses to Selected Students' ) ) );

		echo '<br />';

		PopTable( 'header', _( 'Courses to Add' ) );

		echo '<table><tr><td colspan="2"><div id=course_div>';

		foreach ( (array) $_SESSION['MassSchedule.php'] as $course_to_add )
		{
			echo $course_to_add['course_title'] . '<br />' .
				$course_to_add['course_period_title'] . '<br /><br />';
		}

		echo '</div>' . '<a href="#" onclick=\'popups.open(
				"Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=choose_course"
			); return false;\'>' . _( 'Choose a Course' ) . '</a></td></tr>';

		echo '<tr class="st"><td>' . _( 'Start Date' ) . '</td>
			<td>' . DateInput( DBDate(), 'start', '', false, false ) . '</td></tr>';

		echo '<tr class="st"><td>' . _( 'Marking Period' ) . '</td>';

		$mp_RET = DBGet( DBQuery( "SELECT MARKING_PERIOD_ID,TITLE," .
			db_case( array( 'MP', "'FY'", "'0'", "'SEM'", "'1'", "'QTR'", "'2'" ) ) . " AS TBL
			FROM SCHOOL_MARKING_PERIODS
			WHERE (MP='FY' OR MP='SEM' OR MP='QTR')
			AND SCHOOL_ID='" . UserSchool() . "'
			AND SYEAR='" . UserSyear() . "'
			ORDER BY TBL,SORT_ORDER" ) );

		echo '<td><select name="marking_period_id">';

		foreach ( (array) $mp_RET as $mp )
		{
			echo '<option value="' . $mp['MARKING_PERIOD_ID'] . '">' . $mp['TITLE'] . '</option>';
		}

		echo '</select>';
		echo '</td></tr></table>';

		PopTable( 'footer' );

		echo '<br />';
	}

	$extra['link'] = array( 'FULL_NAME' => false );

	$extra['SELECT'] = ",CAST (NULL AS CHAR(1)) AS CHECKBOX";

	$extra['functions'] = array( 'CHECKBOX' => '_makeChooseCheckbox' );

	$extra['columns_before'] = array( 'CHECKBOX' => '</a><input type="checkbox" value="Y" name="controller" onclick="checkAll(this.form,this.checked,\'student\');"><A>' );

	$extra['new'] = true;

	Widgets( 'course' );
	Widgets( 'request' );

	// Last year course custom widget.
	MyWidgets( 'ly_course' );
	// Widgets('activity');

	Search( 'student_id', $extra );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<br /><div class="center">' .
			SubmitButton( _( 'Add Courses to Selected Students' ) ) . '</div></form>';
	}
}

if ( $_REQUEST['modfunc'] === 'choose_course' )
{
	if ( empty( $_REQUEST['course_period_id'] ) )
	{
		include 'modules/Scheduling/Courses.php';
	}
	else
	{
		$course_title_RET = DBGet( DBQuery( "SELECT TITLE
			FROM COURSES
			WHERE COURSE_ID='" . $_REQUEST['course_id'] . "'" ) );

		$course_title = $course_title_RET[1]['TITLE'];

		$period_title_RET = DBGet( DBQuery( "SELECT TITLE
			FROM COURSE_PERIODS
			WHERE COURSE_PERIOD_ID='" . $_REQUEST['course_period_id'] . "'" ) );

		$period_title = $period_title_RET[1]['TITLE'];

		// Add course period if not already chosen...
		if ( ! isset( $_SESSION['MassSchedule.php'][ $_REQUEST['course_period_id'] ] ) )
		{
			$_SESSION['MassSchedule.php'][ $_REQUEST['course_period_id'] ] = array(
				'subject_id' => $_REQUEST['subject_id'],
				'course_id' => $_REQUEST['course_id'],
				'course_title' => $course_title,
				'course_period_id' => $_REQUEST['course_period_id'],
				'course_period_title' => $period_title,
			);

			// Update main window.
			echo '<script>opener.document.getElementById("course_div").innerHTML += ' .
				json_encode( $course_title . '<br />' . $period_title . '<br /><br />' ) . ';</script>';
		}

		// Close popup.
		echo '<script>window.close();</script>';
	}
}

function _makeChooseCheckbox( $value, $title )
{
	global $THIS_RET;

	return '<input type="checkbox" name="student[' . $THIS_RET['STUDENT_ID'] . ']" value="Y" />';
}
