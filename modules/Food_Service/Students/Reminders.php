<?php
$food_service_config = ProgramConfig( 'food_service' );

// if $homeroom is null then teacher and subject for period used for attendance are used for homeroom teacher and subject
// if $homeroom is set then teacher for $homeroom subject and $homeroom are used for teacher and subject
//$homeroom = 'Homeroom';
$target = $food_service_config['FOOD_SERVICE_BALANCE_TARGET'][1]['VALUE'];
$warning = $food_service_config['FOOD_SERVICE_BALANCE_WARNING'][1]['VALUE'];
// Available substitutions for the notes...
// %N = student firstname (given) or nickname (@deprecated) (according to user preference)
// %F = student firstname
// %g = he/she according to student gender
// %G = He/She according to student gender
// %h = his/her according to student gender
// %H = His/Her according to student gender
// %P = payment amount
// %T = balance target amount
$warning_note = _('%N\'s lunch account is getting low.  Please send in at least %P with %h reminder slip.  THANK YOU!');
$negative_note = _('%N now has a <b>negative balance</b> in %h lunch account. Please send in the negative balance plus %T.  THANK YOU!');
$minimum = $food_service_config['FOOD_SERVICE_BALANCE_MINIMUM'][1]['VALUE'];
$minimum_note = _('%N now has a <b>negative balance</b> below the allowed minimum.  Please send in the negative balance plus %T.  THANK YOU!');
$year_end_note = _('%N\'s lunch account is getting low.  The requested payment anount is estimated so %h account will have a zero balance at the end of the school year.  Please send in the requested amount with %h reminder slip.  THANK YOU!');
$year_end_note = _('%N\'s lunch account is getting low.  It\'s estimated that %g needs about a %T current balance to finish the year with a zero balance.  Please send in the requested amount with %h reminder slip.  THANK YOU!');

