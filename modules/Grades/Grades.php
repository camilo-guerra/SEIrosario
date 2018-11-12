<?php
// Note: The 'active assignments' feature is not fully correct.  If a student has dropped and re-enrolled there can be multiple timespans for
// which the  assignemnts are 'active' for that student.  However, only the timespan of current enrollment is used for 'active' assignment
// determination.  It would be possible to include all enrollment timespans but only the current is used for simplicity.  This is not a bug
// but an accepted limitaion.

require_once 'ProgramFunctions/_makeLetterGrade.fnc.php';

require_once 'modules/Grades/includes/StudentAssignments.fnc.php';

DrawHeader( _( 'Gradebook' ) . ' - ' . ProgramTitle() . ' - ' . GetMP( UserMP() ) );

// if running as a teacher program then rosario[allow_edit] will already be set according to admin permissions
if ( !isset($_ROSARIO['allow_edit']))
	$_ROSARIO['allow_edit'] = true;

$gradebook_config = ProgramUserConfig( 'Gradebook' );

//$max_allowed = Preferences('ANOMALOUS_MAX','Gradebook')/100;
$max_allowed = ( $gradebook_config['ANOMALOUS_MAX'] ? $gradebook_config['ANOMALOUS_MAX'] / 100 : 1 );

if ( ! empty( $_REQUEST['student_id'] ) )
{
	if ( $_REQUEST['student_id'] !== UserStudentID() )
	{
		SetUserStudentID( $_REQUEST['student_id'] );

		//FJ bugfix SQL bug course period
		/*if ( $_REQUEST['period'] && $_REQUEST['period']!=UserCoursePeriod())
			$_SESSION['UserCoursePeriod'] = $_REQUEST['period'];*/
		if ( ! empty( $_REQUEST['period'] ) )
		{
			list($CoursePeriod, $CoursePeriodSchoolPeriod) = explode('.', $_REQUEST['period']);

			if ( $CoursePeriod!=UserCoursePeriod())
				$_SESSION['UserCoursePeriod'] = $CoursePeriod;
		}
	}
}
elseif ( UserStudentID() )
{
	unset( $_SESSION['student_id'] );
	//FJ bugfix SQL bug course period
	/*if ( $_REQUEST['period'] && $_REQUEST['period']!=UserCoursePeriod())
		$_SESSION['UserCoursePeriod'] = $_REQUEST['period'];*/
	if ( ! empty( $_REQUEST['period'] ) )
	{
		list($CoursePeriod, $CoursePeriodSchoolPeriod) = explode('.', $_REQUEST['period']);

		if ( $CoursePeriod!=UserCoursePeriod())
			$_SESSION['UserCoursePeriod'] = $CoursePeriod;
	}
}

if ( ! empty( $_REQUEST['period'] ) )
{
	//FJ bugfix SQL bug course period
	/*if ( $_REQUEST['period']!=UserCoursePeriod())
	{
		$_SESSION['UserCoursePeriod'] = $_REQUEST['period'];*/
	list($CoursePeriod, $CoursePeriodSchoolPeriod) = explode('.', $_REQUEST['period']);

	if ( $CoursePeriod!=UserCoursePeriod())
	{
		$_SESSION['UserCoursePeriod'] = $CoursePeriod;

		if ( ! empty( $_REQUEST['student_id'] ) )
		{
			if ( $_REQUEST['student_id']!=UserStudentID())
				SetUserStudentID($_REQUEST['student_id']);
		}
		else
			unset($_SESSION['student_id']);
	}
}

