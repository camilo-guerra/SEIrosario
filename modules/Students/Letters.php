<?php

require_once 'ProgramFunctions/MarkDownHTML.fnc.php';
require_once 'ProgramFunctions/Template.fnc.php';

if ( User( 'PROFILE' ) === 'teacher' )
{
	$_ROSARIO['allow_edit'] = true;
}

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit() )
{
	if ( count( $_REQUEST['st_arr'] ) )
	{
		// FJ bypass strip_tags on the $_REQUEST vars.
		$REQUEST_letter_text = SanitizeHTML( $_POST['letter_text'] );

		$st_list = "'" . implode( "','", $_REQUEST['st_arr'] ) . "'";

		$extra['WHERE'] = " AND s.STUDENT_ID IN (" . $st_list . ")";

		if ( $_REQUEST['mailing_labels']=='Y')
			Widgets('mailing_labels');

		$extra['SELECT'] .= ",s.FIRST_NAME AS NICK_NAME";

		if (User('PROFILE')=='admin')
		{
			if ( $_REQUEST['w_course_period_id_which']=='course_period' && $_REQUEST['w_course_period_id'] )
			{
				$extra['SELECT'] .= ",(SELECT " . DisplayNameSQL( 'st' ) . "
				FROM STAFF st,COURSE_PERIODS cp
				WHERE st.STAFF_ID=cp.TEACHER_ID
				AND cp.COURSE_PERIOD_ID='" . $_REQUEST['w_course_period_id'] . "') AS TEACHER";

				$extra['SELECT'] .= ",(SELECT cp.ROOM FROM COURSE_PERIODS cp WHERE cp.COURSE_PERIOD_ID='".$_REQUEST['w_course_period_id']."') AS ROOM";
			}
			else
			{
				//FJ multiple school periods for a course period
				//$extra['SELECT'] .= ",(SELECT st.FIRST_NAME||' '||st.LAST_NAME FROM STAFF st,COURSE_PERIODS cp,SCHOOL_PERIODS p,SCHEDULE ss WHERE st.STAFF_ID=cp.TEACHER_ID AND cp.PERIOD_id=p.PERIOD_ID AND p.ATTENDANCE='Y' AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND ss.STUDENT_ID=s.STUDENT_ID AND ss.SYEAR='".UserSyear()."' AND ss.MARKING_PERIOD_ID IN (".GetAllMP('QTR',GetCurrentMP('QTR',DBDate(),false)).") AND (ss.START_DATE<='".DBDate()."' AND (ss.END_DATE>='".DBDate()."' OR ss.END_DATE IS NULL)) ORDER BY p.SORT_ORDER LIMIT 1) AS TEACHER";
				$extra['SELECT'] .= ",(SELECT " . DisplayNameSQL( 'st' ) . "
				FROM STAFF st,COURSE_PERIODS cp,SCHOOL_PERIODS p,SCHEDULE ss,COURSE_PERIOD_SCHOOL_PERIODS cpsp
				WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
				AND st.STAFF_ID=cp.TEACHER_ID
				AND cpsp.PERIOD_id=p.PERIOD_ID
				AND p.ATTENDANCE='Y'
				AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID
				AND ss.STUDENT_ID=s.STUDENT_ID
				AND ss.SYEAR='" . UserSyear() . "'
				AND ss.MARKING_PERIOD_ID IN (" . GetAllMP( 'QTR', GetCurrentMP( 'QTR', DBDate(), false ) ) . ")
				AND (ss.START_DATE<='" . DBDate() . "'
					AND (ss.END_DATE>='" . DBDate() . "' OR ss.END_DATE IS NULL))
				ORDER BY p.SORT_ORDER LIMIT 1) AS TEACHER";

				//$extra['SELECT'] .= ",(SELECT cp.ROOM FROM COURSE_PERIODS cp,SCHOOL_PERIODS p,SCHEDULE ss WHERE cp.PERIOD_id=p.PERIOD_ID AND p.ATTENDANCE='Y' AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND ss.STUDENT_ID=s.STUDENT_ID AND ss.SYEAR='".UserSyear()."' AND ss.MARKING_PERIOD_ID IN (".GetAllMP('QTR',GetCurrentMP('QTR',DBDate(),false)).") AND (ss.START_DATE<='".DBDate()."' AND (ss.END_DATE>='".DBDate()."' OR ss.END_DATE IS NULL)) ORDER BY p.SORT_ORDER LIMIT 1) AS ROOM";
				$extra['SELECT'] .= ",(SELECT cp.ROOM FROM COURSE_PERIODS cp,SCHOOL_PERIODS p,SCHEDULE ss,COURSE_PERIOD_SCHOOL_PERIODS cpsp WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID AND cpsp.PERIOD_id=p.PERIOD_ID AND p.ATTENDANCE='Y' AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND ss.STUDENT_ID=s.STUDENT_ID AND ss.SYEAR='".UserSyear()."' AND ss.MARKING_PERIOD_ID IN (".GetAllMP('QTR',GetCurrentMP('QTR',DBDate(),false)).") AND (ss.START_DATE<='".DBDate()."' AND (ss.END_DATE>='".DBDate()."' OR ss.END_DATE IS NULL)) ORDER BY p.SORT_ORDER LIMIT 1) AS ROOM";
			}
		}
		else
		{
			$extra['SELECT'] .= ",(SELECT " . DisplayNameSQL( 'st' ) . "
			FROM STAFF st,COURSE_PERIODS cp
			WHERE st.STAFF_ID=cp.TEACHER_ID
			AND cp.COURSE_PERIOD_ID='" . UserCoursePeriod() . "') AS TEACHER";

			$extra['SELECT'] .= ",(SELECT cp.ROOM FROM COURSE_PERIODS cp WHERE cp.COURSE_PERIOD_ID='".UserCoursePeriod()."') AS ROOM";
		}

		$RET = GetStuList( $extra );

		if ( count( $RET ) )
		{
			SaveTemplate( $REQUEST_letter_text );

			// $REQUEST_letter_text = nl2br(str_replace("''","'",str_replace('  ',' &nbsp;',$REQUEST_letter_text)));

			$handle = PDFStart();

			foreach ( (array) $RET as $student )
			{
				$student_points = $total_points = 0;
				unset($_ROSARIO['DrawHeader']);

				if ( $_REQUEST['mailing_labels']=='Y')
					echo '<br /><br /><br />';
				//DrawHeader(ParseMLField(Config('TITLE')).' Letter');
				DrawHeader('&nbsp;');
				DrawHeader($student['FULL_NAME'],$student['STUDENT_ID']);
				DrawHeader($student['GRADE_ID'],$student['SCHOOL_TITLE']);
				//DrawHeader('',GetMP(GetCurrentMP('QTR',DBDate(),false)));
				DrawHeader(ProperDate(DBDate()));

				if ( $_REQUEST['mailing_labels']=='Y')
					echo '<br /><br /><table class="width-100p"><tr><td style="width:50px;"> &nbsp; </td><td>'.$student['MAILING_LABEL'].'</td></tr></table><br />';

				$letter_text = $REQUEST_letter_text;
				foreach ( (array) $student as $column => $value)
					$letter_text = str_replace('__'.$column.'__',$value,$letter_text);

				echo '<br />'.$letter_text;
				echo '<div style="page-break-after: always;"></div>';
			}
			PDFStop($handle);
		}
		else
			BackPrompt(_('No Students were found.'));
	}
	else
		BackPrompt(_('You must choose at least one student.'));
}

