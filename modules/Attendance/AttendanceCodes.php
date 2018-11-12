<?php

DrawHeader( ProgramTitle() );

if ( empty( $_REQUEST['table'] ) )
{
	$_REQUEST['table'] = '0';
}

if ( $_REQUEST['modfunc'] === 'update'
	&& AllowEdit() )
{
	if ( ! empty( $_REQUEST['values'] )
		&& ! empty( $_POST['values'] ) )
	{
		foreach ( (array) $_REQUEST['values'] as $id => $columns )
		{
			// FJ fix SQL bug invalid sort order.
			if (empty($columns['SORT_ORDER']) || is_numeric($columns['SORT_ORDER']))
			{
				if ( $columns['DEFAULT_CODE']=='Y')
					DBQuery("UPDATE ATTENDANCE_CODES SET DEFAULT_CODE=NULL WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND TABLE_NAME='".$_REQUEST['table']."'");

				if ( $id!='new')
				{
					if ( $_REQUEST['table']!='new')
						$sql = "UPDATE ATTENDANCE_CODES SET ";
					else
						$sql = "UPDATE ATTENDANCE_CODE_CATEGORIES SET ";

					foreach ( (array) $columns as $column => $value)
						$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";

					$sql = mb_substr($sql,0,-1) . " WHERE ID='".$id."'";
					DBQuery($sql);
				}
				// New: check for Title
				elseif ( $columns['TITLE'] )
				{
					if ( $_REQUEST['table']!='new')
					{
						$sql = "INSERT INTO ATTENDANCE_CODES ";
						$fields = 'ID,SCHOOL_ID,SYEAR,TABLE_NAME,';
						$values = db_seq_nextval('ATTENDANCE_CODES_SEQ').",'".UserSchool()."','".UserSyear()."','".$_REQUEST['table']."',";
					}
					else
					{
						$sql = "INSERT INTO ATTENDANCE_CODE_CATEGORIES ";
						$fields = 'ID,SCHOOL_ID,SYEAR,';
						$values = db_seq_nextval('ATTENDANCE_CODE_CATEGORIES_SEQ').",'".UserSchool()."','".UserSyear()."',";
					}

					$go = false;
					foreach ( (array) $columns as $column => $value)
					{
						if (isset($value) && $value!='')
						{
							$fields .= DBEscapeIdentifier( $column ) . ',';
							$values .= "'" . $value . "',";
							$go = true;
						}
					}
					$sql .= '(' . mb_substr( $fields, 0, -1 ) . ') values(' . mb_substr( $values, 0, -1 ) . ')';

					if ( $go)
						DBQuery($sql);
				}
			}
			else
				$error[] = _('Please enter a valid Sort Order.');
		}
	}

	// Unset modfunc & redirect URL.
	RedirectURL( 'modfunc' );
}