$types_RET = DBGet(DBQuery("SELECT ASSIGNMENT_TYPE_ID,TITLE,FINAL_GRADE_PERCENT,COLOR
FROM GRADEBOOK_ASSIGNMENT_TYPES gt
WHERE STAFF_ID='".User('STAFF_ID')."'
AND COURSE_ID=(SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."')
AND (SELECT count(1) FROM GRADEBOOK_ASSIGNMENTS WHERE STAFF_ID=gt.STAFF_ID
AND ((COURSE_ID=gt.COURSE_ID AND STAFF_ID=gt.STAFF_ID) OR COURSE_PERIOD_ID='".UserCoursePeriod()."')
AND MARKING_PERIOD_ID='".UserMP()."'
AND ASSIGNMENT_TYPE_ID=gt.ASSIGNMENT_TYPE_ID)>0
ORDER BY SORT_ORDER,TITLE"),array(),array('ASSIGNMENT_TYPE_ID'));
//echo '<pre>'; var_dump($types_RET); echo '</pre>';

if ( $_REQUEST['type_id']
	&& ! $types_RET[ $_REQUEST['type_id'] ] )
{
	// Unset type ID & redirect URL.
	RedirectURL( 'type_id' );
}

//FJ default points
$assignments_RET = DBGet(DBQuery("SELECT ASSIGNMENT_ID,ASSIGNMENT_TYPE_ID,TITLE,POINTS,ASSIGNED_DATE,DUE_DATE,DEFAULT_POINTS,extract(EPOCH FROM DUE_DATE) AS DUE_EPOCH,
CASE WHEN (ASSIGNED_DATE IS NULL OR CURRENT_DATE>=ASSIGNED_DATE) AND (DUE_DATE IS NULL OR CURRENT_DATE>=DUE_DATE) OR CURRENT_DATE>(SELECT END_DATE FROM SCHOOL_MARKING_PERIODS WHERE MARKING_PERIOD_ID=gradebook_assignments.MARKING_PERIOD_ID) THEN 'Y' ELSE NULL END AS DUE
FROM GRADEBOOK_ASSIGNMENTS
WHERE STAFF_ID='".User('STAFF_ID')."'
AND ((COURSE_ID=(SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."') AND STAFF_ID='".User('STAFF_ID')."') OR COURSE_PERIOD_ID='".UserCoursePeriod()."')
AND MARKING_PERIOD_ID='".UserMP()."'".($_REQUEST['type_id']?"
AND ASSIGNMENT_TYPE_ID='".$_REQUEST['type_id']."'":'')."
ORDER BY ".Preferences('ASSIGNMENT_SORTING','Gradebook')." DESC,ASSIGNMENT_ID DESC,TITLE"),array(),array('ASSIGNMENT_ID'));
//echo '<pre>'; var_dump($assignments_RET); echo '</pre>';

// when changing course periods the assignment_id will be wrong except for '' (totals) and 'all'
if ( $_REQUEST['assignment_id']
	&& $_REQUEST['assignment_id'] !== 'all'
	&& ! $assignments_RET[ $_REQUEST['assignment_id'] ] )
{
	// Unset assignment ID & redirect URL.
	RedirectURL( 'assignment_id' );
}
	//else
	//	$_REQUEST['type_id'] = $assignments_RET[$_REQUEST['assignment_id']][1]['ASSIGNMENT_TYPE_ID'];

if ( UserStudentID()
	&& ! $_REQUEST['assignment_id'] )
{
	$_REQUEST['assignment_id'] = 'all';
}

if ( $_REQUEST['values']
	&& $_POST['values']
	// Fix use weak comparison "==" operator as $_SESSION['type_id'] maybe null.
	&& $_SESSION['type_id'] == $_REQUEST['type_id']
	&& $_SESSION['assignment_id'] == $_REQUEST['assignment_id'] )
{
	include 'ProgramFunctions/_makePercentGrade.fnc.php';

	if ( UserStudentID() )
	{
		$current_RET[ UserStudentID() ] = DBGet( DBQuery( "SELECT g.ASSIGNMENT_ID
			FROM GRADEBOOK_GRADES g,GRADEBOOK_ASSIGNMENTS a
			WHERE a.ASSIGNMENT_ID=g.ASSIGNMENT_ID
			AND a.MARKING_PERIOD_ID='" . UserMP() . "'
			AND g.STUDENT_ID='" . UserStudentID() . "'
			AND g.COURSE_PERIOD_ID='" . UserCoursePeriod() . "'" .
			( $_REQUEST['assignment_id'] === 'all' ? '' :
				" AND g.ASSIGNMENT_ID='" . $_REQUEST['assignment_id'] . "'" ) ),
			array(),
			array( 'ASSIGNMENT_ID' )
		);
	}
	elseif ( $_REQUEST['assignment_id'] === 'all' )
	{
		$current_RET = DBGet( DBQuery( "SELECT g.STUDENT_ID,g.ASSIGNMENT_ID,g.POINTS
			FROM GRADEBOOK_GRADES g,GRADEBOOK_ASSIGNMENTS a
			WHERE a.ASSIGNMENT_ID=g.ASSIGNMENT_ID
			AND a.MARKING_PERIOD_ID='" . UserMP() . "'
			AND g.COURSE_PERIOD_ID='" . UserCoursePeriod() . "'" ),
			array(),
			array( 'STUDENT_ID', 'ASSIGNMENT_ID' )
		);
	}
	else
	{
		$current_RET = DBGet( DBQuery( "SELECT STUDENT_ID,POINTS,COMMENT,ASSIGNMENT_ID
			FROM GRADEBOOK_GRADES
			WHERE ASSIGNMENT_ID='" . $_REQUEST['assignment_id'] . "'
			AND COURSE_PERIOD_ID='" . UserCoursePeriod() . "'" ),
			array(),
			array( 'STUDENT_ID', 'ASSIGNMENT_ID' )
		);
	}

	foreach ( (array) $_REQUEST['values'] as $student_id => $assignments)
	{
		foreach ( (array) $assignments as $assignment_id => $columns)
		{
			if ( $columns['POINTS'])
			{
				if ( $columns['POINTS']=='*')
					$columns['POINTS'] = '-1';
				else
				{
					if (mb_substr($columns['POINTS'],-1)=='%')
						$columns['POINTS'] = mb_substr($columns['POINTS'],0,-1) * $assignments_RET[ $assignment_id ][1]['POINTS'] / 100;
					elseif ( !is_numeric($columns['POINTS']))
						$columns['POINTS'] = _makePercentGrade($columns['POINTS'],UserCoursePeriod()) * $assignments_RET[ $assignment_id ][1]['POINTS'] / 100;

					if ( $columns['POINTS']<0)
						$columns['POINTS'] = '0';
					elseif ( $columns['POINTS']>9999.99)
						$columns['POINTS'] = '9999.99';
				}
			}

			$sql = '';

			if ( $current_RET[ $student_id ][ $assignment_id ])
			{
				$sql = "UPDATE GRADEBOOK_GRADES SET ";

				foreach ( (array) $columns as $column => $value)
				{
					$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
				}

				$sql = mb_substr($sql,0,-1)." WHERE STUDENT_ID='".$student_id."' AND ASSIGNMENT_ID='".$assignment_id."' AND COURSE_PERIOD_ID='".UserCoursePeriod()."'";
			}
			elseif ( $columns['POINTS']!='' || $columns['COMMENT'])
				$sql = "INSERT INTO GRADEBOOK_GRADES (STUDENT_ID,PERIOD_ID,COURSE_PERIOD_ID,ASSIGNMENT_ID,POINTS,COMMENT) values('".$student_id."','".UserPeriod()."','".UserCoursePeriod()."','".$assignment_id."','".$columns['POINTS']."','".$columns['COMMENT']."')";

			if ( $sql)
				DBQuery($sql);
		}
	}

	// Unset values & redirect URL.
	RedirectURL( 'values' );

	unset( $current_RET );
}

$_SESSION['type_id'] = isset( $_REQUEST['type_id'] ) ? $_REQUEST['type_id'] : null;
$_SESSION['assignment_id'] = isset( $_REQUEST['assignment_id'] ) ? $_REQUEST['assignment_id'] : null;

$LO_options = array('search'=>false);

if (UserStudentID())
{
	$extra['WHERE'] = " AND s.STUDENT_ID='" . UserStudentID() . "'";

	if ( empty( $_REQUEST['type_id'] ) )
	{
		$LO_columns = array( 'TYPE_TITLE' => _( 'Category' ) );
	}
	else
		$LO_columns = array();

	$LO_columns += array(
		'TITLE' => _( 'Assignment' ),
		'POINTS' => _( 'Points' ),
		'COMMENT' => _( 'Comment' ),
		'SUBMISSION' => _( 'Submission' ),
	);

	// modif Francois: display percent grade according to Configuration.
	if ( ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ) >= 0 )
	{
		$LO_columns['PERCENT_GRADE'] = _('Percent');
	}
	// modif Francois: display letter grade according to Configuration.
	if ( ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ) <= 0 )
	{
		$LO_columns['LETTER_GRADE'] = _('Letter');
	}

	$link['TITLE']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&include_inactive='.$_REQUEST['include_inactive'].'&include_all='.$_REQUEST['include_all'];

	$link['TITLE']['variables'] = array(
		'type_id' => 'ASSIGNMENT_TYPE_ID',
		'assignment_id' => 'ASSIGNMENT_ID',
	);

	$current_RET[UserStudentID()] = DBGet(DBQuery("SELECT g.ASSIGNMENT_ID
	FROM GRADEBOOK_GRADES g,GRADEBOOK_ASSIGNMENTS a
	WHERE a.ASSIGNMENT_ID=g.ASSIGNMENT_ID
	AND a.MARKING_PERIOD_ID='".UserMP()."'
	AND g.STUDENT_ID='".UserStudentID()."'
	AND g.COURSE_PERIOD_ID='".UserCoursePeriod()."'".
	($_REQUEST['assignment_id']=='all'?'':" AND g.ASSIGNMENT_ID='".$_REQUEST['assignment_id']."'")),array(),array('ASSIGNMENT_ID'));

	$count_assignments = count($assignments_RET);

	$extra['SELECT'] = ",ga.ASSIGNMENT_TYPE_ID,ga.ASSIGNMENT_ID,ga.TITLE,ga.POINTS AS TOTAL_POINTS,
		ga.SUBMISSION,'' AS PERCENT_GRADE,'' AS LETTER_GRADE,
		CASE WHEN (ga.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=ga.ASSIGNED_DATE)
			AND (ga.DUE_DATE IS NULL OR CURRENT_DATE>=ga.DUE_DATE)
			OR CURRENT_DATE>(SELECT END_DATE FROM SCHOOL_MARKING_PERIODS WHERE MARKING_PERIOD_ID=ga.MARKING_PERIOD_ID)
			THEN 'Y' ELSE NULL END AS DUE";

	$extra['SELECT'] .= ',gg.POINTS,gg.COMMENT';

	if ( empty( $_REQUEST['type_id'] ) )
	{
		$extra['SELECT'] .= ',(SELECT TITLE FROM GRADEBOOK_ASSIGNMENT_TYPES WHERE ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID) AS TYPE_TITLE';

		$link['TYPE_TITLE']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&include_inactive='.$_REQUEST['include_inactive'].'&include_all='.$_REQUEST['include_all'];

		$link['TYPE_TITLE']['variables'] = array('type_id' => 'ASSIGNMENT_TYPE_ID');
	}

	$extra['FROM'] = " JOIN GRADEBOOK_ASSIGNMENTS ga ON (ga.STAFF_ID=cp.TEACHER_ID AND ((ga.COURSE_ID=cp.COURSE_ID AND ga.STAFF_ID=cp.TEACHER_ID) OR ga.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID) AND ga.MARKING_PERIOD_ID='".UserMP()."'".($_REQUEST['assignment_id']=='all'?'':" AND ga.ASSIGNMENT_ID='".$_REQUEST['assignment_id']."'").($_REQUEST['type_id']?" AND ga.ASSIGNMENT_TYPE_ID='".$_REQUEST['type_id']."'":'').") LEFT OUTER JOIN GRADEBOOK_GRADES gg ON (gg.STUDENT_ID=s.STUDENT_ID AND gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID)";

	if ( empty( $_REQUEST['include_all'] ) )
		$extra['WHERE'] .= " AND (gg.POINTS IS NOT NULL OR (ga.DUE_DATE IS NULL OR (".db_greatest('ssm.START_DATE','ss.START_DATE')."<=ga.DUE_DATE) AND (".db_least('ssm.END_DATE','ss.END_DATE')." IS NULL OR ".db_least('ssm.END_DATE','ss.END_DATE').">=ga.DUE_DATE)))".($_REQUEST['type_id']?" AND ga.ASSIGNMENT_TYPE_ID='".$_REQUEST['type_id']."'":'');

	$extra['ORDER_BY'] = Preferences('ASSIGNMENT_SORTING','Gradebook')." DESC";

	$extra['functions'] = array(
		'POINTS' => '_makeExtraStuCols',
		'PERCENT_GRADE' => '_makeExtraStuCols',
		'LETTER_GRADE' => '_makeExtraStuCols',
		'COMMENT' => '_makeExtraStuCols',
		'SUBMISSION' => 'MakeStudentAssignmentSubmissionView',
	);
}
else
{
	$LO_columns = array( 'FULL_NAME' => _( 'Student' ) );

	// Gain 1 column: replace it with "Submission".
	/*if ( $_REQUEST['assignment_id'] != 'all' )
	{
		$LO_columns += array( 'STUDENT_ID' => sprintf( _( '%s ID' ), Config( 'NAME' ) ) );
	}*/

	if ( $_REQUEST['include_inactive'] == 'Y' )
	{
		$LO_columns += array(
			'ACTIVE' => _( 'School Status' ),
			'ACTIVE_SCHEDULE' => _( 'Course Status' ) );
	}

	$link['FULL_NAME']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&include_inactive='.$_REQUEST['include_inactive'].'&include_all='.$_REQUEST['include_all'].'&type_id='.$_REQUEST['type_id'].'&assignment_id=all';
	$link['FULL_NAME']['variables'] = array('student_id' => 'STUDENT_ID');

	if ( $_REQUEST['assignment_id']=='all')
	{
		$current_RET = DBGet(DBQuery("SELECT g.STUDENT_ID,g.ASSIGNMENT_ID,g.POINTS FROM GRADEBOOK_GRADES g,GRADEBOOK_ASSIGNMENTS a WHERE a.ASSIGNMENT_ID=g.ASSIGNMENT_ID AND a.MARKING_PERIOD_ID='".UserMP()."' AND g.COURSE_PERIOD_ID='".UserCoursePeriod()."'".($_REQUEST['type_id']?" AND a.ASSIGNMENT_TYPE_ID='".$_REQUEST['type_id']."'":'')),array(),array('STUDENT_ID','ASSIGNMENT_ID'));
		$count_extra = array('SELECT_ONLY' => 'ssm.STUDENT_ID');
		$count_students = GetStuList($count_extra);
		$count_students = count($count_students);

		$extra['SELECT'] = ",extract(EPOCH FROM ".db_greatest('ssm.START_DATE','ss.START_DATE').") AS START_EPOCH,extract(EPOCH FROM ".db_least('ssm.END_DATE','ss.END_DATE').") AS END_EPOCH";
		$extra['functions'] = array();

		foreach ( (array) $assignments_RET as $id => $assignment )
		{
			$assignment = $assignment[1];

			$extra['SELECT'] .= ",'" . $id . "' AS G" . $id;

			$extra['functions'] += array('G' . $id => '_makeExtraCols' );

			$column_title = $assignment['TITLE'];

			if ( empty( $_REQUEST['type_id'] ) )
			{
				$column_title = $types_RET[$assignment['ASSIGNMENT_TYPE_ID']][1]['TITLE'] . '<br />' . $column_title;
			}

			if ( ! $_REQUEST['type_id']
				&& $types_RET[$assignment['ASSIGNMENT_TYPE_ID']][1]['COLOR'] )
			{
				$column_title = '<span style="background-color: ' . $types_RET[$assignment['ASSIGNMENT_TYPE_ID']][1]['COLOR'] . ';">&nbsp;</span>&nbsp;' .
					$column_title;
			}

			$LO_columns['G' . $id] = $column_title;
		}
	}
	elseif ( ! empty( $_REQUEST['assignment_id'] ) )
	{
		$extra['SELECT'] .= ",'" . $_REQUEST['assignment_id'] . "' AS POINTS,
			'" . $_REQUEST['assignment_id'] . "' AS PERCENT_GRADE,
			'" . $_REQUEST['assignment_id'] . "' AS LETTER_GRADE,
			'" . $_REQUEST['assignment_id'] . "' AS COMMENT,
			(SELECT 'Y' FROM GRADEBOOK_ASSIGNMENTS ga
				WHERE ga.ASSIGNMENT_ID='" . $_REQUEST['assignment_id'] . "'
				AND ga.SUBMISSION='Y') AS SUBMISSION,
			'" . $_REQUEST['assignment_id'] . "' AS ASSIGNMENT_ID";

		$extra['SELECT'] .= ",extract(EPOCH FROM ".db_greatest('ssm.START_DATE','ss.START_DATE').") AS START_EPOCH,extract(EPOCH FROM ".db_least('ssm.END_DATE','ss.END_DATE').") AS END_EPOCH";

		$extra['functions'] = array(
			'POINTS' => '_makeExtraAssnCols',
			'PERCENT_GRADE' => '_makeExtraAssnCols',
			'LETTER_GRADE' => '_makeExtraAssnCols',
			'COMMENT' => '_makeExtraAssnCols',
			'SUBMISSION' => 'MakeStudentAssignmentSubmissionView',
		);

		$LO_columns += array(
			'POINTS' => _( 'Points' ),
			'COMMENT' => _( 'Comment' ),
			'SUBMISSION' => _( 'Submission' ),
		);

		$current_RET = DBGet(DBQuery("SELECT STUDENT_ID,POINTS,COMMENT,ASSIGNMENT_ID FROM GRADEBOOK_GRADES WHERE ASSIGNMENT_ID='".$_REQUEST['assignment_id']."' AND COURSE_PERIOD_ID='".UserCoursePeriod()."'"),array(),array('STUDENT_ID','ASSIGNMENT_ID'));
	}
	else
	{
		if (count($assignments_RET))
		{
			//FJ default points
			$extra['SELECT_ONLY'] = "s.STUDENT_ID, gt.ASSIGNMENT_TYPE_ID,sum(".db_case(array('gg.POINTS',"'-1'","'0'","''",db_case(array('ga.DEFAULT_POINTS',"'-1'","'0'",'ga.DEFAULT_POINTS')),'gg.POINTS')).") AS PARTIAL_POINTS,sum(".db_case(array('gg.POINTS',"'-1'","'0'","''",db_case(array('ga.DEFAULT_POINTS',"'-1'","'0'",'ga.POINTS')),'ga.POINTS')).") AS PARTIAL_TOTAL,gt.FINAL_GRADE_PERCENT";
			$extra['FROM'] = " JOIN GRADEBOOK_ASSIGNMENTS ga ON ((ga.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID OR ga.COURSE_ID=cp.COURSE_ID AND ga.STAFF_ID=cp.TEACHER_ID) AND ga.MARKING_PERIOD_ID='".UserMP()."') LEFT OUTER JOIN GRADEBOOK_GRADES gg ON (gg.STUDENT_ID=s.STUDENT_ID AND gg.ASSIGNMENT_ID=ga.ASSIGNMENT_ID AND gg.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID),GRADEBOOK_ASSIGNMENT_TYPES gt";
			$extra['WHERE'] = " AND gt.ASSIGNMENT_TYPE_ID=ga.ASSIGNMENT_TYPE_ID AND gt.COURSE_ID=cp.COURSE_ID AND (gg.POINTS IS NOT NULL OR (ga.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=ga.ASSIGNED_DATE) AND (ga.DUE_DATE IS NULL OR CURRENT_DATE>=ga.DUE_DATE) OR CURRENT_DATE>(SELECT END_DATE FROM SCHOOL_MARKING_PERIODS WHERE MARKING_PERIOD_ID=ga.MARKING_PERIOD_ID))".($_REQUEST['type_id']?" AND ga.ASSIGNMENT_TYPE_ID='".$_REQUEST['type_id']."'":'');

			if ( empty( $_REQUEST['include_all'] ) )
				$extra['WHERE'] .=" AND (gg.POINTS IS NOT NULL OR ga.DUE_DATE IS NULL OR ((ga.DUE_DATE>=ss.START_DATE AND (ss.END_DATE IS NULL OR ga.DUE_DATE<=ss.END_DATE)) AND (ga.DUE_DATE>=ssm.START_DATE AND (ssm.END_DATE IS NULL OR ga.DUE_DATE<=ssm.END_DATE))))";

			$extra['GROUP'] = "gt.ASSIGNMENT_TYPE_ID,gt.FINAL_GRADE_PERCENT,s.STUDENT_ID";
			$extra['group'] = array('STUDENT_ID');

			$points_RET = GetStuList($extra);
			//echo '<pre>'; var_dump($points_RET); echo '</pre>';

			unset($extra);
			$extra['SELECT'] = ",extract(EPOCH FROM ".db_greatest('ssm.START_DATE','ss.START_DATE').") AS START_EPOCH,extract(EPOCH FROM ".db_least('ssm.END_DATE','ss.END_DATE').") AS END_EPOCH,'' AS POINTS,'' AS PERCENT_GRADE,'' AS LETTER_GRADE";
			$extra['functions'] = array('POINTS' => '_makeExtraAssnCols', 'PERCENT_GRADE' => '_makeExtraAssnCols', 'LETTER_GRADE' => '_makeExtraAssnCols');

			$LO_columns['POINTS'] = _('Points');
		}
	}

	if ( $_REQUEST['assignment_id'] != 'all' )
	{
		// modif Francois: display percent grade according to Configuration.
		if ( ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ) >= 0 )
		{
			$LO_columns['PERCENT_GRADE'] = _( 'Percent' );
		}

		// modif Francois: display letter grade according to Configuration.
		if ( ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ) <= 0 )
		{
			$LO_columns['LETTER_GRADE'] = _( 'Letter' );
		}
	}

	$extra['functions']['FULL_NAME'] = 'makePhotoTipMessage';
}

$stu_RET = GetStuList($extra);
//echo '<pre>'; var_dump($stu_RET); echo '</pre>';

//FJ add translation
$type_onchange_URL = "'Modules.php?modname=" . $_REQUEST['modname'] .
	'&include_inactive=' . $_REQUEST['include_inactive'] .
	'&include_all=' . $_REQUEST['include_all'] .
	( $_REQUEST['assignment_id'] === 'all' ? '&assignment_id=all' : '' ) .
	( UserStudentID() ? '&student_id=' . UserStudentID() : '' ) .
	"&type_id='";

$type_select = '<select name="type_id" onchange="ajaxLink(' . $type_onchange_URL . ' + this.options[selectedIndex].value);">';

$type_select .= '<option value=""' . ( ! $_REQUEST['type_id'] ? ' selected' : '' ) . '>' .
	_( 'All' ) .
'</option>';

foreach ( (array) $types_RET as $id => $type )
{
	$type_select .= '<option value="' . $id . '"' . ( $_REQUEST['type_id'] == $id? ' selected' : '' ) . '>' .
		$type[1]['TITLE'] .
	'</option>';
}

$type_select .= '</select>';

$assignment_onchange_URL = "'Modules.php?modname=" . $_REQUEST['modname'] .
	'&include_inactive=' . $_REQUEST['include_inactive'] .
	'&include_all=' . $_REQUEST['include_all'] .
	'&type_id=' . $_REQUEST['type_id'] .
	"&assignment_id='";

$assignment_select = '<select name="assignment_id" onchange="ajaxLink(' . $assignment_onchange_URL . ' + this.options[selectedIndex].value);">';

$assignment_select .= '<option value="">' . _( 'Totals' ) . '</option>';

$assignment_select .= '<option value="all"' . ( ( $_REQUEST['assignment_id'] === 'all' && !UserStudentID() ) ? ' selected' : '' ) . '>' .
	_( 'All' ) .
'</option>';

if ( UserStudentID() && $_REQUEST['assignment_id'] === 'all' )
{
	$assignment_select .= '<option value="all" selected>' . $stu_RET[1]['FULL_NAME'] . '</option>';
}

foreach ( (array) $assignments_RET as $id => $assignment)
{
	$assignment_select .= '<option value="' . $id . '"' .
		( $_REQUEST['assignment_id'] == $id ? ' selected' : '' ) . '>' .
		( $_REQUEST['type_id'] ?
			'' :
			$types_RET[ $assignment[1]['ASSIGNMENT_TYPE_ID'] ][1]['TITLE'] . ' - ' ) .
		$assignment[1]['TITLE'] . '</option>';
}

$assignment_select .= '</select>';

// echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&student_id='.UserStudentID().'" method="POST">';

echo '<form action="' . PreparePHP_SELF() . '" method="POST">';

$tabs = array( array(
	'title' => _( 'All' ),
	'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&type_id=' . ( $_REQUEST['assignment_id'] == 'all' ? '&assignment_id=all' : '' ) . ( UserStudentID() ? '&student_id=' . UserStudentID() : '' ) . '&include_inactive=' . $_REQUEST['include_inactive'] . '&include_all=' . $_REQUEST['include_all']
));

foreach ( (array) $types_RET as $id => $type )
{
	$color = '';

	if ( $type[1]['COLOR'] )
		$color = '<span style="background-color: ' . $type[1]['COLOR'] . ';">&nbsp;</span>&nbsp;';

	$tabs[] = array(
		'title' => $color . $type[1]['TITLE'] . ( $gradebook_config['WEIGHT'] == 'Y' ? '|' . number_format( 100 * $type[1]['FINAL_GRADE_PERCENT'], 0 ) . '%' : '' ),
		'link' => 'Modules.php?modname=' . $_REQUEST['modname'] . '&type_id=' . $id . ( $_REQUEST['assignment_id'] == 'all' ? '&assignment_id=all' : '' ) . ( UserStudentID() ? '&student_id=' . UserStudentID() : '' ) . '&include_inactive=' .$_REQUEST['include_inactive'] . '&include_all=' . $_REQUEST['include_all']
	);
}

DrawHeader(
	$type_select . $assignment_select,
	$_REQUEST['assignment_id'] ? SubmitButton() : ''
);

DrawHeader(
	CheckBoxOnclick(
		'include_inactive',
		_( 'Include Inactive Students' )
	) . ' &nbsp;' .
	CheckBoxOnclick(
		'include_all',
		_( 'Include Inactive Assignments' )
	)
);

if ( $_REQUEST['assignment_id'] && $_REQUEST['assignment_id']!='all')
{
	$assigned_date = $assignments_RET[$_REQUEST['assignment_id']][1]['ASSIGNED_DATE'];
	$due_date = $assignments_RET[$_REQUEST['assignment_id']][1]['DUE_DATE'];
	$due = $assignments_RET[$_REQUEST['assignment_id']][1]['DUE'];

	DrawHeader('<b>'._('Assigned Date').':</b> '.($assigned_date ? ProperDate($assigned_date) : _('N/A')).', <b>'._('Due Date').':</b> '.($due_date ? ProperDate($due_date) : _('N/A')).($due ? ' - <b>'._('Assignment is Due').'</b>' : ''));
}

$LO_options['header'] = WrapTabs(
	$tabs,
	'Modules.php?modname=' . $_REQUEST['modname'] . '&type_id=' .
	( $_REQUEST['type_id'] ?
		$_REQUEST['type_id'] :
		( $_REQUEST['assignment_id'] && $_REQUEST['assignment_id'] != 'all'?
			$assignments_RET[ $_REQUEST['assignment_id'] ][1]['ASSIGNMENT_TYPE_ID'] :
			'' )
	) .
	( $_REQUEST['assignment_id'] == 'all' ? '&assignment_id=all' : '' ) .
	( UserStudentID() ? '&student_id=' . UserStudentID() : '' ) .
	'&include_inactive=' . $_REQUEST['include_inactive'] . '&include_all=' . $_REQUEST['include_all']
);

echo '<br />';

if ( UserStudentID() )
{
	ListOutput(
		$stu_RET,
		$LO_columns,
		'Assignment',
		'Assignments',
		$link,
		array(),
		$LO_options
	);
}
else
{
	ListOutput(
		$stu_RET,
		$LO_columns,
		'Student',
		'Students',
		$link,
		array(),
		$LO_options
	);
}

echo $_REQUEST['assignment_id']?'<br /><div class="center">' . SubmitButton() . '</div>':'';
echo '</form>';


/**
 * Make Tip Message containing Student Photo
 * Local function
 *
 * Callback for DBGet() column formatting
 *
 * @deprecated since 3.8, see GetStuList.fnc.php makePhotoTipMessage()
 *
 * @uses MakeStudentPhotoTipMessage()
 *
 * @see ProgramFunctions/TipMessage.fnc.php
 *
 * @global $THIS_RET, see DBGet()
 *
 * @param  string $full_name Student Full Name
 * @param  string $column    'FULL_NAME'
 *
 * @return string Student Full Name + Tip Message containing Student Photo
 */
function _makeTipMessage( $full_name, $column )
{
	global $THIS_RET;

	require_once 'ProgramFunctions/TipMessage.fnc.php';

	return MakeStudentPhotoTipMessage( $THIS_RET['STUDENT_ID'], $full_name );
}


function _makeExtraAssnCols( $assignment_id, $column )
{
	global $THIS_RET,
		$assignments_RET,
		$current_RET,
		$points_RET,
		$max_allowed,
		$total,
		$gradebook_config;

	switch ( $column)
	{
		case 'POINTS':

			if ( ! $assignment_id )
			{
				$total = $total_points = 0;
				//FJ default points
				$total_use_default_points = false;

				if (count($points_RET[$THIS_RET['STUDENT_ID']]))
				{
					foreach ( (array) $points_RET[$THIS_RET['STUDENT_ID']] as $partial_points)
					{
						if ( $partial_points['PARTIAL_TOTAL']!=0 || $gradebook_config['WEIGHT']!='Y')
						{
							$total += $partial_points['PARTIAL_POINTS'];
							$total_points += $partial_points['PARTIAL_TOTAL'];
						}
					}
				}

//				return '<table cellspacing=0 cellpadding=0><tr><td>'.$total.'</td><td>&nbsp;/&nbsp;</td><td>'.$total_points.'</td></tr></table>';
				return $total.'&nbsp;/&nbsp;'.$total_points;
			}
			else
			{
				if ( ! empty( $_REQUEST['include_all'] )
					|| ( $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'] != ''
						|| ! $assignments_RET[ $assignment_id ][1]['DUE_EPOCH']
						|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] >= $THIS_RET['START_EPOCH']
						&& ( ! $THIS_RET['END_EPOCH']
							|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] <= $THIS_RET['END_EPOCH'] ) ) )
				{
					$total_points = $assignments_RET[ $assignment_id ][1]['POINTS'];

					//FJ default points
					$points = $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'];
					$div = true;

					if (is_null($points))
					{
						$points = $assignments_RET[ $assignment_id ][1]['DEFAULT_POINTS'];
						$div = false;
					}

					if ( $points=='-1')
						$points = '*';
					elseif (mb_strpos($points,'.'))
						$points = rtrim(rtrim($points,'0'),'.');

//					return '<table cellspacing=0 cellpadding=1><tr><td>'.TextInput($points,'values['.$THIS_RET['STUDENT_ID'].']['.$assignment_id.'][POINTS]','',' size=2 maxlength=7 tabindex='.$tabindex).'</td><td>&nbsp;/&nbsp;</td><td>'.$total_points.'</td></tr></table>';
					return '<span' . ( $div ? ' class="span-grade-points"' : '' ) . '>' .
						TextInput(
							$points,
							'values[' . $THIS_RET['STUDENT_ID'] . '][' . $assignment_id . '][POINTS]',
							'',
							' size=2 maxlength=7',
							$div
						) . '</span>
						<span>&nbsp;/&nbsp;' . $total_points . '</span>';
				}
			}
		break;

		case 'PERCENT_GRADE':
			if ( ! $assignment_id)
			{
				$total = $total_percent = 0;
				if (count($points_RET[$THIS_RET['STUDENT_ID']]))
				{
					foreach ( (array) $points_RET[$THIS_RET['STUDENT_ID']] as $partial_points)
						if ( $partial_points['PARTIAL_TOTAL']!=0 || $gradebook_config['WEIGHT']!='Y')
						{
							$total += $partial_points['PARTIAL_POINTS']*($gradebook_config['WEIGHT']=='Y'?$partial_points['FINAL_GRADE_PERCENT']/$partial_points['PARTIAL_TOTAL']:1);
							$total_percent += ($gradebook_config['WEIGHT']=='Y'?$partial_points['FINAL_GRADE_PERCENT']:$partial_points['PARTIAL_TOTAL']);
						}

					if ( $total_percent!=0)
						$total /= $total_percent;
				}

				return ($total>$max_allowed?'<span style="color:red">':'')._Percent($total,0).($total>$max_allowed?'</span>':'');
			}
			else
			{
				if ( ! empty( $_REQUEST['include_all'] )
					|| ( $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'] != ''
						|| ! $assignments_RET[ $assignment_id ][1]['DUE_EPOCH']
						|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] >= $THIS_RET['START_EPOCH']
						&& (! $THIS_RET['END_EPOCH']
							|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] <= $THIS_RET['END_EPOCH'] ) ) )
				{
					$total_points = $assignments_RET[ $assignment_id ][1]['POINTS'];
					//FJ default points
					$points = $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'];

					if (is_null($points))
						$points = $assignments_RET[ $assignment_id ][1]['DEFAULT_POINTS'];

					if ( $total_points!=0)
					{
						if ( $points!='-1')
							return ($assignments_RET[ $assignment_id ][1]['DUE']||$points!=''?($points>$total_points*$max_allowed?'<span style="color:red">':'<span>'):'<span>')._Percent($points/$total_points,0).'</span>';
						else
							return _('N/A');
					}
					else
						return _('E/C');
				}
			}
		break;

		case 'LETTER_GRADE':
			if ( ! $assignment_id)
			{
				return '<b>'._makeLetterGrade($total).'</b>';
			}
			else
			{
				if ( ! empty( $_REQUEST['include_all'] )
					|| ( $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'] != ''
						|| ! $assignments_RET[ $assignment_id ][1]['DUE_EPOCH']
						|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] >= $THIS_RET['START_EPOCH']
						&& ( ! $THIS_RET['END_EPOCH']
							|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] <= $THIS_RET['END_EPOCH'] ) ) )
				{
					$total_points = $assignments_RET[ $assignment_id ][1]['POINTS'];
					//FJ default points
					$points = $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'];

					if (is_null($points))
						$points = $assignments_RET[ $assignment_id ][1]['DEFAULT_POINTS'];

					if ( $total_points!=0)
					{
						if ( $points!='-1')
							return ($assignments_RET[ $assignment_id ][1]['DUE']||$points!=''?'':'<span style="color:gray">').'<b>'._makeLetterGrade($points/$total_points).'</b>'.($assignments_RET[ $assignment_id ][1]['DUE']||$points!=''?'':'</span>');
						else
							return _('N/A');
					}
					else
						return _('N/A');
				}
			}
		break;

		case 'COMMENT':
			if ( ! $assignment_id)
			{
			}
			else
			{
				if ( ! empty( $_REQUEST['include_all'] )
					|| ( $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'] != ''
						|| ! $assignments_RET[ $assignment_id ][1]['DUE_EPOCH']
						|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] >= $THIS_RET['START_EPOCH']
						&& ( ! $THIS_RET['END_EPOCH']
							|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] <= $THIS_RET['END_EPOCH'] ) ) )
				{
					return TextInput(
						$current_RET[ $THIS_RET['STUDENT_ID'] ][ $assignment_id ][1]['COMMENT'],
						'values[' . $THIS_RET['STUDENT_ID'] . '][' . $assignment_id . '][COMMENT]',
						'',
						' maxlength=100'
					);
				}
			}
		break;
	}

}

