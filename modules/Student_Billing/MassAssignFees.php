<?php

if ( $_REQUEST['modfunc'] === 'save' )
{
	if ( count( $_REQUEST['student'] )
		&& AllowEdit() )
	{
		//FJ fix SQL bug invalid amount
		if (is_numeric($_REQUEST['amount']))
		{
			$due_date = RequestedDate(
				$_REQUEST['year_due'],
				$_REQUEST['month_due'],
				$_REQUEST['day_due']
			);

			if ( $due_date )
			{
				foreach ( (array) $_REQUEST['student'] as $student_id => $yes)
				{
						$sql = "INSERT INTO BILLING_FEES (STUDENT_ID,ID,TITLE,AMOUNT,SYEAR,SCHOOL_ID,ASSIGNED_DATE,DUE_DATE,COMMENTS)
									values('".$student_id."',".db_seq_nextval('BILLING_FEES_SEQ').",'".$_REQUEST['title']."','".preg_replace('/[^0-9.-]/','',$_REQUEST['amount'])."','".UserSyear()."','".UserSchool()."','".DBDate()."','".$due_date."','".$_REQUEST['comments']."')";
						DBQuery($sql);
				}
				$note[] = button('check') .'&nbsp;'._('That fee has been added to the selected students.');
			}
			else
				$error[] = _('The date you entered is not valid');
		}
		else
			$error[] = _('Please enter a valid Amount.');
	}
	else
		$error[] = _('You must choose at least one student.');

	// Unset modfunc & redirect URL.
	RedirectURL( 'modfunc' );
}


if ( ! $_REQUEST['modfunc'] )

{
	DrawHeader( ProgramTitle() );

	echo ErrorMessage( $error );

	echo ErrorMessage( $note, 'note' );

	if ( $_REQUEST['search_modfunc']=='list')
	{
		echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=save" method="POST">';
		DrawHeader( '', SubmitButton( _( 'Add Fee to Selected Students' ) ) );

		echo '<br />';

		PopTable('header', _('Fee'));

		echo '<table class="col1-align-right">';

		echo '<tr><td>'._('Title').'</td><td><input type="text" name="title" required /></td></tr>';

		echo '<tr><td>'._('Amount').'</td><td><input type="text" name="amount" size="5" maxlength="10" required /></td></tr>';

		echo '<tr><td>' . _( 'Due Date' ) . '</td>
			<td>' . DateInput( DBDate(), 'due', '', false, false ) . '</td></tr>';

		echo '<tr><td>'._('Comment').'</td><td><input type="text" name="comments" /></td></tr>';

		echo '</table>';

		PopTable('footer');

		echo '<br />';
	}
}

if ( ! $_REQUEST['modfunc'] )
{
	$extra['link'] = array( 'FULL_NAME' => false );
	$extra['SELECT'] = ",NULL AS CHECKBOX";
	$extra['functions'] = array( 'CHECKBOX' => '_makeChooseCheckbox' );
	$extra['columns_before'] = array( 'CHECKBOX' => '</a><input type="checkbox" value="Y" name="controller" onclick="checkAll(this.form,this.checked,\'student\');"><A>' );
	$extra['new'] = true;


	Search( 'student_id', $extra );

	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<br /><div class="center">' . SubmitButton( _( 'Add Fee to Selected Students' ) ) . '</div>';
		echo '</form>';
	}
}

function _makeChooseCheckbox($value,$title)
{	global $THIS_RET;

	return '<input type="checkbox" name="student['.$THIS_RET['STUDENT_ID'].']" value="Y" />';
}