if ( ! $_REQUEST['modfunc'] )
{
	DrawHeader( ProgramTitle() );

	if ( $_REQUEST['search_modfunc']=='list')
	{
		echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=save&include_inactive='.$_REQUEST['include_inactive'].'&_search_all_schools='.$_REQUEST['_search_all_schools'].'&_ROSARIO_PDF=true" method="POST">';

		$extra['header_right'] = SubmitButton( _('Print Letters for Selected Students' ) );

		Widgets('mailing_labels');
		$extra['extra_header_left'] = '<table>' . $extra['search'] . '</table>';
		$extra['search'] = '';

		// FJ add TinyMCE to the textarea.
		$extra['extra_header_left'] .= '<table class="width-100p"><tr><td>' .
			TinyMCEInput(
				GetTemplate(),
				'letter_text',
				_( 'Letter Text' )
			) .
			'</td></tr>';

		$extra['extra_header_left'] .= '<tr><td>' . _( 'Substitutions' ) . '<br /><table><tr class="st">';
		$extra['extra_header_left'] .= '<td>__FULL_NAME__</td><td>= '._( 'Display Name' ).'</td><td>&nbsp;</td>';
		$extra['extra_header_left'] .= '</tr><tr class="st">';
		$extra['extra_header_left'] .= '<td>__FIRST_NAME__</td><td>= '._('First Name').'</td><td>&nbsp;</td>';
		$extra['extra_header_left'] .= '<td>__LAST_NAME__</td><td>= '._('Last Name').'</td>';
		$extra['extra_header_left'] .= '</tr><tr class="st">';
		$extra['extra_header_left'] .= '<td>__MIDDLE_NAME__</td><td>= '._('Middle Name').'</td><td>&nbsp;</td>';
		$extra['extra_header_left'] .= '<td>__STUDENT_ID__</td><td>= '.sprintf(_('%s ID'),Config('NAME')).'</td>';
		$extra['extra_header_left'] .= '</tr><tr class="st">';
		$extra['extra_header_left'] .= '<td>__SCHOOL_TITLE__</td><td>= '._('School').'</td><td>&nbsp;</td>';
		$extra['extra_header_left'] .= '<td>__GRADE_ID__</td><td>= '._('Grade Level').'</td>';
		$extra['extra_header_left'] .= '</tr><tr class="st">';
		if (User('PROFILE')=='admin')
		{
			$extra['extra_header_left'] .= '<td>__TEACHER__</td><td>= '._('Attendance Teacher').'</td><td></td>';
			$extra['extra_header_left'] .= '<td>__ROOM__</td><td>= '._('Attendance Room').'</td>';
		}
		else
		{
			$extra['extra_header_left'] .= '<td>__TEACHER__</td><td>= '._('Your Name').'</td><td></td>';
			$extra['extra_header_left'] .= '<td>__ROOM__</td><td>= '._('Your Room').'</td>';
		}
		$extra['extra_header_left'] .= '</tr></table></td></tr></table>';
	}


	$extra['SELECT'] .= ",s.STUDENT_ID AS CHECKBOX";
	$extra['link'] = array('FULL_NAME'=>false);
	$extra['functions'] = array('CHECKBOX' => '_makeChooseCheckbox');
	$extra['columns_before'] = array('CHECKBOX' => '</a><input type="checkbox" value="Y" name="controller" checked onclick="checkAll(this.form,this.checked,\'st_arr\');"><A>');
	$extra['options']['search'] = false;
	$extra['new'] = true;

	Search('student_id',$extra);
	if ( $_REQUEST['search_modfunc']=='list')
	{
		echo '<br /><div class="center">' .
			SubmitButton( _('Print Letters for Selected Students' ) ) . '</div></form>';
	}
}

function _makeChooseCheckbox($value,$title)
{
	return '<input type="checkbox" name="st_arr[]" value="'.$value.'" checked />';
}