function _makeExtraStuCols( $value, $column )
{
	global $THIS_RET,
		$assignments_RET,
		$assignment_count,
		$count_assignments,
		$max_allowed;

	//FJ default points
	if (is_null($THIS_RET['POINTS']))
		$THIS_RET['POINTS'] = $assignments_RET[$THIS_RET['ASSIGNMENT_ID']][1]['DEFAULT_POINTS'];

	switch ( $column )
	{
		case 'POINTS':
			$assignment_count++;

			//FJ default points
			$div = true;
			if (is_null($value))
			{
				$value = $assignments_RET[$THIS_RET['ASSIGNMENT_ID']][1]['DEFAULT_POINTS'];
				$div = false;
			}

			if ( $value=='-1')
				$value = '*';
			elseif (mb_strpos($value,'.'))
				$value = rtrim(rtrim($value,'0'),'.');

//			return '<table cellspacing=0 cellpadding=1><tr><td>'.TextInput($value,'values['.$THIS_RET['STUDENT_ID'].']['.$THIS_RET['ASSIGNMENT_ID'].'][POINTS]','',' size=2 maxlength=7 tabindex='.$tabindex).'</td><td>&nbsp;/&nbsp;</td><td>'.$THIS_RET['TOTAL_POINTS'].'</td></tr></table>';
			return '<span' . ( $div ? ' class="span-grade-points"' : '' ) . '>' .
				TextInput(
					$value,
					'values[' . $THIS_RET['STUDENT_ID'] . '][' . $THIS_RET['ASSIGNMENT_ID'] . '][POINTS]',
					'',
					' size=2 maxlength=7',
					$div
				) . '</span>
				<span>&nbsp;/&nbsp;' . $THIS_RET['TOTAL_POINTS'] . '</span>';
		break;

		case 'PERCENT_GRADE':
			if ( $THIS_RET['TOTAL_POINTS']!=0)
			{
				if ( $THIS_RET['POINTS']!='-1')
					return ($THIS_RET['DUE']||$THIS_RET['POINTS']!=''?($THIS_RET['POINTS']>$THIS_RET['TOTAL_POINTS']*$max_allowed?'<span style="color:red">':'<span>'):'<span>')._Percent($THIS_RET['POINTS']/$THIS_RET['TOTAL_POINTS'],0).'</span>';
				else
					return _('N/A');
			}
			else
				return _('E/C');
		break;

		case 'LETTER_GRADE':
			if ( $THIS_RET['TOTAL_POINTS']!=0)
			{
				if ( $THIS_RET['POINTS']!='-1')
					return ($THIS_RET['DUE']||$THIS_RET['POINTS']!=''?'':'<span style="color:gray">').'<b>'._makeLetterGrade($THIS_RET['POINTS']/$THIS_RET['TOTAL_POINTS']).'</b>'.($THIS_RET['DUE']||$THIS_RET['POINTS']!=''?'':'</span>');
				else
					return _('N/A');
			}
			else
				return _('N/A');
		break;

		case 'COMMENT':

			return TextInput(
				$value,
				'values[' . $THIS_RET['STUDENT_ID'] . '][' . $THIS_RET['ASSIGNMENT_ID'] . '][COMMENT]',
				'',
				' maxlength=100'
			);
		break;
	}
}

