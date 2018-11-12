<?php

require_once 'ProgramFunctions/TipMessage.fnc.php';

// Set this to false to disable auto-pull-downs for the contact info Description field.
$info_apd = true;

// Add eventual Dates to $_REQUEST['values'].
AddRequestedDates( 'values', 'post' );

if ( isset( $_POST['values'] )
	&& count( $_POST['values'] )
	&& AllowEdit() )
{
	if ( ! empty( $_REQUEST['values']['EXISTING'] ) )
	{
		if ( $_REQUEST['values']['EXISTING']['address_id'] && $_REQUEST['address_id']=='old')
		{
			$_REQUEST['address_id'] = $_REQUEST['values']['EXISTING']['address_id'];
			if (count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_ADDRESS WHERE ADDRESS_ID='".$_REQUEST['address_id']."' AND STUDENT_ID='".UserStudentID()."'")))==0)
			{
				DBQuery("INSERT INTO STUDENTS_JOIN_ADDRESS (ID,STUDENT_ID,ADDRESS_ID) values(".db_seq_nextval('STUDENTS_JOIN_ADDRESS_SEQ').",'".UserStudentID()."','".$_REQUEST['address_id']."')");
				DBQuery("INSERT INTO STUDENTS_JOIN_PEOPLE (ID,STUDENT_ID,PERSON_ID,ADDRESS_ID,CUSTODY,EMERGENCY,STUDENT_RELATION) SELECT DISTINCT ON (PERSON_ID) ".db_seq_nextval('STUDENTS_JOIN_PEOPLE_SEQ').",'".UserStudentID()."',PERSON_ID,ADDRESS_ID,CUSTODY,EMERGENCY,STUDENT_RELATION FROM STUDENTS_JOIN_PEOPLE WHERE ADDRESS_ID='".$_REQUEST['address_id']."'");
			}
		}
		elseif ( $_REQUEST['values']['EXISTING']['person_id'] && $_REQUEST['person_id']=='old')
		{
			$_REQUEST['person_id'] = $_REQUEST['values']['EXISTING']['person_id'];
			if (count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_PEOPLE WHERE PERSON_ID='".$_REQUEST['person_id']."' AND STUDENT_ID='".UserStudentID()."'")))==0)
			{
				DBQuery("INSERT INTO STUDENTS_JOIN_PEOPLE (ID,STUDENT_ID,PERSON_ID,ADDRESS_ID,CUSTODY,EMERGENCY,STUDENT_RELATION) SELECT DISTINCT ON (PERSON_ID) ".db_seq_nextval('STUDENTS_JOIN_PEOPLE_SEQ').",'".UserStudentID()."',PERSON_ID,'".$_REQUEST['address_id']."',CUSTODY,EMERGENCY,STUDENT_RELATION FROM STUDENTS_JOIN_PEOPLE WHERE PERSON_ID='".$_REQUEST['person_id']."'");
				if ( $_REQUEST['address_id']=='0' && count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_ADDRESS WHERE ADDRESS_ID='0' AND STUDENT_ID='".UserStudentID()."'")))==0)
					DBQuery("INSERT INTO STUDENTS_JOIN_ADDRESS (ID,ADDRESS_ID,STUDENT_ID) values (".db_seq_nextval('STUDENTS_JOIN_ADDRESS_SEQ').",'0','".UserStudentID()."')");
			}
		}
	}

	if ( ! empty( $_REQUEST['values']['ADDRESS'] ) )
	{
		// FJ other fields required.
		$required_error = CheckRequiredCustomFields( 'ADDRESS_FIELDS', $_REQUEST['values']['ADDRESS'] );

		// FJ textarea fields MarkDown sanitize.
		$_REQUEST['values']['ADDRESS'] = FilterCustomFieldsMarkdown( 'ADDRESS_FIELDS', 'values', 'ADDRESS' );

		if ( $_REQUEST['address_id']!='new')
		{
			$sql = "UPDATE ADDRESS SET ";

			$fields_RET = DBGet(DBQuery("SELECT ID,TYPE FROM ADDRESS_FIELDS ORDER BY SORT_ORDER"), array(), array('ID'));

			$go = 0;

			foreach ( (array) $_REQUEST['values']['ADDRESS'] as $column => $value)
			{
				if (1)//!empty($value) || $value=='0')
				{
					//FJ check numeric fields
					if ( $fields_RET[str_replace('CUSTOM_','',$column)][1]['TYPE'] == 'numeric' && $value!='' && !is_numeric($value))
					{
						$error[] = _('Please enter valid Numeric data.');
						continue;
					}

					if ( !is_array($value))
						$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
					else
					{
						$sql .= $column."='||";
						foreach ( (array) $value as $val)
						{
							if ( $val)
								$sql .= str_replace('&quot;','"',$val).'||';
						}
						$sql .= "',";
					}
					$go = true;
				}
			}
			$sql = mb_substr($sql,0,-1) . " WHERE ADDRESS_ID='".$_REQUEST['address_id']."'";
			if ( $go)
			{
				DBQuery($sql);

				//hook
				do_action('Students/Student.php|update_student_address');
			}
		}
		else
		{
			$id = DBGet(DBQuery('SELECT '.db_seq_nextval('ADDRESS_SEQ').' as SEQ_ID'));
			$id = $id[1]['SEQ_ID'];

			$sql = "INSERT INTO ADDRESS ";

			$fields = 'ADDRESS_ID,';
			$values = "'" . $id . "',";

			$go = 0;
			foreach ( (array) $_REQUEST['values']['ADDRESS'] as $column => $value)
			{
				if ( !empty($value) || $value=='0')
				{
					$fields .= DBEscapeIdentifier( $column ) . ',';
					$values .= "'" . $value . "',";
					$go = true;
				}
			}
			$sql .= '(' . mb_substr( $fields, 0, -1 ) . ') values(' . mb_substr( $values, 0, -1 ) . ')';
			if ( $go)
			{
				DBQuery($sql);
				DBQuery("INSERT INTO STUDENTS_JOIN_ADDRESS (ID,STUDENT_ID,ADDRESS_ID,RESIDENCE,MAILING,BUS_PICKUP,BUS_DROPOFF) values(".db_seq_nextval('STUDENTS_JOIN_ADDRESS_SEQ').",'".UserStudentID()."','".$id."','".$_REQUEST['values']['STUDENTS_JOIN_ADDRESS']['RESIDENCE']."','".$_REQUEST['values']['STUDENTS_JOIN_ADDRESS']['MAILING']."','".$_REQUEST['values']['STUDENTS_JOIN_ADDRESS']['BUS_PICKUP']."','".$_REQUEST['values']['STUDENTS_JOIN_ADDRESS']['BUS_DROPOFF']."')");
				$_REQUEST['address_id'] = $id;

				//hook
				do_action('Students/Student.php|add_student_address');
			}
		}
	}

	if ( ! empty( $_REQUEST['values']['PEOPLE'] ) )
	{
		// FJ other fields required.
		$required_error = CheckRequiredCustomFields( 'PEOPLE_FIELDS', $_REQUEST['values']['PEOPLE'] );

		// FJ textarea fields MarkDown sanitize.
		$_REQUEST['values']['PEOPLE'] = FilterCustomFieldsMarkdown( 'PEOPLE_FIELDS', 'values', 'PEOPLE' );

		if ( $_REQUEST['person_id']!='new')
		{
			$sql = "UPDATE PEOPLE SET ";

			$fields_RET = DBGet(DBQuery("SELECT ID,TYPE FROM PEOPLE_FIELDS ORDER BY SORT_ORDER"), array(), array('ID'));

			$go = 0;

			foreach ( (array) $_REQUEST['values']['PEOPLE'] as $column => $value)
			{
				if (1)//!empty($value) || $value=='0')
				{
					//FJ check numeric fields
					if ( $fields_RET[str_replace('CUSTOM_','',$column)][1]['TYPE'] == 'numeric' && $value!='' && !is_numeric($value))
					{
						$error[] = _('Please enter valid Numeric data.');
						continue;
					}

					$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
					$go = true;
				}
			}
			$sql = mb_substr($sql,0,-1) . " WHERE PERSON_ID='".$_REQUEST['person_id']."'";
			if ( $go)
				DBQuery($sql);
		}
		else
		{
			$id = DBGet(DBQuery('SELECT '.db_seq_nextval('PEOPLE_SEQ').' as SEQ_ID'));
			$id = $id[1]['SEQ_ID'];

			$sql = "INSERT INTO PEOPLE ";

			$fields = 'PERSON_ID,';
			$values = "'".$id."',";

			$go = 0;
			foreach ( (array) $_REQUEST['values']['PEOPLE'] as $column => $value)
			{
				if ( !empty($value) || $value=='0')
				{
					$fields .= DBEscapeIdentifier( $column ) . ',';
					$values .= "'" . $value . "',";
					$go = true;
				}
			}
			$sql .= '(' . mb_substr( $fields, 0, -1 ) . ') values(' . mb_substr( $values, 0, -1 ) . ')';
			if ( $go)
			{
				DBQuery($sql);
				DBQuery("INSERT INTO STUDENTS_JOIN_PEOPLE (ID,PERSON_ID,STUDENT_ID,ADDRESS_ID,CUSTODY,EMERGENCY) values(".db_seq_nextval('STUDENTS_JOIN_PEOPLE_SEQ').",'".$id."','".UserStudentID()."','".$_REQUEST['address_id']."','".$_REQUEST['values']['STUDENTS_JOIN_PEOPLE']['CUSTODY']."','".$_REQUEST['values']['STUDENTS_JOIN_PEOPLE']['EMERGENCY']."')");
				if ( $_REQUEST['address_id']=='0' && count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_ADDRESS WHERE ADDRESS_ID='0' AND STUDENT_ID='".UserStudentID()."'")))==0)
					DBQuery("INSERT INTO STUDENTS_JOIN_ADDRESS (ID,ADDRESS_ID,STUDENT_ID) values (".db_seq_nextval('STUDENTS_JOIN_ADDRESS_SEQ').",'0','".UserStudentID()."')");
				$_REQUEST['person_id'] = $id;
			}
		}
	}

	if ( ! empty( $_REQUEST['values']['PEOPLE_JOIN_CONTACTS'] ) )
	{
		foreach ( (array) $_REQUEST['values']['PEOPLE_JOIN_CONTACTS'] as $id => $values)
		{
			if ( $id!='new')
			{
				$sql = "UPDATE PEOPLE_JOIN_CONTACTS SET ";

				foreach ( (array) $values as $column => $value)
				{
					$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
				}
				$sql = mb_substr($sql,0,-1) . " WHERE ID='".$id."'";
				DBQuery($sql);
			}
			elseif ( $info_apd
				|| ( $values['TITLE'] && $values['VALUE'] ) )
			{
				$sql = "INSERT INTO PEOPLE_JOIN_CONTACTS ";

				$fields = 'ID,PERSON_ID,';
				$vals = db_seq_nextval('PEOPLE_JOIN_CONTACTS_SEQ').",'".$_REQUEST['person_id']."',";

				$go = 0;
				foreach ( (array) $values as $column => $value)
				{
					if ( !empty($value) || $value=='0')
					{
						$fields .= DBEscapeIdentifier( $column ) . ',';
						$vals .= "'" . $value . "',";
						$go = true;
					}
				}

				$sql .= '(' . mb_substr($fields,0,-1) . ') values(' . mb_substr($vals,0,-1) . ')';

				if ( $go)
					DBQuery($sql);
			}
		}
	}

	if ( $_REQUEST['values']['STUDENTS_JOIN_PEOPLE'] && $_REQUEST['person_id']!='new')
	{
		$sql = "UPDATE STUDENTS_JOIN_PEOPLE SET ";

		foreach ( (array) $_REQUEST['values']['STUDENTS_JOIN_PEOPLE'] as $column => $value)
		{
			$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
		}
		$sql = mb_substr($sql,0,-1) . " WHERE PERSON_ID='".$_REQUEST['person_id']."' AND STUDENT_ID='".UserStudentID()."'";
		DBQuery($sql);
	}

	if ( $_REQUEST['values']['STUDENTS_JOIN_ADDRESS'] && $_REQUEST['address_id']!='new')
	{
		$sql = "UPDATE STUDENTS_JOIN_ADDRESS SET ";

		foreach ( (array) $_REQUEST['values']['STUDENTS_JOIN_ADDRESS'] as $column => $value)
		{
			$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
		}
		$sql = mb_substr($sql,0,-1) . " WHERE ADDRESS_ID='".$_REQUEST['address_id']."' AND STUDENT_ID='".UserStudentID()."'";
		DBQuery($sql);
	}

	if ( $required_error )
	{
		$error[] = _( 'Please fill in the required fields' );
	}

	// Unset modfunc & values & redirect URL.
	RedirectURL( array( 'modfunc', 'values' ) );
}

if ( $_REQUEST['modfunc'] === 'delete_address'
	&& AllowEdit() )
{
	if ( ! empty( $_REQUEST['contact_id'] ) )
	{
		if ( DeletePrompt( _( 'Contact Information' ) ) )
		{
			DBQuery( "DELETE FROM PEOPLE_JOIN_CONTACTS
				WHERE ID='" . $_REQUEST['contact_id'] . "'" );

			// Unset modfunc & contact ID redirect URL.
			RedirectURL( array( 'modfunc', 'contact_id' ) );
		}
	}
	elseif ( ! empty( $_REQUEST['person_id'] ) )
	{
		if ( DeletePrompt( _( 'Contact' ) ) )
		{
			DBQuery("DELETE FROM STUDENTS_JOIN_PEOPLE WHERE PERSON_ID='".$_REQUEST['person_id']."' AND ADDRESS_ID='".$_REQUEST['address_id']."' AND STUDENT_ID='".UserStudentID()."'");
			if (count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_PEOPLE WHERE PERSON_ID='".$_REQUEST['person_id']."'")))==0)
			{
				DBQuery("DELETE FROM PEOPLE WHERE PERSON_ID='".$_REQUEST['person_id']."'");
				DBQuery("DELETE FROM PEOPLE_JOIN_CONTACTS WHERE PERSON_ID='".$_REQUEST['person_id']."'");
			}
			if ( $_REQUEST['address_id']=='0' && count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_PEOPLE WHERE ADDRESS_ID='0' AND STUDENT_ID='".UserStudentID()."'")))==0)
			{
				DBQuery("DELETE FROM STUDENTS_JOIN_ADDRESS WHERE ADDRESS_ID='0' AND STUDENT_ID='".UserStudentID()."'");
			}

			// Unset modfunc & address ID & person ID redirect URL.
			RedirectURL( array( 'modfunc', 'address_id', 'person_id' ) );
		}
	}
	elseif ( ! empty( $_REQUEST['address_id'] ) )
	{
		if ( DeletePrompt( _( 'Address' ) ) )
		{
			DBQuery("UPDATE STUDENTS_JOIN_PEOPLE SET ADDRESS_ID='0' WHERE STUDENT_ID='".UserStudentID()."' AND ADDRESS_ID='".$_REQUEST['address_id']."'");
			if (count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_PEOPLE WHERE STUDENT_ID='".UserStudentID()."' AND ADDRESS_ID='0'"))) && count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_ADDRESS WHERE ADDRESS_ID='0' AND STUDENT_ID='".UserStudentID()."'")))==0)
				DBQuery("UPDATE STUDENTS_JOIN_ADDRESS SET ADDRESS_ID='0',RESIDENCE=NULL,MAILING=NULL,BUS_PICKUP=NULL,BUS_DROPOFF=NULL WHERE STUDENT_ID='".UserStudentID()."' AND ADDRESS_ID='".$_REQUEST['address_id']."'");
			else
				DBQuery("DELETE FROM STUDENTS_JOIN_ADDRESS WHERE STUDENT_ID='".UserStudentID()."' AND ADDRESS_ID='".$_REQUEST['address_id']."'");
			if (count(DBGet(DBQuery("SELECT '' FROM STUDENTS_JOIN_ADDRESS WHERE ADDRESS_ID='".$_REQUEST['address_id']."'")))==0)
				DBQuery("DELETE FROM ADDRESS WHERE ADDRESS_ID='".$_REQUEST['address_id']."'");

			// Unset modfunc & address ID redirect URL.
			RedirectURL( array( 'modfunc', 'address_id' ) );
		}
	}
}

echo ErrorMessage( $error );

if ( ! $_REQUEST['modfunc'] )
{
	$addresses_RET = DBGet( DBQuery( "SELECT a.ADDRESS_ID, sjp.STUDENT_RELATION,a.ADDRESS,
		a.CITY,a.STATE,a.ZIPCODE,a.PHONE,a.MAIL_ADDRESS,a.MAIL_CITY,a.MAIL_STATE,a.MAIL_ZIPCODE,
		sjp.CUSTODY,sja.MAILING,sja.RESIDENCE,sja.BUS_PICKUP,sja.BUS_DROPOFF," .
		db_case( array( 'a.ADDRESS_ID', "'0'", '1', '0' ) ) . "AS SORT_ORDER
	FROM ADDRESS a,STUDENTS_JOIN_ADDRESS sja,STUDENTS_JOIN_PEOPLE sjp
	WHERE a.ADDRESS_ID=sja.ADDRESS_ID
	AND sja.STUDENT_ID='" . UserStudentID() . "'
	AND a.ADDRESS_ID=sjp.ADDRESS_ID
	AND sjp.STUDENT_ID=sja.STUDENT_ID
	UNION
	SELECT a.ADDRESS_ID,'" . DBEscapeString( _( 'No Contact' ) ) . "' AS STUDENT_RELATION,
		a.ADDRESS,a.CITY,a.STATE,a.ZIPCODE,a.PHONE,a.MAIL_ADDRESS,a.MAIL_CITY,a.MAIL_STATE,
		a.MAIL_ZIPCODE,'' AS CUSTODY,sja.MAILING,sja.RESIDENCE,sja.BUS_PICKUP,sja.BUS_DROPOFF," .
		db_case( array( 'a.ADDRESS_ID', "'0'", '1', '0' ) ) . " AS SORT_ORDER
	FROM ADDRESS a,STUDENTS_JOIN_ADDRESS sja
	WHERE a.ADDRESS_ID=sja.ADDRESS_ID
	AND sja.STUDENT_ID='" . UserStudentID() . "'
	AND NOT EXISTS (SELECT ''
		FROM STUDENTS_JOIN_PEOPLE sjp
		WHERE sjp.STUDENT_ID=sja.STUDENT_ID
		AND sjp.ADDRESS_ID=a.ADDRESS_ID)
	ORDER BY SORT_ORDER,RESIDENCE,CUSTODY,STUDENT_RELATION" ), array(), array( 'ADDRESS_ID' ) );

	//echo '<pre>'; var_dump($addresses_RET); echo '</pre>';

	if (count($addresses_RET)==1 && $_REQUEST['address_id']!='new' && $_REQUEST['address_id']!='old' && $_REQUEST['address_id']!='0')
		$_REQUEST['address_id'] = key($addresses_RET).'';

	echo '<table><tr class="address st"><td class="valign-top">';
	echo '<table class="widefat">';
	if (count($addresses_RET) || $_REQUEST['address_id']=='new' || $_REQUEST['address_id']=='0')
	{
		$i = 1;
		if ( $_REQUEST['address_id']=='')
			$_REQUEST['address_id'] = key($addresses_RET).'';

		foreach ( (array) $addresses_RET as $address_id => $addresses)
		{
			echo '<tr>';

			if ( $address_id!='0')
			{

			// Find other students associated with this address.
			$xstudents = DBGet( DBQuery( "SELECT s.STUDENT_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME,
				RESIDENCE,BUS_PICKUP,BUS_DROPOFF,MAILING
				FROM STUDENTS s,STUDENTS_JOIN_ADDRESS sja
				WHERE s.STUDENT_ID=sja.STUDENT_ID
				AND sja.ADDRESS_ID='" . $address_id . "'
				AND sja.STUDENT_ID!='" . UserStudentID() . "'" ) );

			if ( $xstudents )
			{
				$warning = array();

				foreach ( (array) $xstudents as $xstudent )
				{
					$ximages = '';

					if ( $xstudent['RESIDENCE'] === 'Y' )
					{
						$ximages .= ' ' . button( 'house', '', '', 'bigger' );
					}

					if ( $xstudent['BUS_PICKUP'] === 'Y'
						|| $xstudent['BUS_DROPOFF'] === 'Y' )
					{
						$ximages .= ' ' . button( 'bus', '', '', 'bigger' );
					}

					if ( $xstudent['MAILING'] === 'Y' )
					{
						$ximages .= ' ' . button( 'mailbox', '', '', 'bigger' );
					}

					$warning[] = '<b>' . $xstudent['FULL_NAME'] . '</b>' . $ximages;
				}

				echo '<th>' . makeTipMessage(
					implode( '<br />', $warning ),
					_( 'Other students associated with this address' ),
					button( 'warning' )
				) . '</th>';
			}
			else
				echo '<th>&nbsp;</th>';
			}
			else
				echo '<th>&nbsp;</th>';

			$relation_list = '';
			foreach ( (array) $addresses as $address)
//FJ fix Warning: mb_strpos(): Empty delimiter
//					$relation_list .= ($address['STUDENT_RELATION']&&mb_strpos($address['STUDENT_RELATION'].', ',$relation_list)==false?$address['STUDENT_RELATION']:'---').', ';
				$relation_list .= ($address['STUDENT_RELATION']&&(empty($relation_list)?false:mb_strpos($address['STUDENT_RELATION'].', ',$relation_list))==false?$address['STUDENT_RELATION']:'---').', ';
			$address = $addresses[1];
			$relation_list = mb_substr($relation_list,0,-2);

			$images = '';
			if ( $address['RESIDENCE']=='Y')
				$images .= ' '. button('house','','','bigger');

			if ( $address['BUS_PICKUP']=='Y' || $address['BUS_DROPOFF']=='Y')
				$images .= ' '. button('bus','','','bigger');

			if ( $address['MAILING']=='Y')
				$images .= ' '. button('mailbox','','','bigger');

			echo '<th colspan="2">'.$images.'&nbsp;'.$relation_list.'</th>';

			echo '</tr>';

			if ( $address_id==$_REQUEST['address_id'] && $_REQUEST['address_id']!='0' && $_REQUEST['address_id']!='new')
				$this_address = $address;

			$i++;

			$remove_address_button = '';

			if ( $address['ADDRESS_ID'] != '0' && AllowEdit() )
			{
				$remove_address_button = button(
					'remove',
					'',
					'"Modules.php?modname=' . $_REQUEST['modname'] .
					'&category_id=' . $_REQUEST['category_id'] .
					'&address_id=' . $address['ADDRESS_ID'] .
					'&modfunc=delete_address"'
				);
			}

			if ( $_REQUEST['address_id'] == $address['ADDRESS_ID'] )
			{
				echo '<tr class="highlight"><td>' . $remove_address_button . '</td><td>';
			}
			else
			{
				echo '<tr class="highlight-hover"><td>' . $remove_address_button . '</td><td>';
			}

			// Translate "No Address".
			echo '<a href="Modules.php?modname=' . $_REQUEST['modname'] .
				'&category_id=' . $_REQUEST['category_id'] .
				'&address_id=' . $address['ADDRESS_ID'] . '">' .
				( $address['ADDRESS_ID'] == '0' ? _( 'No Address' ) : $address['ADDRESS'] ) .
				'<br />' . ( $address['CITY'] ? $address['CITY'] . ', ' : '' ) .
				$address['STATE'] . ( $address['ZIPCODE'] ? ' ' . $address['ZIPCODE'] : '' ) . '</a>';

			echo '</td>';
			echo '<td'.$style.'><a href="Modules.php?modname='.$_REQUEST['modname'].'&category_id='.$_REQUEST['category_id'].'&address_id='.$address['ADDRESS_ID'].'" class="arrow right"></a></td>';

			echo '</tr>';
		}

		if ( count( $addresses_RET ) )
		{
			echo '<tr><td colspan="3">&nbsp;</td></tr>';
		}
	}
	else
		echo '<tr><td colspan="3">'._('This student doesn\'t have an address.').'</td></tr>';

	if ( AllowEdit() )
	{
		$tr_add_highlight = '<tr class="highlight"><td>' . button( 'add' ) . '</td><td>';

		$tr_add_highlight_hover = '<tr class="highlight-hover"><td>' . button( 'add' ) . '</td><td>';

		// New Address.
		echo $_REQUEST['address_id'] == 'new' ? $tr_add_highlight : $tr_add_highlight_hover;

		$link = 'Modules.php?modname=' . $_REQUEST['modname'] .
			'&category_id=' . $_REQUEST['category_id'] . '&address_id=new';

		echo '<a href="' . $link . '">' . _( 'Add a <b>New</b> Address' ) . ' &nbsp; </a></td>';

		echo '<td><a href="' . $link . '" class="arrow right"></a></td></tr>';


		// Existing Address.
		echo $_REQUEST['address_id'] == 'old' ? $tr_add_highlight : $tr_add_highlight_hover;

		$link = 'Modules.php?modname=' . $_REQUEST['modname'] .
			'&category_id=' . $_REQUEST['category_id'] . '&address_id=old';

		echo '<a href="' . $link . '">' . _( 'Add an <b>Existing</b> Address' ) . ' &nbsp; </a></td>';

		echo '<td><a href="' . $link . '" class="arrow right"></a></td></tr>';


		// New Contact without Address.
		echo $_REQUEST['address_id'] == '0'	&& $_REQUEST['person_id'] == 'new' ?
			$tr_add_highlight :
			$tr_add_highlight_hover;

		$link = 'Modules.php?modname=' . $_REQUEST['modname'] .
			'&category_id=' . $_REQUEST['category_id'] . '&address_id=0&person_id=new';

		echo '<a href="' . $link . '">' .
				_( 'Add a <b>New</b> Contact<br />without an Address' ) . ' &nbsp; </a></td>';

		echo '<td><a href="' . $link . '" class="arrow right"></a></td></tr>';

		// Existing Contact without Address.
		echo $_REQUEST['address_id'] == '0'	&& $_REQUEST['person_id'] == 'old' ?
			$tr_add_highlight :
			$tr_add_highlight_hover;

		$link = 'Modules.php?modname=' . $_REQUEST['modname'] .
			'&category_id=' . $_REQUEST['category_id'] . '&address_id=0&person_id=old';

		echo '<a href="' . $link . '">' .
				_( 'Add an <b>Existing</b> Contact<br />without an Address' ) . ' &nbsp; </a></td>';

		echo '<td><a href="' . $link . '" class="arrow right"></a></td></tr>';
	}

	echo '</table></td>';

	if (isset($_REQUEST['address_id']))
	{
		echo '<td class="valign-top">';
		echo '<input type="hidden" name="address_id" value="'.$_REQUEST['address_id'].'">';

		if ( $_REQUEST['address_id']!='new' && $_REQUEST['address_id']!='old')
		{
			echo '<table class="widefat width-100p"><tr><th colspan="3">';

			echo ($_REQUEST['address_id']=='0'?_('Contacts without an Address'):_('Contacts at this Address')).'</th></tr>';

			$contacts_RET = DBGet( DBQuery( "SELECT p.PERSON_ID,p.FIRST_NAME,p.MIDDLE_NAME,p.LAST_NAME,
				sjp.CUSTODY,sjp.EMERGENCY,sjp.STUDENT_RELATION
				FROM PEOPLE p,STUDENTS_JOIN_PEOPLE sjp
				WHERE p.PERSON_ID=sjp.PERSON_ID
				AND sjp.STUDENT_ID='" . UserStudentID() . "'
				AND sjp.ADDRESS_ID='" . $_REQUEST['address_id'] . "'
				ORDER BY sjp.STUDENT_RELATION" ) );

			$i = 1;
			if (count($contacts_RET))
			{
				foreach ( (array) $contacts_RET as $contact)
				{
					$THIS_RET = $contact;
					if ( $contact['PERSON_ID']==$_REQUEST['person_id'])
						$this_contact = $contact;

					$i++;

					if ( AllowEdit() )
					{
						$remove_button = button(
							'remove',
							'',
							'"Modules.php?modname=' . $_REQUEST['modname'] .
							'&category_id=' . $_REQUEST['category_id'] .
							'&modfunc=delete_address&address_id=' . $_REQUEST['address_id'] .
							'&person_id=' . $contact['PERSON_ID'] . '"'
						);
					}
					else
						$remove_button = '';

					if ( $_REQUEST['person_id']==$contact['PERSON_ID'] )
						echo '<tr class="highlight"><td>'.$remove_button.'</td><td>';
					else
						echo '<tr class="highlight-hover"><td>'.$remove_button.'</td><td>';

					$images = '';

					// Find other students associated with this person.
					$xstudents = DBGet( DBQuery( "SELECT s.STUDENT_ID,
						" . DisplayNameSQL( 's' ) . " AS FULL_NAME,
						STUDENT_RELATION,CUSTODY,EMERGENCY
						FROM STUDENTS s,STUDENTS_JOIN_PEOPLE sjp
						WHERE s.STUDENT_ID=sjp.STUDENT_ID
						AND sjp.PERSON_ID='" . $contact['PERSON_ID'] . "'
						AND sjp.STUDENT_ID!='" . UserStudentID() . "'" ) );

					if ( $xstudents )
					{
						$warning = array();

						foreach ( (array) $xstudents as $xstudent )
						{
							$ximages = '';

							if ( $xstudent['CUSTODY'] === 'Y' )
							{
								$ximages .= ' ' . button( 'gavel', '', '', 'bigger' );
							}

							if ( $xstudent['EMERGENCY'] === 'Y' )
							{
								$ximages .= ' ' . button( 'emergency', '', '', 'bigger' );
							}

							$warning[] = '<b>' . $xstudent['FULL_NAME'] . '</b> ' .
								( $xstudent['STUDENT_RELATION'] ? '(' . $xstudent['STUDENT_RELATION'] . ') ' : '' ) .
								$ximages;
						}

						$images .= ' ' . makeTipMessage(
							implode( '<br />', $warning ),
							_( 'Other students associated with this person' ),
							button( 'warning' )
						);
					}

					if ( $contact['CUSTODY'] === 'Y' )
					{
						$images .= ' ' . button( 'gavel', '', '', 'bigger' );
					}

					if ( $contact['EMERGENCY'] === 'Y' )
					{
						$images .= ' ' . button( 'emergency', '', '', 'bigger' );
					}

					echo '<a href="Modules.php?modname=' . $_REQUEST['modname'] . '&category_id=' . $_REQUEST['category_id'] . '&address_id=' . $_REQUEST['address_id'] . '&person_id=' . $contact['PERSON_ID'] . '">' .
						DisplayName(
							$contact['FIRST_NAME'],
							$contact['LAST_NAME'],
							$contact['MIDDLE_NAME']
						) . '</a>';

					echo $contact['STUDENT_RELATION'] ? ' (' . $contact['STUDENT_RELATION'] . ')' : '';

					echo '<span style="float:right">' . $images . '</span></td>';

					echo '<td><a href="Modules.php?modname='.$_REQUEST['modname'].'&category_id='.$_REQUEST['category_id'].'&address_id='.$_REQUEST['address_id'].'&person_id='.$contact['PERSON_ID'].'" class="arrow right"></a></td>';
					echo '</tr>';
				}
			}
			else
				echo '<tr><td colspan="3">'.($_REQUEST['address_id']=='0'?_('There are no contacts without an address.'):_('There are no contacts at this address.')).'</td></tr>';

			// New Contact
			if ( AllowEdit() )
			{
				$style = '';

				if ( $_REQUEST['person_id']=='new')
					echo '<tr class="highlight"><td>'.button('add').'</td><td>';
				else
					echo '<tr class="highlight-hover"><td>'.button('add').'</td><td>';

				echo '<a href="Modules.php?modname='.$_REQUEST['modname'].'&category_id='.$_REQUEST['category_id'].'&address_id='.$_REQUEST['address_id'].'&person_id=new">'._('Add a <b>New</b> Contact').'</a>';
				echo '</td>';

				echo '<td><a href="Modules.php?modname='.$_REQUEST['modname'].'&category_id='.$_REQUEST['category_id'].'&address_id='.$_REQUEST['address_id'].'&person_id=new" class="arrow right"></a></td>';
				echo '</tr>';

				if ( $_REQUEST['person_id']=='old')
					echo '<tr class="highlight"><td>'.button('add').'</td><td>';
				else
					echo '<tr class="highlight-hover"><td>'.button('add').'</td><td>';

				echo '<a href="Modules.php?modname='.$_REQUEST['modname'].'&category_id='.$_REQUEST['category_id'].'&address_id='.$_REQUEST['address_id'].'&person_id=old">'._('Add an <b>Existing</b> Contact').'</a>';
				echo '</td>';

				echo '<td><a href="Modules.php?modname='.$_REQUEST['modname'].'&category_id='.$_REQUEST['category_id'].'&address_id='.$_REQUEST['address_id'].'&person_id=old" class="arrow right"></a></td>';
				echo '</tr>';
			}

			echo '</table><br />';
		}

		if ( $_REQUEST['address_id'] != '0'
			&& $_REQUEST['address_id'] != 'old' )
		{
			if ( $_REQUEST['address_id'] === 'new' )
			{
				$size = true;
			}
			else
			{
				$size = false;
			}

			// Get City, State & Zip options for auto pull-downs.
			$city_options = _makeAutoSelect(
				'CITY',
				'ADDRESS',
				array(
					array( 'CITY' => $this_address['CITY'] ),
					array( 'CITY' => $this_address['MAIL_CITY'] )
				),
				array()
			);

			$state_options = _makeAutoSelect(
				'STATE',
				'ADDRESS',
				array(
					array( 'STATE' => $this_address['STATE'] ),
					array( 'STATE' => $this_address['MAIL_STATE'] )
				),
				array()
			);

			$zip_options = _makeAutoSelect(
				'ZIPCODE',
				'ADDRESS',
				array(
					array( 'ZIPCODE' => $this_address['ZIPCODE'] ),
					array( 'ZIPCODE' => $this_address['MAIL_ZIPCODE'] )
				),
				array()
			);

			echo '<table class="widefat width-100p"><tr><th colspan="3">' .
				_( 'Address' ) . '</th></tr>';

			echo '<tr><td colspan="3">' .
				TextInput(
					$this_address['ADDRESS'],
					'values[ADDRESS][ADDRESS]',
					_( 'Street' ),
					$size ? 'required maxlength=255 size=20' : 'required maxlength=255' ) .
			'</td></tr>';

			// City, State & Zip auto pull-downs.
			echo '<tr><td>' .
				_makeAutoSelectInputX(
					$this_address['CITY'],
					'CITY',
					'ADDRESS',
					_( 'City' ),
					$city_options
				) . '</td>';

			echo '<td>' .
				_makeAutoSelectInputX(
					$this_address['STATE'],
					'STATE',
					'ADDRESS',
					_( 'State' ),
					$state_options
				) . '</td>';

			echo '<td>' .
				_makeAutoSelectInputX(
					$this_address['ZIPCODE'],
					'ZIPCODE',
					'ADDRESS',
					_( 'Zip' ),
					$zip_options
				) . '</td></tr>';

			echo '<tr><td colspan="3">' .
				TextInput(
					$this_address['PHONE'],
					'values[ADDRESS][PHONE]',
					_('Phone'),
					$size ? 'size=13' : ''
				) . '</td></tr>';

			if ( $_REQUEST['address_id']!='new' && $_REQUEST['address_id']!='0')
			{
				$display_address = urlencode($this_address['ADDRESS'].', '.($this_address['CITY']?' '.$this_address['CITY'].', ':'').$this_address['STATE'].($this_address['ZIPCODE']?' '.$this_address['ZIPCODE']:''));

				$link = 'http://google.com/maps?q='.$display_address;

				echo '<tr><td class="valign-top" colspan="3">' .
					button(
						'compass_rose',
						_( 'Map It' ),
						'# onclick=\'popups.open(
							"' . $link . '",
							"scrollbars=yes,resizable=yes,width=800,height=700"
						); return false;\'',
						'bigger'
					) . '</td></tr>';
			}
			echo '</table>';

			if ( $_REQUEST['address_id']=='new')
			{
				$new = true;
				$this_address['RESIDENCE'] = 'Y';
				$this_address['MAILING'] = 'Y';

				if ( ProgramConfig( 'students', 'STUDENTS_USE_BUS' ) )
				{
					$this_address['BUS_PICKUP'] = 'Y';
					$this_address['BUS_DROPOFF'] = 'Y';
				}
			}

			//FJ css WPadmin
			echo '<br /><table class="widefat"><tr><td>'.CheckboxInput($this_address['RESIDENCE'], 'values[STUDENTS_JOIN_ADDRESS][RESIDENCE]', '', 'CHECKED', $new, button('check'), button('x')).'</td><td>'. button('house','','','bigger') .'</td><td>'._('Residence').'</td></tr>';

			echo '<tr><td>'.CheckboxInput($this_address['BUS_PICKUP'], 'values[STUDENTS_JOIN_ADDRESS][BUS_PICKUP]', '', 'CHECKED', $new, button('check'), button('x')).'</td><td>'. button('bus','','','bigger') .'</td><td>'._('Bus Pickup').'</td></tr>';

			echo '<tr><td>'.CheckboxInput($this_address['BUS_DROPOFF'], 'values[STUDENTS_JOIN_ADDRESS][BUS_DROPOFF]', '', 'CHECKED', $new, button('check'), button('x')).'</td><td>'. button('bus','','','bigger') .'</td><td>'._('Bus Dropoff').'</td></tr>';

			if (Config('STUDENTS_USE_MAILING') || $this_address['MAIL_CITY'] || $this_address['MAIL_STATE'] || $this_address['MAIL_ZIPCODE'])
			{
				echo '<script> function show_mailing(checkbox){if (checkbox.checked==true) document.getElementById(\'mailing_address_div\').style.visibility=\'visible\'; else document.getElementById(\'mailing_address_div\').style.visibility=\'hidden\';}</script>';

				echo '<tr><td>'.CheckboxInput($this_address['MAILING'], 'values[STUDENTS_JOIN_ADDRESS][MAILING]', '', 'CHECKED', $new, button('check'), button('x'), true, 'onclick=show_mailing(this);').'</td><td>'. button('mailbox','','','bigger') .'</td><td>'._('Mailing Address').'</td></tr></table>';

				echo '<div id="mailing_address_div" style="visibility: '.(($this_address['MAILING']||$_REQUEST['address_id']=='new')?'visible':'hidden').';">';

				echo '<br /><table class="widefat"><tr><th colspan="3">'._('Mailing Address').'&nbsp;('._('If different than above').')';

				echo '</th></tr>';

				echo '<tr><td colspan="3">'.TextInput($this_address['MAIL_ADDRESS'],'values[ADDRESS][MAIL_ADDRESS]',_('Street'),! $this_address['MAIL_ADDRESS']?'size=20':'').'</td></tr>';

				echo '<tr><td>'._makeAutoSelectInputX($this_address['MAIL_CITY'],'MAIL_CITY','ADDRESS',_('City'),array()).'</td>';

				echo '<td>'._makeAutoSelectInputX($this_address['MAIL_STATE'],'MAIL_STATE','ADDRESS',_('State'),array()).'</td>';

				echo '<td>'._makeAutoSelectInputX($this_address['MAIL_ZIPCODE'],'MAIL_ZIPCODE','ADDRESS',_('Zip'),array()).'</td></tr>';

				echo '</table>';
				echo '</div>';
			}
			else
				echo '<tr><td>'.CheckboxInput($this_address['MAILING'], 'values[STUDENTS_JOIN_ADDRESS][MAILING]', '', 'CHECKED', $new, button('check'), button('x')).'</td><td>'. button('mailbox','','','bigger') .'</td><td>'._('Mailing Address').'</td></tr></table>';
		}

		if ( $_REQUEST['address_id'] === 'old' )
		{
			$limit_current_school_sql = '';

			if ( Config( 'LIMIT_EXISTING_CONTACTS_ADDRESSES' ) )
			{
				// Limit Existing Addresses to current school.
				$limit_current_school_sql = " AND ADDRESS_ID IN (SELECT sja.ADDRESS_ID
					FROM STUDENTS_JOIN_ADDRESS sja, STUDENT_ENROLLMENT se
					WHERE sja.STUDENT_ID=se.STUDENT_ID
					AND se.SCHOOL_ID='" . UserSchool() . "')";
			}

			$addresses_RET = DBGet( DBQuery( "SELECT ADDRESS_ID,ADDRESS,CITY,STATE,ZIPCODE
				FROM ADDRESS
				WHERE ADDRESS_ID!='0'
				AND ADDRESS_ID NOT IN (SELECT ADDRESS_ID
					FROM STUDENTS_JOIN_ADDRESS
					WHERE STUDENT_ID='" . UserStudentID() . "')" .
				$limit_current_school_sql .
				" ORDER BY ADDRESS,CITY,STATE,ZIPCODE" ) );

			$address_select = array();

			foreach ( (array) $addresses_RET as $address )
			{
				$address_select[ $address['ADDRESS_ID'] ] = $address['ADDRESS'] . ', ' . $address['CITY'] . ', ' . $address['STATE'] . ', ' . $address['ZIPCODE'];
			}

			echo ChosenSelectInput(
				'',
				'values[EXISTING][address_id]',
				_( 'Select Address' ),
				$address_select
			);
		}

		echo '</td>';

		if ( ! empty( $_REQUEST['person_id'] ) )
		{
			echo '<td class="valign-top">';
			echo '<input type="hidden" name="person_id" value="'.$_REQUEST['person_id'].'" />';

			if ( $_REQUEST['person_id']!='old')
			{
				$relation_options = _makeAutoSelect(
					'STUDENT_RELATION',
					'STUDENTS_JOIN_PEOPLE',
					$this_contact['STUDENT_RELATION'],
					array()
				);

				//FJ css WPadmin
				echo '<table class="widefat"><tr><th colspan="3">'._('Contact Information').'</th></tr>';

				if ( $_REQUEST['person_id']!='new')
				{
					echo '<tr><td id="person_'.$this_contact['PERSON_ID'].'" colspan="2">';

					$id = 'person_' . $info['ID'];

					$person_html = _makePeopleInput(
						$this_contact['FIRST_NAME'],
						'FIRST_NAME',
						_( 'First Name' )
					) . '<br />' .
					_makePeopleInput(
						$this_contact['MIDDLE_NAME'],
						'MIDDLE_NAME',
						_( 'Middle Name' )
					) . '<br />' .
					_makePeopleInput(
						$this_contact['LAST_NAME'],
						'LAST_NAME',
						_( 'Last Name' )
					);

					echo InputDivOnclick(
						$id,
						$person_html,
						$this_contact['FIRST_NAME'] . ' ' . $this_contact['MIDDLE_NAME'] . ' ' .
						$this_contact['LAST_NAME'],
						FormatInputTitle( _( 'Name' ), $id )
					);

					echo '<tr><td colspan="2">'._makeAutoSelectInputX($this_contact['STUDENT_RELATION'],'STUDENT_RELATION','STUDENTS_JOIN_PEOPLE',_('Relation'),$relation_options).'</td>';

					// Custody.
					echo '<tr><td>' . CheckboxInput(
						$this_contact['CUSTODY'],
						'values[STUDENTS_JOIN_PEOPLE][CUSTODY]',
						'',
						'CHECKED',
						$new,
						button( 'check' ),
						button('x')
					) . '</td><td>' .
					button( 'gavel', '', '', 'bigger' ) . ' ' . _( 'Custody' ) .
					'</td></tr>';

					// Emergency.
					echo '<tr><td>' . CheckboxInput(
						$this_contact['EMERGENCY'],
						'values[STUDENTS_JOIN_PEOPLE][EMERGENCY]',
						'',
						'CHECKED',
						$new,
						button( 'check' ),
						button( 'x' )
					) . '</td><td>' .
					button( 'emergency', '', '', 'bigger' ) . ' ' . _( 'Emergency' ) .
					'</td></tr>';

					$info_RET = DBGet( DBQuery( "SELECT ID,TITLE,VALUE
						FROM PEOPLE_JOIN_CONTACTS
						WHERE PERSON_ID='" . $_REQUEST['person_id'] . "'" ) );

					$info_options = _makeAutoSelect(
						'TITLE',
						'PEOPLE_JOIN_CONTACTS',
						$info_RET,
						array()
					);

					foreach ( (array) $info_RET as $info )
					{
						echo '<tr>';

						if ( AllowEdit() )
						{
							echo '<td>' . button(
								'remove',
								'',
								'"Modules.php?modname=' . $_REQUEST['modname'] .
								'&category_id=' . $_REQUEST['category_id'] .
								'&modfunc=delete_address&address_id=' . $_REQUEST['address_id'] .
								'&person_id=' . $_REQUEST['person_id'] .
								'&contact_id=' . $info['ID'] . '"'
							) . '</td><td>';
						}
						else
							echo '<td></td><td>';

						if ( ! AllowEdit() )
						{
							echo TextInput(
								$info['VALUE'],
								'values[PEOPLE_JOIN_CONTACTS][' . $info['ID'] . '][VALUE]',
								$info['TITLE']
							);
						}
						else
						{
							$id = 'info_' . $info['ID'];

							$info_html = TextInput(
								$info['VALUE'],
								'values[PEOPLE_JOIN_CONTACTS][' . $info['ID'] . '][VALUE]',
								'',
								'',
								false
							) . '<br />';

							if ( $info_apd )
							{
								$info_html .= _makeAutoSelectInputX(
									$info['TITLE'],
									'TITLE',
									'PEOPLE_JOIN_CONTACTS',
									'',
									$info_options,
									$info['ID'],
									false
								);
							}
							else
							{
								$info_html .= TextInput(
									$info['TITLE'],
									'values[PEOPLE_JOIN_CONTACTS][' . $info['ID'] . '][TITLE]',
									'',
									'',
									false
								);
							}

							echo InputDivOnclick(
								$id,
								$info_html,
								$info['VALUE'],
								FormatInputTitle(
									$info['TITLE'] === '---' ?
										'<span class="legend-red">-' . _( 'Edit' ) . '-</span>' :
										$info['TITLE'],
									$id
								)
							);
						}

						echo '</td></tr>';
					}

					if ( AllowEdit()
						&& ProgramConfig( 'students', 'STUDENTS_USE_CONTACT' ) )
					{
						echo '<tr><td>' . button( 'add' ) . '</td><td>' .
						TextInput(
							'',
							'values[PEOPLE_JOIN_CONTACTS][new][VALUE]',
							_('Value'),
							'maxlength=100'
						) . '<br />' . ( $info_apd && count( $info_options ) > 1 ?
							SelectInput(
								'',
								'values[PEOPLE_JOIN_CONTACTS][new][TITLE]',
								_( 'Description' ),
								$info_options,
								'N/A'
							) :
							TextInput(
								'',
								'values[PEOPLE_JOIN_CONTACTS][new][TITLE]',
								_( 'Description' ),
								'maxlength=100'
							) ) . '</td></tr>';
					}
				}
				else
				{
					echo '<tr>
					<td colspan="2">' .
					_makePeopleInput(
						'',
						'FIRST_NAME',
						_( 'First Name' )
					) .
					'<br />' .
					_makePeopleInput(
						'',
						'MIDDLE_NAME',
						_( 'Middle Name' )
					) . '<br />' .
					_makePeopleInput(
						'',
						'LAST_NAME',
						_( 'Last Name' )
					) . '</tr>';

					echo '<tr><td colspan="3">' .
					_makeAutoSelectInputX(
						'',
						'STUDENT_RELATION',
						'STUDENTS_JOIN_PEOPLE',
						_( 'Relation'),
						$relation_options
					) . '</td></tr>';

					echo '<tr><td>'. button('gavel', '', '', 'bigger').' ';

					echo CheckboxInput('', 'values[STUDENTS_JOIN_PEOPLE][CUSTODY]', _('Custody'), '',true).'</td>';

					echo '<td colspan="2">'. button('emergency', '', '', 'bigger') .' ';

					echo CheckboxInput('', 'values[STUDENTS_JOIN_PEOPLE][EMERGENCY]', _('Emergency'), '',true).'</td></tr>';

				}
				echo '</table>';

				$categories_RET = DBGet(DBQuery("SELECT c.ID AS CATEGORY_ID,c.TITLE AS CATEGORY_TITLE,c.CUSTODY,c.EMERGENCY,f.ID,f.TITLE,f.TYPE,f.SELECT_OPTIONS,f.DEFAULT_SELECTION,f.REQUIRED FROM PEOPLE_FIELD_CATEGORIES c,PEOPLE_FIELDS f WHERE f.CATEGORY_ID=c.ID ORDER BY c.SORT_ORDER,c.TITLE,f.SORT_ORDER,f.TITLE"),array(),array('CATEGORY_ID'));
				if ( $categories_RET)
				{
					echo '<td class="valign-top">';
					if ( $_REQUEST['person_id'] !== 'new' )
					{
						$value = DBGet(DBQuery("SELECT * FROM PEOPLE WHERE PERSON_ID='".$_REQUEST['person_id']."'"));
						$value = $value[1];
					}
					else
						$value = array();

					$request = 'values[PEOPLE]';
					echo '<table>';
					foreach ( (array) $categories_RET as $fields_RET)
					{
						if ( ! $fields_RET['CUSTODY']&&! $fields_RET['EMERGENCY'] || $fields_RET['CUSTODY']=='Y'&&$this_contact['CUSTODY']=='Y' || $fields_RET['EMERGENCY']=='Y'&&$this_contact['EMERGENCY']=='Y')
						{
							echo '<tr><td>';
							echo '<fieldset><legend>'.ParseMLField($fields_RET[1]['CATEGORY_TITLE']).'</legend>';
							require_once 'modules/Students/includes/Other_Fields.inc.php';
							echo '</fieldset>';
							echo '</td></tr>';
						}
					}
					echo '</table>';
					echo '</td>';
				}
			}
			elseif ( $_REQUEST['person_id'] === 'old' )
			{
				$limit_current_school_sql = '';

				if ( Config( 'LIMIT_EXISTING_CONTACTS_ADDRESSES' ) )
				{
					// Limit Existing Contacts to current school.
					$limit_current_school_sql = " AND p.PERSON_ID IN (SELECT sjp.PERSON_ID
						FROM STUDENTS_JOIN_PEOPLE sjp, STUDENT_ENROLLMENT se
						WHERE sjp.STUDENT_ID=se.STUDENT_ID
						AND se.SCHOOL_ID='" . UserSchool() . "')";
				}

				$people_RET = DBGet( DBQuery( "SELECT DISTINCT p.PERSON_ID,
					p.FIRST_NAME,p.LAST_NAME,p.MIDDLE_NAME
					FROM PEOPLE p,STUDENTS_JOIN_PEOPLE sjp
					WHERE sjp.PERSON_ID=p.PERSON_ID
					AND sjp.ADDRESS_ID" . ( $_REQUEST['address_id'] != '0' ? '!=' : '=' ) . "'0'
					AND p.PERSON_ID NOT IN (SELECT PERSON_ID
						FROM STUDENTS_JOIN_PEOPLE
						WHERE STUDENT_ID='" . UserStudentID() . "')" .
					$limit_current_school_sql .
					" ORDER BY LAST_NAME,FIRST_NAME" ) );

				$people_select = array();

				foreach ( (array) $people_RET as $people )
				{
					$people_select[ $people['PERSON_ID'] ] = DisplayName(
						$people['FIRST_NAME'],
						$people['LAST_NAME'],
						$people['MIDDLE_NAME']
					);
				}

				echo ChosenSelectInput(
					'',
					'values[EXISTING][person_id]',
					_( 'Select Person' ),
					$people_select
				);
			}
		}
		elseif ( $_REQUEST['address_id']!='0' && $_REQUEST['address_id']!='old')
		{
			$categories_RET = DBGet(DBQuery("SELECT c.ID AS CATEGORY_ID,c.TITLE AS CATEGORY_TITLE,c.RESIDENCE,c.MAILING,c.BUS,f.ID,f.TITLE,f.TYPE,f.SELECT_OPTIONS,f.DEFAULT_SELECTION,f.REQUIRED FROM ADDRESS_FIELD_CATEGORIES c,ADDRESS_FIELDS f WHERE f.CATEGORY_ID=c.ID ORDER BY c.SORT_ORDER,c.TITLE,f.SORT_ORDER,f.TITLE"),array(),array('CATEGORY_ID'));

			if ( $categories_RET )
			{
				echo '<td class="valign-top">';

				if ( $_REQUEST['address_id']!='new' )
				{
					$value = DBGet(DBQuery("SELECT * FROM ADDRESS WHERE ADDRESS_ID='".$_REQUEST['address_id']."'"));
					$value = $value[1];
				}
				else
					$value = array();

				$request = 'values[ADDRESS]';
				echo '<table>';
				foreach ( (array) $categories_RET as $fields_RET)
				{
					if ( ! $fields_RET[1]['RESIDENCE']&&! $fields_RET[1]['MAILING']&&! $fields_RET[1]['BUS'] || $fields_RET[1]['RESIDENCE']=='Y'&&$this_address['RESIDENCE']=='Y' || $fields_RET[1]['MAILING']=='Y'&&$this_address['MAILING']=='Y' || $fields_RET[1]['BUS']=='Y'&&($this_address['BUS_PICKUP']=='Y'||$this_address['BUS_DROPOFF']=='Y'))
					{
						echo '<tr><td>';
						echo '<fieldset><legend>'.ParseMLField($fields_RET[1]['CATEGORY_TITLE']).'</legend>';
						require_once 'modules/Students/includes/Other_Fields.inc.php';
						echo '</fieldset>';
						echo '</td></tr>';
					}
				}
				echo '</table>';
			}
		}
		echo '</td>';
	}
	/*else
		echo '<td></td><td></td>';*/
	echo '</tr>';
	echo '</table>';
	$separator = '<hr />';

	require_once 'modules/Students/includes/Other_Info.inc.php';
}

function _makePeopleInput($value,$column,$title='')
{
	if ( $column === 'LAST_NAME'
		|| $column === 'FIRST_NAME' )
	{
		$options = 'required';
	}

	if ( $column === 'LAST_NAME'
		|| $column === 'FIRST_NAME'
		|| $column === 'MIDDLE_NAME' )
	{
		$options .= ' maxlength=50';
	}

	if ( $_REQUEST['person_id']=='new')
	{
		$div = false;
	}
	else
		$div = true;

	if ( $column=='STUDENT_RELATION')
	{
		$table = 'STUDENTS_JOIN_PEOPLE';
	}
	else
		$table = 'PEOPLE';

	return TextInput(
		$value,
		'values[' . $table . '][' . $column . ']',
		$title,
		$options,
		false
	);
}

function _makeAutoSelect( $column, $table, $values = '', $options = array() )
{
	$fatal_error = array();

	// Tables white list, prevent hacking.
	$tables_white_list = array(
		'ADDRESS',
		'STUDENTS_JOIN_PEOPLE',
	);

	if ( ! in_array( $table, $tables_white_list ) )
	{
		// Do NOT translate this error, should never be displayed.
		$fatal_error[] = sprintf( '_makeAutoSelect error: unknown table %s', $table );
	}

	// Column sanitize, prevent hacking.
	if ( ! preg_match( "/^[a-zA-Z0-9_]*$/", $column ) )
	{
		// Do NOT translate this error, should never be displayed.
		$fatal_error[] = sprintf( '_makeAutoSelect error: illegal column %s', $column );
	}

	if ( $fatal_error )
	{
		return ErrorMessage( $error, 'fatal' );
	}

	// Add the 'new' option, is also the separator.
	$options['---'] = '-' . _( 'Edit' ) . '-';

	if ( AllowEdit() ) // We don't really need the select list if we can't edit anyway.
	{
		$limit_current_school_sql = '';

		if ( $table === 'ADDRESS'
			&& Config( 'LIMIT_EXISTING_CONTACTS_ADDRESSES' ) )
		{
			// Limit Existing Addresses to current school.
			$limit_current_school_sql = " WHERE ADDRESS_ID IN (SELECT sja.ADDRESS_ID
				FROM STUDENTS_JOIN_ADDRESS sja, STUDENT_ENROLLMENT se
				WHERE sja.STUDENT_ID=se.STUDENT_ID
				AND se.SCHOOL_ID='" . UserSchool() . "')";
		}

		// Add values already in table
		$options_RET = DBGet( DBQuery( "SELECT DISTINCT " . DBEscapeIdentifier( $column ) .
			",upper(" . DBEscapeIdentifier( $column ) . ") AS SORT_KEY
			FROM " . DBEscapeIdentifier( $table ) .
			$limit_current_school_sql .
			" ORDER BY SORT_KEY" ) );

		foreach ( (array) $options_RET as $option )
		{
			if ( $option[ $column ] != ''
				&& ! isset( $options[ $option[ $column ] ] ) )
			{
				$options[ $option[ $column ] ] = array( $option[ $column ], $option[ $column ] );
			}
		}
	}

	// Make sure values are in the list.
	if ( isset( $values )
		&& is_array( $values ) )
	{
		foreach ( (array) $values as $value )
		{
			if ( $value[ $column ] != ''
				&& ! isset( $options[ $value[ $column ] ] ) )
			{
				$options[ $value[ $column ] ] = array( $value[ $column ], $value[ $column ] );
			}
		}
	}
	elseif ( $values != ''
		&& ! isset( $options[ $values ] ) )
	{
		$options[ $values ] = array( $values, $values );
	}

	return $options;
}


/**
 * Make Auto Select input
 * aka auto pull-down:
 * When the -Edit- option is selected,
 * the select field is automatically transformed into a text field.
 * If the value is "---" or if there are less than 2 values saved yet,
 * a text field is directly shown.
 *
 * Local function.
 *
 * @uses SelectInput()
 * @uses TextInput()
 *
 * @param  string  $value  Input value.
 * @param  string  $column Column.
 * @param  string  $table  DB table (ADDRESS or PEOPLE_JOIN_CONTACTS or STUDENTS_JOIN_PEOPLE).
 * @param  string  $title  Input title.
 * @param  array   $select Select options.
 * @param  string  $id     ID. Optional. Defaults to ''.
 * @param  boolean $div    Wrap in div onclick? Optional. Defaults to false.
 *
 * @return string          Select or Text Input.
 */
function _makeAutoSelectInputX( $value, $column, $table, $title, $select, $id = '', $div = true )
{
	static $js_included = false;

	if ( $column === 'CITY'
		|| $column === 'MAIL_CITY' )
	{
		$options = 'maxlength=60';
	}

	if ( $column === 'STATE'
		|| $column === 'MAIL_STATE' )
	{
		$options = 'size=3 maxlength=10';
	}
	elseif ( $column === 'ZIPCODE'
		|| $column === 'MAIL_ZIPCODE' )
	{
		$options = 'size=5 maxlength=10';
	}
	else
		$options = 'maxlength=100';

	$input_name = 'values[' . $table . ']' . ( $id ? '[' . $id . ']' : '' ) . '[' . $column . ']';

	if ( $value !== '---'
		&& count( $select ) > 1 )
	{
		// When -Edit- option selected, change the Address auto pull-downs to text fields.
		$return = '';

		if ( AllowEdit()
			&& ! isset( $_REQUEST['_ROSARIO_PDF'] )
			&& ! $js_included )
		{
			$js_included = true;

			ob_start(); ?>
			<script>
			function maybeEditTextInput(el) {

				// -Edit- option's value is ---.
				if ( el.options[el.selectedIndex].value === '---' ) {

					var $el = $( el );

					// Remove parent <div> if any
					if ( $el.parent('div').length ) {
						$el.unwrap();
					}
					// Remove the select input.
					$el.remove();

					// Show & enable the text input of the same name.
					$( '[name="' + el.name + '"]' ).prop('disabled', false).show().focus();
				}
			}
			</script>
			<?php $return = ob_get_clean();
		}

		if ( AllowEdit()
			&& ! isset( $_REQUEST['_ROSARIO_PDF'] ) )
		{
			// Add hidden & disabled Text input in case user chooses -Edit-.
			$return .= TextInput(
				'',
				$input_name,
				'',
				$options . ' disabled style="display:none;"',
				false
			);
		}

		$return .= SelectInput(
			$value,
			$input_name,
			$title,
			$select,
			'N/A',
			'onchange="maybeEditTextInput(this);"',
			$div
		);

		return $return;
	}
	else
	{
		// FJ new option.
		return TextInput(
			$value === '---' ? array( '---', '<span style="color:red">-' . _( 'Edit' ) . '-</span>' ) : $value,
			$input_name,
			$title,
			$options,
			$div
		);
	}
}
