<?php

require_once 'modules/Scheduling/includes/calcSeats0.fnc.php';

if ( $_REQUEST['modname']=='Scheduling/Scheduler.php' && ! $_REQUEST['run'])
{
	$function = 'Prompt';
	DrawHeader(ProgramTitle());
}
else
	$function = '_returnTrue';

$confirm_HTML = '<table class="width-100p"><tr><td>
	<label><input type="checkbox" name="test_mode" value="Y">&nbsp;' . _( 'Test Mode' ) . '</label>
</td></tr>
<tr><td>
	<label><input type="checkbox" name="delete" value="Y">&nbsp;' . _( 'Delete Current Schedules' ) . '</label>
</td></tr>
</table>';

$ok = $function(
	_( 'Confirm Scheduler Run' ),
	_( 'Are you sure you want to run the scheduler?' ),
	$confirm_HTML
);

if ( $ok )
{
	echo '<br />';
	PopTable('header',_('Scheduler Progress'));
	echo '<table class="cellspacing-0 center" style="border: solid 1px; height:19px"><tr>';

	for ( $i=1;$i<=100;$i++)
		echo '<td id="cell'.$i.'" style="width:3px;"></td>';

	echo '</tr></table><br /><div id="percentDIV"><span class="loading"></span> '._('Processing Requests ...').' </div>';
	PopTable('footer');
	ob_flush();
	flush();
	set_time_limit(0);

	// get the fy marking period id, there should be exactly one fy marking period
	$fy_id = DBGet(DBQuery("SELECT MARKING_PERIOD_ID FROM SCHOOL_MARKING_PERIODS WHERE MP='FY' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"));
	$fy_id = $fy_id[1]['MARKING_PERIOD_ID'];

	$custom_fields_RET = DBGet(DBQuery("SELECT ID,TITLE,TYPE FROM CUSTOM_FIELDS WHERE ID=200000000"),array(),array('ID'));
	if ( $custom_fields_RET['200000000'] && $custom_fields_RET['200000000'][1]['TYPE'] == 'select')
		$sql_gender = ',s.CUSTOM_200000000 as GENDER';
	else
		$sql_gender = ',\'None\' as GENDER';
	$sql = "SELECT r.REQUEST_ID,r.STUDENT_ID".$sql_gender.",r.SUBJECT_ID,r.COURSE_ID,MARKING_PERIOD_ID,WITH_TEACHER_ID,NOT_TEACHER_ID,WITH_PERIOD_ID,NOT_PERIOD_ID,(SELECT COUNT(*) FROM COURSE_PERIODS cp2 WHERE cp2.COURSE_ID=r.COURSE_ID) AS SECTIONS
	FROM SCHEDULE_REQUESTS r,STUDENTS s,STUDENT_ENROLLMENT ssm
	WHERE s.STUDENT_ID=ssm.STUDENT_ID AND ssm.SYEAR=r.SYEAR
	AND ('".DBDate()."' BETWEEN ssm.START_DATE AND ssm.END_DATE OR ssm.END_DATE IS NULL)
	AND s.STUDENT_ID=r.STUDENT_ID
	AND r.SYEAR='".UserSyear()."'
	AND r.SCHOOL_ID='".UserSchool()."'
	ORDER BY REQUEST_ID";//ORDER BY SECTIONS

	$requests_RET = DBGet(DBQuery($sql),array(),array('REQUEST_ID'));

	if ( $_REQUEST['delete']=='Y' && count($requests_RET)>0)
		DBQuery("DELETE FROM SCHEDULE WHERE SCHOOL_ID='".UserSchool()."' AND SYEAR='".UserSyear()."' AND (SCHEDULER_LOCK!='Y' OR SCHEDULER_LOCK IS NULL)");

	$periods_RET = DBGet(DBQuery("SELECT COURSE_PERIOD_ID,MARKING_PERIOD_ID,MP,TOTAL_SEATS,CALENDAR_ID FROM COURSE_PERIODS WHERE SCHOOL_ID='".UserSchool()."' AND SYEAR='".UserSyear()."'"));
	foreach ( (array) $periods_RET as $period)
	{
		$seats = calcSeats0($period);
		DBQuery("UPDATE COURSE_PERIODS SET FILLED_SEATS='".$seats."' WHERE COURSE_PERIOD_ID='".$period['COURSE_PERIOD_ID']."'");
	}

	$count = DBGet(DBQuery("SELECT count(*) as count from schedule WHERE SCHOOL_ID='".UserSchool()."'"));

	//FJ multiple school periods for a course period
	//$sql = "SELECT PARENT_ID,COURSE_PERIOD_ID,COURSE_ID,COURSE_ID AS COURSE,GENDER_RESTRICTION,PERIOD_ID,DAYS,TEACHER_ID,MARKING_PERIOD_ID,MP,COALESCE(TOTAL_SEATS,0)-COALESCE(FILLED_SEATS,0) AS AVAILABLE_SEATS,(SELECT COUNT(*) FROM COURSE_PERIODS cp2 WHERE cp2.COURSE_ID=cp.COURSE_ID) AS SECTIONS FROM COURSE_PERIODS cp ORDER BY SECTIONS,AVAILABLE_SEATS";
	$sql = "SELECT PARENT_ID,cp.COURSE_PERIOD_ID,COURSE_ID,COURSE_ID AS COURSE,GENDER_RESTRICTION,cpsp.PERIOD_ID,cpsp.DAYS,TEACHER_ID,MARKING_PERIOD_ID,MP,COALESCE(TOTAL_SEATS,0)-COALESCE(FILLED_SEATS,0) AS AVAILABLE_SEATS,
	(SELECT COUNT(*) FROM COURSE_PERIODS cp2 WHERE cp2.COURSE_ID=cp.COURSE_ID) AS SECTIONS
	FROM COURSE_PERIODS cp,COURSE_PERIOD_SCHOOL_PERIODS cpsp
	WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
	AND SCHOOL_ID='".UserSchool()."'
	AND SYEAR='".UserSyear()."'
	ORDER BY COURSE_ID,SHORT_NAME";//ORDER BY SECTIONS,AVAILABLE_SEATS

	$cp_parent_RET = DBGet(DBQuery($sql),array(),array('PARENT_ID'));

	//$sql = "SELECT PARENT_ID,COURSE_PERIOD_ID,COURSE_ID,COURSE_ID AS COURSE,GENDER_RESTRICTION,PERIOD_ID,DAYS,TEACHER_ID,MARKING_PERIOD_ID,MP,COALESCE(TOTAL_SEATS,0)-COALESCE(FILLED_SEATS,0) AS AVAILABLE_SEATS,(SELECT COUNT(*) FROM COURSE_PERIODS cp2 WHERE cp2.COURSE_ID=cp.COURSE_ID) AS SECTIONS FROM COURSE_PERIODS cp WHERE PARENT_ID=COURSE_PERIOD_ID ORDER BY SECTIONS,AVAILABLE_SEATS";
	$sql = "SELECT PARENT_ID,cp.COURSE_PERIOD_ID,COURSE_ID,SHORT_NAME,COURSE_ID AS COURSE,GENDER_RESTRICTION,cpsp.PERIOD_ID,cpsp.DAYS,TEACHER_ID,MARKING_PERIOD_ID,MP,COALESCE(TOTAL_SEATS,0)-COALESCE(FILLED_SEATS,0) AS AVAILABLE_SEATS,
	(SELECT COUNT(*) FROM COURSE_PERIODS cp2 WHERE cp2.COURSE_ID=cp.COURSE_ID) AS SECTIONS
	FROM COURSE_PERIODS cp,COURSE_PERIOD_SCHOOL_PERIODS cpsp
	WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
	AND PARENT_ID=cp.COURSE_PERIOD_ID
	AND SCHOOL_ID='".UserSchool()."'
	AND SYEAR='".UserSyear()."'
	ORDER BY COURSE_ID,SHORT_NAME";//ORDER BY SECTIONS,AVAILABLE_SEATS

	$cp_course_RET = DBGet(DBQuery($sql),array(),array('COURSE'));

	$mps_RET = DBGet(DBQuery("SELECT PARENT_ID,MARKING_PERIOD_ID FROM SCHOOL_MARKING_PERIODS WHERE MP='QTR' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"),array(),array('PARENT_ID','MARKING_PERIOD_ID'));

	// GET FILLED/LOCKED REQUESTS
	//FJ multiple school periods for a course period
	/*$sql = "SELECT s.STUDENT_ID,r.REQUEST_ID,s.COURSE_PERIOD_ID,cp.PARENT_ID,s.COURSE_ID,cp.PERIOD_ID FROM SCHEDULE_REQUESTS r,SCHEDULE s,COURSE_PERIODS cp WHERE
				s.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND cp.PARENT_ID=cp.COURSE_PERIOD_ID AND
				r.SYEAR='".UserSyear()."' AND r.SCHOOL_ID='".UserSchool()."' AND s.SYEAR=r.SYEAR AND s.SCHOOL_ID=r.SCHOOL_ID
				AND s.COURSE_ID=r.COURSE_ID AND r.STUDENT_ID = s.STUDENT_ID
				AND ('".DBDate()."' BETWEEN s.START_DATE AND s.END_DATE OR s.END_DATE IS NULL)";*/
	$sql = "SELECT s.STUDENT_ID,r.REQUEST_ID,s.COURSE_PERIOD_ID,cp.PARENT_ID,s.COURSE_ID,cpsp.PERIOD_ID
	FROM SCHEDULE_REQUESTS r,SCHEDULE s,COURSE_PERIODS cp,COURSE_PERIOD_SCHOOL_PERIODS cpsp
	WHERE cpsp.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID
	AND s.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID
	AND cp.PARENT_ID=cp.COURSE_PERIOD_ID
	AND r.SYEAR='".UserSyear()."'
	AND r.SCHOOL_ID='".UserSchool()."'
	AND s.SYEAR=r.SYEAR
	AND s.SCHOOL_ID=r.SCHOOL_ID
	AND s.COURSE_ID=r.COURSE_ID
	AND r.STUDENT_ID = s.STUDENT_ID
	AND ('".DBDate()."' BETWEEN s.START_DATE AND s.END_DATE OR s.END_DATE IS NULL)";

	$QI = DBQuery($sql);
	$locked_RET = DBGet($QI,array(),array('STUDENT_ID','REQUEST_ID'));

	foreach ( (array) $locked_RET as $student_id => $courses)
	{
		foreach ( (array) $courses as $request_id => $course)
		{
			$course = $course[1];
			foreach ( (array) $cp_parent_RET[$course['PARENT_ID']] as $slice)
			{
				$schedule[ $student_id ][$slice['PERIOD_ID']][] = $slice + array('REQUEST_ID' => $request_id);
				$filled[ $request_id ] = true;
			}
		}
	}
	if (ob_get_level() == 0)
		ob_start();

	$last_percent = 0;
	$completed = 0;
	$requests_count = count($requests_RET);
//FJ fix error Warning: Invalid argument supplied for foreach()
	$unfilled = array();
	foreach ( (array) $requests_RET as $request_id => $request)
	{
		// EXISTING / LOCKED COURSE
		if ( !empty($locked_RET[$request[1]['STUDENT_ID']][$request[1]['REQUEST_ID']]))
		{
			$completed++;
			continue;
		}

		$scheduled = _scheduleRequest($request[1]);

		if ( ! $scheduled)
		{
			$not_request = array();
			if ( !empty($locked_RET[$request[1]['STUDENT_ID']]))
				foreach ( (array) $locked_RET[$request[1]['STUDENT_ID']] as $request_id => $requests)
					$not_request[] = $request_id;

			$moved = _moveRequest($request[1],$not_request);

			if ( ! $moved)
				$unfilled[] = $request;
			else
				$filled[$request[1]['REQUEST_ID']] = true;
		}
		else
			$filled[$request[1]['REQUEST_ID']] = true;

		$completed++;

		$percent = round($completed*100/$requests_count,0);
		if ( $percent>$last_percent)
		{
			echo '<script>';
			for ( $i=$last_percent+1;$i<=$percent;$i++)
				echo 'cell'.$i.'.bgColor="'.Preferences('HIGHLIGHT').'";'."\r";
			echo 'document.getElementById("percentDIV").innerHTML = '.json_encode(sprintf(_('%d%% Done'),$percent)).';</script>';
			ob_flush();
			flush();
			$last_percent = $percent;
		}
	}

	echo '<!-- unfilled '.count($unfilled).' -->';
	foreach ( (array) $unfilled as $key => $request)
	{
		$scheduled = _scheduleRequest($request[1]);

		if ( ! $scheduled)
		{
			$not_request = array();
			if ( !empty($locked_RET[$request[1]['STUDENT_ID']]))
				foreach ( (array) $locked_RET[$request[1]['STUDENT_ID']] as $request_id => $requests)
					$not_request[] = $request_id;

			$moved = _moveRequest($request[1],$not_request);

			if ( $moved)
				unset($unfilled[ $key ]);
		}
		else
			unset($unfilled[ $key ]);
	}
	echo '<!-- unfilled '.count($unfilled).' -->';

	if ( $_REQUEST['test_mode']!='Y')
	{
		echo '<script>document.getElementById("percentDIV").innerHTML = '.json_encode('<span class="loading"></span> '._('Saving Schedules ...').' ').';</script>';
		echo str_pad(' ',4096);
		ob_flush();
		flush();

		$connection = db_start();
		db_trans_start($connection);

		$date = DBDate();
		$course_period_temp = '';
		$student_id_temp = '';
		$scount = 0;
		$bad_locked = 0;
		if (isset($schedule) && is_array($schedule))
			foreach ( (array) $schedule as $student_id => $periods)
			{
				$course_periods_temp = array();
				foreach ( (array) $periods as $course_periods)
				{
					foreach ( (array) $course_periods as $period_id => $course_period)
					{
						$scount++;
						//FJ multiple school periods for a course period
						if (empty($locked_RET[ $student_id ][$course_period['REQUEST_ID']]) && !(in_array($course_period['COURSE_PERIOD_ID'],$course_periods_temp)))
						{
							db_trans_query($connection,"INSERT INTO SCHEDULE (SYEAR,SCHOOL_ID,STUDENT_ID,START_DATE,COURSE_ID,COURSE_PERIOD_ID,MP,MARKING_PERIOD_ID) values('".UserSyear()."','".UserSchool()."','".$student_id."','".$date."','".$course_period['COURSE_ID']."','".$course_period['COURSE_PERIOD_ID']."','".$course_period['MP']."','".$course_period['MARKING_PERIOD_ID']."');");

							//hook
							do_action('Scheduling/Scheduler.php|schedule_student');
						}
						else
							$bad_locked++;
						//	db_trans_query($connection,"INSERT INTO SCHEDULE (SYEAR,SCHOOL_ID,STUDENT_ID,START_DATE,COURSE_ID,COURSE_PERIOD_ID,MP,MARKING_PERIOD_ID) values('".UserSyear()."','".UserSchool()."','".$student_id."','".$date."','".$course_period['COURSE_ID']."','".$course_period['COURSE_PERIOD_ID']."','".$course_period['MP']."','".$course_period['MARKING_PERIOD_ID']."');");

						//FJ multiple school periods for a course period
						$course_periods_temp[] = $course_period['COURSE_PERIOD_ID'];
					}
				}
			}
		echo '<!-- Bad Locked '.$scount.' -->';
		echo '<!-- Schedule Count() '.$scount.'-->';
		//echo 'Empty Courses:';
		foreach ( (array) $cp_parent_RET as $parent_id => $course_period)
		{
			$course_period = $course_period[1];
			//if ( $course_period['AVAILABLE_SEATS']<='0')
			//	echo $course_period['COURSE_ID'].': '.$course_period['COURSE_PERIOD_ID'].'<br />';
			db_trans_query($connection,"UPDATE COURSE_PERIODS SET FILLED_SEATS=TOTAL_SEATS-'".$course_period['AVAILABLE_SEATS']."' WHERE PARENT_ID='".$parent_id."'");
		}
		db_trans_commit($connection);
	}

	if ( $_REQUEST['test_mode']!='Y' || $_REQUEST['delete']=='Y')
	{
		echo '<script>document.getElementById("percentDIV").innerHTML = '.json_encode('<span class="loading"></span> '._('Optimizing ...').' ').';</script>';
		echo str_pad(' ',4096);
		ob_flush();
		flush();

		DBQuery("VACUUM");
		DBQuery("ANALYZE");
	}

	$error_msg = ErrorMessage( $error );

	echo '<script>document.getElementById("percentDIV").innerHTML = '.json_encode($error_msg. button('check', '', '', 'bigger') .' <b>'._('Done.').'</b>').';</script>';
	ob_end_flush();

	echo '<br /><br />';

	//$_REQUEST['modname'] = 'Scheduling/UnfilledRequests.php';
	$_REQUEST['search_modfunc']='list';
	require_once 'modules/Scheduling/UnfilledRequests.php';
}