function _makeExtraCols( $assignment_id, $column )
{
	global $THIS_RET,
		$assignments_RET,
		$current_RET,
		$old_student_id,
		$student_count,
		$count_students,
		$max_allowed;

	if ( $THIS_RET['STUDENT_ID']!=$old_student_id)
	{
		$student_count++;

		$old_student_id = $THIS_RET['STUDENT_ID'];
	}

	$total_points = $assignments_RET[ $assignment_id ][1]['POINTS'];

	if ( ! empty( $_REQUEST['include_all'] )
		|| ($current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'] != ''
			|| ! $assignments_RET[ $assignment_id ][1]['DUE_EPOCH']
			|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] >= $THIS_RET['START_EPOCH']
			&& ( ! $THIS_RET['END_EPOCH']
				|| $assignments_RET[ $assignment_id ][1]['DUE_EPOCH'] <= $THIS_RET['END_EPOCH'] ) ) )
	{
		//FJ default points
		$points = $current_RET[$THIS_RET['STUDENT_ID']][ $assignment_id ][1]['POINTS'];
		$div = true;

		if (is_null($points))
		{
			$points = $assignments_RET[ $assignment_id ][1]['DEFAULT_POINTS'];
			$div = false;
		}

		if ( $points == '-1' )
		{
			$points = '*';
		}
		elseif ( mb_strpos( $points, '.' ) )
		{
			$points = rtrim( rtrim( $points, '0' ), '.' );
		}

		if ( $total_points != 0 )
		{
			if ( $points != '*' )
			{
				// modif Francois: display letter grade according to Configuration
				return '<span' . ( $div ? ' class="span-grade-points"' : '' ) . '>' .
					TextInput(
						$points,
						'values[' . $THIS_RET['STUDENT_ID'] . '][' . $assignment_id . '][POINTS]',
						'',
						' size=2 maxlength=7',
						$div
					) . '</span>
					<span>&nbsp;/&nbsp;' . $total_points .
					( ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ) >= 0 ?
						'&nbsp;&minus;&nbsp;' . ( $assignments_RET[ $assignment_id ][1]['DUE'] || $points != '' ?
							( $points > $total_points * $max_allowed ?
								'<span style="color:red">' :
								'<span>'
							) :
							'<span>' ) .
						_Percent( $points / $total_points, 0 ) . '</span>' :
						'' ) .
					( ProgramConfig( 'grades', 'GRADES_DOES_LETTER_PERCENT' ) <= 0 ?
						'&nbsp;&minus;&nbsp;<b>' . _makeLetterGrade( $points / $total_points ) . '</b>' :
						'' ) . '</span>';
			}

			//return '<table cellspacing=0 cellpadding=1><tr align=center><td>'.TextInput($points,'values['.$THIS_RET['STUDENT_ID'].']['.$assignment_id.'][POINTS]','',' size=2 maxlength=7 tabindex='.$tabindex).'<hr />'.$total_points.'</td><td>&nbsp;'._('N/A').'<br />&nbsp;'._('N/A').'</td></tr></table>';
			return '<span' . ( $div ? ' class="span-grade-points"' : '' ) . '>' .
				TextInput(
					$points,
					'values[' . $THIS_RET['STUDENT_ID'] . '][' . $assignment_id . '][POINTS]',
					'',
					' size=2 maxlength=7',
					$div
				) . '</span>
				<span>&nbsp;/&nbsp;' . $total_points . '&nbsp;&minus;&nbsp;' . _( 'N/A' ) . '</span>';
		}

		//return '<table class="cellspacing-0"><tr class="center"><td>'.TextInput($points,'values['.$THIS_RET['STUDENT_ID'].']['.$assignment_id.'][POINTS]','',' size=2 maxlength=7 tabindex='.$tabindex).'<hr />'.$total_points.'</td><td>&nbsp;E/C</td></tr></table>';
		return '<span' . ( $div ? ' class="span-grade-points"' : '' ) . '>' .
			TextInput(
				$points,
				'values[' . $THIS_RET['STUDENT_ID'] . '][' . $assignment_id . '][POINTS]',
				'',
				' size=2 maxlength=7',
				$div
			) . '</span>
			<span>&nbsp;/&nbsp;' . $total_points . '&nbsp;&minus;&nbsp;' . _( 'E/C' ) . '</span>';
	}
}

function _Percent( $num, $decimals = 2 )
{
	return number_format( $num * 100, 2 ) . '%';
}