if ( $_REQUEST['modfunc'] === 'remove'
	&& AllowEdit() )
{
	if ( $_REQUEST['table']!='new')
	{
		if ( DeletePrompt( _( 'Attendance Code' ) ) )
		{
			DBQuery("DELETE FROM ATTENDANCE_CODES WHERE ID='" . $_REQUEST['id'] . "'");

			// Unset modfunc & ID & redirect URL.
			RedirectURL( array( 'modfunc', 'id' ) );
		}
	}
	elseif ( DeletePrompt( _( 'Category' ) ) )
	{
		DBQuery( "DELETE FROM ATTENDANCE_CODE_CATEGORIES
			WHERE ID='" . $_REQUEST['id'] . "'" );

		DBQuery( "DELETE FROM ATTENDANCE_CODES
			WHERE TABLE_NAME='" . $_REQUEST['id'] . "'" );

		DBQuery( "UPDATE COURSE_PERIODS
			SET DOES_ATTENDANCE=replace(DOES_ATTENDANCE,'," . $_REQUEST['id'] . ",',',')
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'" );

		DBQuery( "UPDATE COURSE_PERIODS
			SET DOES_ATTENDANCE=NULL
			WHERE DOES_ATTENDANCE=','
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'" );

		// Unset modfunc & ID & redirect URL.
		RedirectURL( array( 'modfunc', 'id' ) );
	}
}

// FJ fix SQL bug invalid sort order
echo ErrorMessage( $error );

if ( ! $_REQUEST['modfunc'] )
{
	if ( $_REQUEST['table']!=='new')
	{
		$sql = "SELECT ID,TITLE,SHORT_NAME,TYPE,DEFAULT_CODE,STATE_CODE,SORT_ORDER FROM ATTENDANCE_CODES WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND TABLE_NAME='".$_REQUEST['table']."' ORDER BY SORT_ORDER,TITLE";
		$QI = DBQuery($sql);
		$attendance_codes_RET = DBGet($QI,array('TITLE' => '_makeTextInput','SHORT_NAME' => '_makeTextInput','SORT_ORDER' => '_makeTextInput','TYPE' => '_makeSelectInput','STATE_CODE' => '_makeSelectInput','DEFAULT_CODE' => '_makeCheckBoxInput'));
	}

	$tabs = array(array('title' => _('Attendance'),'link' => 'Modules.php?modname='.$_REQUEST['modname'].'&table=0'));
	$categories_RET = DBGet(DBQuery("SELECT ID,TITLE FROM ATTENDANCE_CODE_CATEGORIES WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"));
	foreach ( (array) $categories_RET as $category)
		$tabs[] = array('title' => $category['TITLE'],'link' => 'Modules.php?modname='.$_REQUEST['modname'].'&table='.$category['ID']);

	if ( $_REQUEST['table']!='new')
	{
		$sql = "SELECT ID,TITLE,SHORT_NAME,TYPE,DEFAULT_CODE,STATE_CODE,SORT_ORDER FROM ATTENDANCE_CODES WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND TABLE_NAME='".$_REQUEST['table']."' ORDER BY SORT_ORDER,TITLE";
		$functions = array('TITLE' => '_makeTextInput','SHORT_NAME' => '_makeTextInput','SORT_ORDER' => '_makeTextInput','TYPE' => '_makeSelectInput','DEFAULT_CODE' => '_makeCheckBoxInput');
		$LO_columns = array('TITLE' => _('Title'),'SHORT_NAME' => _('Short Name'),'SORT_ORDER' => _('Sort Order'),'TYPE' => _('Type'),'DEFAULT_CODE' => _('Default for Teacher'));
		if ( $_REQUEST['table']=='0')
		{
			$functions['STATE_CODE'] = '_makeSelectInput';
			$LO_columns['STATE_CODE'] = _('State Code');
		}

		$link['add']['html'] = array('TITLE'=>_makeTextInput('','TITLE'),'SHORT_NAME'=>_makeTextInput('','SHORT_NAME'),'SORT_ORDER'=>_makeTextInput('','SORT_ORDER'),'TYPE'=>_makeSelectInput('','TYPE'),'DEFAULT_CODE'=>_makeCheckBoxInput('','DEFAULT_CODE'));

		if ( $_REQUEST['table']=='0')
			$link['add']['html']['STATE_CODE'] = _makeSelectInput('','STATE_CODE');

		$link['remove']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&modfunc=remove&table='.$_REQUEST['table'];
		$link['remove']['variables'] = array('id' => 'ID');

		$tabs[] = array('title'=>button('add', '', '', 'smaller'),'link' => 'Modules.php?modname='.$_REQUEST['modname'].'&table=new');
	}
	else
	{
		$sql = "SELECT ID,TITLE,SORT_ORDER FROM ATTENDANCE_CODE_CATEGORIES WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' ORDER BY SORT_ORDER,TITLE";
		$functions = array('TITLE' => '_makeTextInput','SORT_ORDER' => '_makeTextInput');
		$LO_columns = array('TITLE' => _('Title'),'SORT_ORDER' => _('Sort Order'));

		$link['add']['html'] = array('TITLE'=>_makeTextInput('','TITLE'),'SORT_ORDER'=>_makeTextInput('','SORT_ORDER'));
		$link['remove']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&modfunc=remove&table=new';
		$link['remove']['variables'] = array('id' => 'ID');

		$tabs[] = array('title'=>button('add', '', '', 'smaller'),'link' => 'Modules.php?modname='.$_REQUEST['modname'].'&table=new');
	}
	$LO_ret = DBGet(DBQuery($sql),$functions);

	echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=update&table='.$_REQUEST['table'].'" method="POST">';
	DrawHeader( '', SubmitButton() );
	echo '<br />';

//FJ css WPadmin
	$LO_options = array('count'=>false,'download'=>false,'search'=>false,'header'=>WrapTabs($tabs,'Modules.php?modname='.$_REQUEST['modname'].'&table='.$_REQUEST['table']));
//	ListOutput($LO_ret,$LO_columns,'.','.',$link,array(),array('count'=>false,'download'=>false,'search'=>false));
	ListOutput($LO_ret,$LO_columns,'.','.',$link,array(),$LO_options);

	echo '<br /><div class="center">' . SubmitButton() . '</div>';
	echo '</form>';
}

function _makeTextInput( $value, $name )
{
	global $THIS_RET;

	if ( $THIS_RET['ID'] )
	{
		$id = $THIS_RET['ID'];
	}
	else
	{
		$id = 'new';
	}

	$extra = '';

	if ( $name === 'SHORT_NAME'
		|| $name === 'SORT_ORDER' )
	{
		$extra .= 'size=5 maxlength=10';
	}

	if ( $id !== 'new'
		&& ( $name === 'TITLE'
			|| $name === 'SHORT_NAME' ) )
	{
		$extra .= ' required';
	}

	return TextInput( $value, 'values[' . $id . '][' . $name . ']', '', $extra );
}

function _makeSelectInput( $value, $name )
{
	global $THIS_RET;

	if ( $THIS_RET['ID'] )
	{
		$id = $THIS_RET['ID'];
	}
	else
	{
		$id = 'new';
	}

	if ( $name === 'TYPE' )
	{
		$options = array(
			'teacher' => _( 'Teacher & Office' ),
			'official' => _( 'Office Only' ),
		);
	}
	elseif ( $name === 'STATE_CODE' )
	{
		$options = array(
			'P' => _( 'Present' ),
			'A' => _( 'Absent' ),
			'H' => _( 'Half Day' ),
		);
	}

	return SelectInput(
		$value,
		'values[' . $id . '][' . $name . ']',
		'',
		$options,
		( $id === 'new' ? 'N/A' : false )
	);
}

function _makeCheckBoxInput( $value, $name )
{
	global $THIS_RET;

	if ( $THIS_RET['ID'] )
	{
		$id = $THIS_RET['ID'];
	}
	else
	{
		$id = 'new';
	}

	$new = ! $THIS_RET['ID'];

	return CheckBoxInput(
		$value,
		'values[' . $id . '][' . $name . ']',
		'',
		'',
		$new
	);
}