function _scheduleRequest($request,$not_parent_id=false)
//{	global $requests_RET,$cp_parent_RET,$cp_course_RET,$mps_RET,$schedule,$filled,$unfilled;
{	global $cp_parent_RET,$cp_course_RET,$schedule,$filled;

	$possible = array();
	if (count($cp_course_RET[$request['COURSE_ID']]))
	{
		foreach ( (array) $cp_course_RET[$request['COURSE_ID']] as $course_period)
		{
			foreach ( (array) $cp_parent_RET[$course_period['COURSE_PERIOD_ID']] as $slice)
			{
				// ALREADY SCHEDULED HERE
				if ( $slice['PARENT_ID']==$not_parent_id)
					continue 2;

				// NO SEATS
				if ( $slice['AVAILABLE_SEATS']<=0)
					continue 2;

				// SLICE VIOLATES GENDER RESTRICTION
				if ( $slice['GENDER_RESTRICTION']!='N' && $slice['GENDER_RESTRICTION']!=mb_substr($request['GENDER'],0,1))
					continue 2;

				// PARENT VIOLATES TEACHER / PERIOD REQUESTS
				if ( $slice['PARENT_ID']==$slice['COURSE_PERIOD_ID'] && (($request['WITH_TEACHER_ID']!='' && $slice['TEACHER_ID']!=$request['WITH_TEACHER_ID']) || ($request['WITH_PERIOD_ID'] && $slice['PERIOD_ID']!=$request['WITH_PERIOD_ID']) || ($request['NOT_TEACHER_ID'] && $slice['TEACHER_ID']==$request['NOT_TEACHER_ID']) || ($request['NOT_PERIOD_ID'] && $slice['PERIOD_ID']==$request['NOT_PERIOD_ID'])))
					continue 2;

				if ( !empty($schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']]))
				{
					// SHOULD LOOK FOR COMPATIBLE CP's IF NOT THE COMPLETE WEEK/YEAR
					foreach ( (array) $schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']] as $existing_slice)
					{
						if ( $existing_slice['PARENT_ID']!=$not_parent_id && _isConflict($existing_slice,$slice))
							continue 3;
					}
				}
			}
			// No conflict
			$possible[] = $course_period;
		}
	}
	if (count($possible))
	{
		// IF THIS COURSE IS BEING SCHEDULED A SECOND TIME, DELETE THE ORIGINAL ONE
		if ( $not_parent_id)
		{
			foreach ( (array) $cp_parent_RET[ $not_parent_id ] as $key => $slice)
			{
				foreach ( (array) $schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']] as $key2 => $item)
				{
					if ( $item['COURSE_PERIOD_ID']==$slice['COURSE_PERIOD_ID'])
					{
						$filled[$schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']][ $key2 ]['REQUEST_ID']] = false;
						unset($schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']][ $key2 ]);
						$cp_parent_RET[ $not_parent_id ][ $key ]['AVAILABLE_SEATS']++;
					}
				}
			}
		}

		// CHOOSE THE BEST CP
		_scheduleBest($request,$possible);
		return true;
	}
	else
		return false; // if this point is reached, the request could not be scheduled
}

function _moveRequest($request,$not_request=false,$not_parent_id=false)
//{	global $requests_RET,$cp_parent_RET,$cp_course_RET,$mps_RET,$schedule,$filled,$unfilled;
{	global $requests_RET,$cp_parent_RET,$cp_course_RET,$schedule;

	if ( ! $not_request || !is_array($not_request))
		$not_request = array();

	if (count($cp_course_RET[$request['COURSE_ID']]))
	{
		foreach ( (array) $cp_course_RET[$request['COURSE_ID']] as $course_period)
		{
			// CLEAR OUT A SLOT FOR EACH $slice
			foreach ( (array) $cp_parent_RET[$course_period['PARENT_ID']] as $slice)
			{
				/* Don't bother to move courses around if request can't be scheduled here anyway. */
				// SEAT COUNTS
				if ( $slice['AVAILABLE_SEATS']<=0)
					continue 2;

				// SLICE VIOLATES GENDER RESTRICTION
				if ( $slice['GENDER_RESTRICTION']!='N' && $slice['GENDER_RESTRICTION']!=mb_substr($request['GENDER'],0,1))
					continue 2;

				// PARENT VIOLATES TEACHER / PERIOD REQUESTS
				if ( $slice['PARENT_ID']==$slice['COURSE_PERIOD_ID'] && (($request['WITH_TEACHER_ID']!='' && $slice['TEACHER_ID']!=$request['WITH_TEACHER_ID']) || ($request['WITH_PERIOD_ID'] && $slice['PERIOD_ID']!=$request['WITH_PERIOD_ID']) || ($request['NOT_TEACHER_ID'] && $slice['TEACHER_ID']==$request['NOT_TEACHER_ID']) || ($request['NOT_PERIOD_ID'] && $slice['PERIOD_ID']==$request['NOT_PERIOD_ID'])))
					continue 2;

				if ( !empty($schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']]))
				{
					foreach ( (array) $schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']] as $existing_slice)
					{
						if (in_array($existing_slice['REQUEST_ID'],$not_request))
							continue 3;

						if (true)
						{
							$not_request_temp = $not_request;
							$not_request_temp[] = $existing_slice['REQUEST_ID'];
							if ( ! $scheduled = _scheduleRequest($requests_RET[$existing_slice['REQUEST_ID']][1],$existing_slice['PARENT_ID']))
							{
								if ( ! $moved = _moveRequest($requests_RET[$existing_slice['REQUEST_ID']][1],$not_request_temp,$existing_slice['PARENT_ID']))
									continue 3;
							}
						}
					}
				}
				else
				{
					// WTF???
				}
			}
			if (_scheduleRequest($request,$not_parent_id))
				return true;
		}
	}

	return false; // if this point is reached, the request could not be scheduled
}

function _isConflict($existing_slice,$slice)
//{	global $requests_RET,$cp_parent_RET,$cp_course_RET,$mps_RET,$schedule,$filled,$unfilled,$fy_id;
{	global $mps_RET,$fy_id;

	 $mp_conflict = $days_conflict = false;
	// LOOK FOR CONFLICT IN SCHEDULED SLICE -- CONFLICT == SEATS,MP,DAYS,PERIOD TIMES

	// MARKING PERIOD CONFLICTS
	if ( $existing_slice['MARKING_PERIOD_ID']=="$fy_id" || ($slice['MARKING_PERIOD_ID']=="$fy_id" && (! $request['MARKING_PERIOD_ID'] || $request['MARKING_PERIOD_ID']==$slice['MARKING_PERIOD_ID'])))
		$mp_conflict = true; // if either course is full year
	elseif ( $existing_slice['MARKING_PERIOD_ID']==$slice['MARKING_PERIOD_ID'])
		$mp_conflict = true; // if both fall in the same QTR or SEM
	elseif ( $existing_slice['MP']==$slice['MP'])
		$mp_conflict = false; // both are SEM's or QTR's, but not the same
	elseif ( $existing_slice['MP']=='SEM' && $mps_RET[$existing_slice['MARKING_PERIOD_ID']][$slice['MARKING_PERIOD_ID']])
		$mp_conflict = true; // the new course is a quarter in the existing semester
	elseif ( $mps_RET[$slice['MARKING_PERIOD_ID']][$existing_slice['MARKING_PERIOD_ID']])
		$mp_conflict = true; // the existing course is a quarter in the new semester
	else
		$mp_conflict = false; // not the same MP, but no conflict

	if ( $mp_conflict) // only look for a day conflict if there's already an MP conflict
	{
		if (mb_strlen($slice['DAYS'])+mb_strlen($existing_slice['DAYS'])>7)
			$days_conflict = true;
		else
		{
			$days_len = mb_strlen($slice['DAYS']);
			for ( $i=0;$i<$days_len;$i++)
			{
				if (mb_strpos($existing_slice['DAYS'],mb_substr($slice['DAYS'],$i,1))!==false)
				{
					$days_conflict = true;
					break;
				}
			}
		}
		if ( $days_conflict)
			return true; // Go to the next available section
	}

	return false; // There is no conflict
}

function _scheduleBest($request,$possible)
//{	global $cp_parent_RET,$schedule,$filled;
{	global $cp_parent_RET,$schedule;

	$best = $possible[0];
	if (count($possible)>1)
	{
		foreach ( (array) $possible as $course_period)
		{
			if ( $cp_parent_RET[$course_period['COURSE_PERIOD_ID']][1]['AVAILABLE_SEATS']>$cp_parent_RET[$best['COURSE_PERIOD_ID']][1]['AVAILABLE_SEATS'])
				$best = $course_period;
		}
	}
	foreach ( (array) $cp_parent_RET[$best['COURSE_PERIOD_ID']] as $key => $slice)
	{
		$schedule[$request['STUDENT_ID']][$slice['PERIOD_ID']][] = $slice + array('REQUEST_ID' => $request['REQUEST_ID']);
		$cp_parent_RET[$best['COURSE_PERIOD_ID']][ $key ]['AVAILABLE_SEATS']--;
	}
}

function _returnTrue($arg1,$arg2='',$arg3='')
{
	return true;
}