if ( $_REQUEST['modfunc'] === 'save' )
{
	if (count($_REQUEST['st_arr']))
	{
		$st_list = "'".implode("','",$_REQUEST['st_arr'])."'";

		$students = DBGet(DBQuery("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,s.MIDDLE_NAME,s.NAME_SUFFIX,fsa.ACCOUNT_ID,fsa.STATUS,
		(SELECT BALANCE FROM FOOD_SERVICE_ACCOUNTS WHERE ACCOUNT_ID=fsa.ACCOUNT_ID) AS BALANCE,
		(SELECT TITLE FROM SCHOOLS WHERE ID=ssm.SCHOOL_ID AND SYEAR=ssm.SYEAR) AS SCHOOL,
		(SELECT TITLE FROM SCHOOL_GRADELEVELS WHERE ID=ssm.GRADE_ID) AS GRADE".
		($_REQUEST['year_end']=='Y'?",(SELECT count(1) FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID=ssm.CALENDAR_ID AND SCHOOL_DATE>CURRENT_DATE) AS DAYS,(SELECT -sum(fsti.AMOUNT) FROM FOOD_SERVICE_TRANSACTIONS fst,FOOD_SERVICE_TRANSACTION_ITEMS fsti WHERE fst.SYEAR=ssm.SYEAR AND fsti.TRANSACTION_ID=fst.TRANSACTION_ID AND fst.ACCOUNT_ID=fsa.ACCOUNT_ID AND fsti.AMOUNT<0 AND fst.TIMESTAMP BETWEEN CURRENT_DATE-14 AND CURRENT_DATE-1) AS T_AMOUNT,(SELECT count(1) FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID=ssm.CALENDAR_ID AND SCHOOL_DATE BETWEEN CURRENT_DATE-14 AND CURRENT_DATE-1) AS T_DAYS":'')."
		FROM STUDENTS s,STUDENT_ENROLLMENT ssm,FOOD_SERVICE_STUDENT_ACCOUNTS fsa
		WHERE s.STUDENT_ID IN (".$st_list.")
		AND fsa.STUDENT_ID=s.STUDENT_ID
		AND ssm.STUDENT_ID=s.STUDENT_ID
		AND ssm.SYEAR='".UserSyear()."'"));

		$handle = PDFStart();

		foreach ( (array) $students as $student )
		{
			if ( $homeroom )
			{
				$teacher = DBGet( DBQuery( "SELECT " . DisplayNameSQL( 's' ) . " AS FULL_NAME,cs.TITLE
				FROM STAFF s,SCHEDULE sch,COURSE_PERIODS cp,COURSES c,COURSE_SUBJECTS cs
				WHERE s.STAFF_ID=cp.TEACHER_ID
				AND sch.STUDENT_ID='" . $student['STUDENT_ID'] . "'
				AND cp.COURSE_ID=sch.COURSE_ID
				AND c.COURSE_ID=cp.COURSE_ID
				AND c.SUBJECT_ID=cs.SUBJECT_ID
				AND cs.TITLE='" . $homeroom . "'
				AND sch.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID
				AND sch.SYEAR='" . UserSyear() . "'" ) );
			}
			else
			{
				//FJ multiple school periods for a course period
				/*$teacher = DBGet(DBQuery("SELECT s.FIRST_NAME||' '||s.LAST_NAME AS FULL_NAME,cs.TITLE
				FROM STAFF s,SCHEDULE sch,COURSE_PERIODS cp,COURSES c,COURSE_SUBJECTS cs,SCHOOL_PERIODS sp
				WHERE s.STAFF_ID=cp.TEACHER_ID AND sch.STUDENT_ID='".$student['STUDENT_ID']."' AND cp.COURSE_ID=sch.COURSE_ID AND c.COURSE_ID=cp.COURSE_ID AND c.SUBJECT_ID=cs.SUBJECT_ID AND sp.PERIOD_ID=cp.PERIOD_ID AND sp.ATTENDANCE='Y' AND sch.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND sch.SYEAR='".UserSyear()."'"));*/
				$teacher = DBGet( DBQuery( "SELECT " . DisplayNameSQL( 's' ) . " AS FULL_NAME,cs.TITLE
				FROM STAFF s,SCHEDULE sch,COURSE_PERIODS cp,COURSES c,COURSE_SUBJECTS cs,SCHOOL_PERIODS sp,COURSE_PERIOD_SCHOOL_PERIODS cpsp
				WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
				AND s.STAFF_ID=cp.TEACHER_ID
				AND sch.STUDENT_ID='" . $student['STUDENT_ID'] . "'
				AND cp.COURSE_ID=sch.COURSE_ID
				AND c.COURSE_ID=cp.COURSE_ID
				AND c.SUBJECT_ID=cs.SUBJECT_ID
				AND sp.PERIOD_ID=cpsp.PERIOD_ID
				AND sp.ATTENDANCE='Y'
				AND sch.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID
				AND sch.SYEAR='" . UserSyear() . "'" ) );
			}

			$teacher = $teacher[1];

			$xstudents = DBGet( DBQuery( "SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,s.NAME_SUFFIX
			FROM STUDENTS s,FOOD_SERVICE_STUDENT_ACCOUNTS fssa
			WHERE fssa.ACCOUNT_ID='" . $student['ACCOUNT_ID'] . "'
			AND s.STUDENT_ID=fssa.STUDENT_ID
			AND s.STUDENT_ID!='" . $student['STUDENT_ID'] . "'
			AND exists(SELECT ''
				FROM STUDENT_ENROLLMENT
				WHERE STUDENT_ID=s.STUDENT_ID
				AND SYEAR='" . UserSyear() . "'
				AND (START_DATE<=CURRENT_DATE AND (END_DATE IS NULL OR CURRENT_DATE<=END_DATE)))" ) );

			$last_deposit = DBGet(DBQuery("SELECT (SELECT sum(AMOUNT) FROM FOOD_SERVICE_TRANSACTION_ITEMS WHERE TRANSACTION_ID=fst.TRANSACTION_ID) AS AMOUNT,to_char(fst.TIMESTAMP,'YYYY-MM-DD') AS DATE
			FROM FOOD_SERVICE_TRANSACTIONS fst
			WHERE fst.SHORT_NAME='DEPOSIT'
			AND fst.ACCOUNT_ID='".$student['ACCOUNT_ID']."'
			AND SYEAR='".UserSyear()."'
			ORDER BY fst.TRANSACTION_ID DESC LIMIT 1"),array('DATE' => 'ProperDate'));
			$last_deposit = $last_deposit[1];

			if ( $_REQUEST['year_end']=='Y')
			{
				$xtarget = number_format($student['DAYS']*$student['T_AMOUNT']/$student['T_DAYS'],2);
				reminder($student,$teacher,$xstudents,$xtarget,$last_deposit,$year_end_note);
			}
			else
			{
				$xtarget = number_format($target*(count($xstudents)+1),2);
				if ( $student['BALANCE'] < $minimum)
					reminder($student,$teacher,$xstudents,$xtarget,$last_deposit,$minimum_note);
				elseif ( $student['BALANCE'] < 0)
					reminder($student,$teacher,$xstudents,$xtarget,$last_deposit,$negative_note);
				elseif ( $student['BALANCE'] < $warning)
					reminder($student,$teacher,$xstudents,$xtarget,$last_deposit,$warning_note);
			}

			echo '<!-- NEED 3in -->';
		}
		PDFStop($handle);
	}
	else
		BackPrompt(_('You must choose at least one student.'));
}

if ( ! $_REQUEST['modfunc'] )

{
	if ( $_REQUEST['search_modfunc']=='list')
	{
		echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=save&_ROSARIO_PDF=true" method="POST">';
		//DrawHeader('',SubmitButton('Create Reminders for Selected Students'));
//FJ add translation
		$extra['header_right'] = SubmitButton( _( 'Create Reminders for Selected Students' ) );

		$extra['extra_header_left'] .= '<label>'._('Estimate for year end').'&nbsp;<input type="checkbox" name="year_end" value="Y" /></label>';
	}

	$extra['link'] = array('FULL_NAME'=>false);
	$extra['SELECT'] = ",s.STUDENT_ID AS CHECKBOX";
	$extra['functions'] = array('CHECKBOX' => '_makeChooseCheckbox');
	$extra['columns_before'] = array('CHECKBOX' => '</a><input type="checkbox" value="Y" checked name="controller" onclick="checkAll(this.form,this.checked,\'st_arr\');"><A>');
	$extra['new'] = true;
	$extra['options']['search'] = false;

	Widgets('fsa_balance_warning');
	Widgets('fsa_status');

	$status = DBEscapeString( _( 'Active' ) );

	$extra['SELECT'] .= ",coalesce(fssa.STATUS,'" . $status . "') AS STATUS,fsa.BALANCE";
	$extra['SELECT'] .= ",(SELECT 'Y' WHERE fsa.BALANCE < '" . $warning . "' AND fsa.BALANCE >= 0) AS WARNING";
	$extra['SELECT'] .= ",(SELECT 'Y' WHERE fsa.BALANCE < 0 AND fsa.BALANCE >= '" . $minimum . "') AS NEGATIVE";
	$extra['SELECT'] .= ",(SELECT 'Y' WHERE fsa.BALANCE < '" . $minimum . "') AS MINIMUM";

	if ( !mb_strpos($extra['FROM'],'fssa'))
	{
		$extra['FROM'] .= ',FOOD_SERVICE_STUDENT_ACCOUNTS fssa';
		$extra['WHERE'] .= ' AND fssa.STUDENT_ID=s.STUDENT_ID';
	}
	if ( !mb_strpos($extra['FROM'],'fsa'))
	{
		$extra['FROM'] .= ',FOOD_SERVICE_ACCOUNTS fsa';
		$extra['WHERE'] .= ' AND fsa.ACCOUNT_ID=fssa.ACCOUNT_ID';
	}
	$extra['functions'] += array('BALANCE' => 'red','WARNING' => 'x','NEGATIVE' => 'x','MINIMUM' => 'x');
	$extra['columns_after'] = array('BALANCE' => _('Balance'),'STATUS' => _('Status'),'WARNING' => _('Warning').'<br />&lt; '.$warning,'NEGATIVE' => _('Negative'),'MINIMUM' => _('Minimum').'<br />'.$minimum);

	Search('student_id',$extra);
	if ( $_REQUEST['search_modfunc']=='list')
	{
		echo '<br /><div class="center">' . SubmitButton( _( 'Create Reminders for Selected Students' ) ) . '</div>';
		echo '</form>';
	}
}

function reminder($student,$teacher,$xstudents,$target,$last_deposit,$note)
{
	$payment = $target - $student['BALANCE'];
	if ( $_REQUEST['year_end']=='Y')
		$payment = floor($payment * 2 + 0.99) / 2;
	if ( $payment <= 0)
		return;
	$payment = number_format($payment,2);

	echo '<table class="width-100p">';
	echo '<tr><td colspan="3" class="center"><span class="sizep1"><i><b>* * * '.($_REQUEST['year_end']=='Y'?_('Year End').' ':'')._('Lunch Payment Reminder').' * * *</b></i></span></td></tr>';
	echo '<tr><td colspan="3" class="center"><b>'.$student['SCHOOL'].'</b></td></tr>';

	echo '<tr><td style="width:33%;">';

	echo DisplayName(
		$student['FIRST_NAME'],
		$student['LAST_NAME'],
		$student['MIDDLE_NAME'],
		$student['NAME_SUFFIX']
	) . '<br />';

	echo $student['STUDENT_ID'];
	if (count($xstudents))
	{
		echo '<br />'._('Other students on this account').':';

		foreach ( (array) $xstudents as $xstudent )
		{
			echo '<br />&nbsp;&nbsp;' . DisplayName(
				$xstudent['FIRST_NAME'],
				$xstudent['LAST_NAME'],
				$xstudent['MIDDLE_NAME'],
				$xstudent['NAME_SUFFIX']
			);
		}

		echo '';
	}
	echo '</td><td style="width:33%;">';
	echo $student['GRADE'].'<br />';
	echo 'Grade';
	echo '</td><td style="width:33%;">';
	echo $teacher['FULL_NAME'].'<br />';
	echo ''.$teacher['TITLE'].' '._('Teacher').'';
	echo '</td></tr>';

	echo '<tr><td style="width:33%;">';
	echo ProperDate(DBDate()).'<br />';
	echo ''._('Today\'s Date').'';
	echo '</td><td style="width:34%;">';
	echo ($last_deposit ? $last_deposit['DATE'] : _('None')).'<br />';
	echo ''._('Date of Last Deposit').'';
	echo '</td><td style="width:33%;">';
	echo ($last_deposit ? $last_deposit['AMOUNT'] : _('None')).'<br />';
	echo ''._('Amount of Last Deposit').'';
	echo '</td></tr>';

	echo '<tr><td style="width:33%;">';
	echo ($student['BALANCE']<0 ? '<b>'.Currency($student['BALANCE']).'</b>' : Currency($student['BALANCE'])).'<br />';
	echo ''._('Balance').'';
	echo '</td><td style="width:34%;">';
	echo '<b>'.Currency($payment).'</b><br />';
	echo '<b>'.($_REQUEST['year_end']=='Y'?_('Requested Payment'):_('Mimimum Payment')).' </b>';
	echo '</td><td style="width:33%;">';
	echo $student['ACCOUNT_ID'].'<br />';
	echo ''._('Account ID').'';
	echo '</td></tr>';

	$note = str_replace(
		'%N',
		$student['FIRST_NAME'],
		$note
	);

	$note = str_replace('%F',$student['FIRST_NAME'],$note);
	$note = str_replace('%g',($student['GENDER'] ? (mb_substr($student['GENDER'],0,1)=='F' ? 'she' : 'he') : 'he/she'),$note);
	$note = str_replace('%G',($student['GENDER'] ? (mb_substr($student['GENDER'],0,1)=='F' ? 'She' : 'He') : 'He/she'),$note);
	$note = str_replace('%h',($student['GENDER'] ? (mb_substr($student['GENDER'],0,1)=='F' ? 'her' : 'his') : 'his/her'),$note);
	$note = str_replace('%H',($student['GENDER'] ? (mb_substr($student['GENDER'],0,1)=='F' ? 'Her' : 'His') : 'His/her'),$note);
//	$note = str_replace('%P',money_format('%i',$payment),$note);
	$note = str_replace('%P',Currency($payment),$note);
	$note = str_replace('%T',$target,$note);

	echo '<tr><td colspan="3">';
	echo '<br />'.$note.'<br />';
	echo '</td></tr>';
	echo '<tr><td colspan="3"><br /><br /><hr /><br /><br /></td></tr></table>';
}
