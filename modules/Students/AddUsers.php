<?php

DrawHeader( ProgramTitle() );

if ( $_REQUEST['modfunc'] === 'save'
	&& AllowEdit()
	&& UserStudentID() )
{
	if ( isset( $_REQUEST['staff'] )
		&& is_array( $_REQUEST['staff'] ) )
	{
		$current_RET = DBGet(DBQuery("SELECT STAFF_ID FROM STUDENTS_JOIN_USERS WHERE STUDENT_ID='".UserStudentID()."'"),array(),array('STAFF_ID'));
		foreach ( (array) $_REQUEST['staff'] as $staff_id => $yes)
		{
			if ( ! $current_RET[ $staff_id ])
			{
				$sql = "INSERT INTO STUDENTS_JOIN_USERS (STAFF_ID,STUDENT_ID) values('".$staff_id."','".UserStudentID()."')";
				DBQuery($sql);

				//hook
				do_action('Students/AddUsers.php|user_assign_role');
			}
		}
		$note[] = _('The selected user\'s profile now includes access to the selected students.');
	}
	else
		$error[] = _('You must choose at least one user');

	// Unset modfunc & redirect URL.
	RedirectURL( 'modfunc' );
}

if ( $_REQUEST['modfunc'] === 'delete'
	&& AllowEdit()
	&& UserStudentID() )
{
	if ( DeletePrompt( _( 'student from that user' ), _( 'remove access to' ) )
		&& ! empty( $_REQUEST['staff_id_remove'] ) )
	{
		DBQuery( "DELETE FROM STUDENTS_JOIN_USERS
			WHERE STAFF_ID='" . $_REQUEST['staff_id_remove'] . "'
			AND STUDENT_ID='" . UserStudentID() . "'" );

		// Hook.
		do_action( 'Students/AddUsers.php|user_unassign_role' );

		// Unset modfunc & staff ID remove & redirect URL.
		RedirectURL( array( 'modfunc', 'staff_id_remove' ) );
	}
}

echo ErrorMessage( $note,'note' );

echo ErrorMessage( $error );

if ( ! $_REQUEST['modfunc'] )
{
	$extra['SELECT'] = ",(SELECT count(u.STAFF_ID) FROM STUDENTS_JOIN_USERS u,STAFF st WHERE u.STUDENT_ID=s.STUDENT_ID AND st.STAFF_ID=u.STAFF_ID AND st.SYEAR=ssm.SYEAR) AS ASSOCIATED";
	$extra['columns_after'] = array('ASSOCIATED' => '# '._('Associated'));

	if ( !UserStudentID())
		Search('student_id',$extra);

	if (UserStudentID())
	{
		if ( $_REQUEST['search_modfunc']=='list')
		{
			echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=save" method="POST">';
			DrawHeader( '', SubmitButton( _( 'Add Selected Parents' ) ) );
		}

		echo '<table class="center"><tr><td>';

		$current_RET = DBGet( DBQuery( "SELECT u.STAFF_ID,
			" . DisplayNameSQL( 's' ) . " AS FULL_NAME,s.LAST_LOGIN
			FROM STUDENTS_JOIN_USERS u,STAFF s
			WHERE s.STAFF_ID=u.STAFF_ID
			AND u.STUDENT_ID='" . UserStudentID() . "'
			AND s.SYEAR='" . UserSyear() . "'" ), array( 'LAST_LOGIN' => 'makeLogin' ) );

		$link['remove'] = array('link' => 'Modules.php?modname='.$_REQUEST['modname'].'&modfunc=delete','variables' => array('staff_id_remove' => 'STAFF_ID'));

		ListOutput($current_RET,array('FULL_NAME' => _('Parents'),'LAST_LOGIN' => _('Last Login')),'Associated Parent','Associated Parents',$link,array(),array('search'=>false));

		echo '</td></tr><tr><td>';

		if (AllowEdit())
		{
			unset($extra);
			$extra['link'] = array('FULL_NAME'=>false);
			$extra['SELECT'] = ",CAST (NULL AS CHAR(1)) AS CHECKBOX";
			$extra['functions'] = array('CHECKBOX' => '_makeChooseCheckbox');
			$extra['columns_before'] = array('CHECKBOX' => '</a><input type="checkbox" value="Y" name="controller" onclick="checkAll(this.form,this.checked,\'staff\');" /><A>');
			$extra['new'] = true;
			$extra['options']['search'] = false;
			$extra['profile'] = 'parent';

			Search('staff_id',$extra);
		}

		echo '</td></tr></table>';

		if ( $_REQUEST['search_modfunc']=='list')
			echo '<br /><div class="center">' . SubmitButton( _( 'Add Selected Parents' ) ) . '</div></form>';
	}
}

function _makeChooseCheckbox($value,$title)
{	global $THIS_RET;

	return '<input type="checkbox" name="staff['.$THIS_RET['STAFF_ID'].']" value="Y" />';
}
