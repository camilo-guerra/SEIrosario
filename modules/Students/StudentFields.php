<?php
/**
 * Student Fields
 *
 * @package RosarioSIS
 * @subpackage modules
 */

require_once 'ProgramFunctions/Fields.fnc.php';

DrawHeader( ProgramTitle() );

//$_ROSARIO['allow_edit'] = true;

if ( isset( $_POST['tables'] )
	&& is_array( $_POST['tables'] )
	&& AllowEdit() )
{
	$table = isset( $_REQUEST['table'] ) ? $_REQUEST['table'] : null;

	foreach ( (array) $_REQUEST['tables'] as $id => $columns )
	{
		// FJ fix SQL bug invalid sort order.
		if ( ( empty( $columns['SORT_ORDER'] )
				|| is_numeric( $columns['SORT_ORDER'] ) )
			&& ( empty( $columns['COLUMNS'] )
				|| is_numeric( $columns['COLUMNS'] ) ) )
		{
			// FJ added SQL constraint TITLE is not null.
			if ( ! isset( $columns['TITLE'] )
				|| ! empty( $columns['TITLE'] ) )
			{
				// FJ Fix PHP fatal error: check Include file exists.
				if ( isset( $columns['INCLUDE'] )
					&& $columns['INCLUDE'] )
				{
					$include_file_path = 'modules/' . $columns['INCLUDE'] . '.inc.php';

					if ( ! file_exists( $include_file_path ) )
					{
						// File does not exist: reset + error.
						unset( $columns['INCLUDE'] );

						$error[] = sprintf(
							_( 'The include file was not found: "%s"' ),
							$include_file_path
						);
					}
				}

				// Update Field / Category.
				if ( $id !== 'new' )
				{
					if ( isset( $columns['CATEGORY_ID'] )
						&& $columns['CATEGORY_ID'] != $_REQUEST['category_id'] )
					{
						$_REQUEST['category_id'] = $columns['CATEGORY_ID'];
					}

					$sql = 'UPDATE ' . $table . ' SET ';

					foreach ( (array) $columns as $column => $value )
					{
						$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
					}

					$sql = mb_substr( $sql, 0, -1 ) . " WHERE ID='" . $id . "'";

					$go = true;
				}
				// New Field / Category.
				else
				{
					$sql = 'INSERT INTO ' . $table . ' ';

					// New Field.
					if ( $table === 'CUSTOM_FIELDS' )
					{
						if ( isset( $columns['CATEGORY_ID'] ) )
						{
							$_REQUEST['category_id'] = $columns['CATEGORY_ID'];

							unset( $columns['CATEGORY_ID'] );
						}

						$_REQUEST['id'] = AddDBField( 'STUDENTS', 'custom_seq', $columns['TYPE'] );

						$fields = 'ID,CATEGORY_ID,';

						$values = $_REQUEST['id'] . ",'" . $_REQUEST['category_id'] . "',";
					}
					// New Category.
					elseif ( $table === 'STUDENT_FIELD_CATEGORIES' )
					{
						$id = DBGet( DBQuery( 'SELECT ' . db_seq_nextval( 'STUDENT_FIELD_CATEGORIES_SEQ' ) . ' AS ID ' ) );

						$id = $id[1]['ID'];

						$fields = "ID,";

						$values = $id . ",";

						$_REQUEST['category_id'] = $id;

						// Add to profile or permissions of user creating it.
						if ( User( 'PROFILE_ID' ) )
						{
							DBQuery( "INSERT INTO PROFILE_EXCEPTIONS (PROFILE_ID,MODNAME,CAN_USE,CAN_EDIT)
								values('" . User( 'PROFILE_ID' ) . "','Students/Student.php&category_id=" . $id . "','Y','Y')" );
						}
						else
						{
							DBQuery( "INSERT INTO STAFF_EXCEPTIONS (USER_ID,MODNAME,CAN_USE,CAN_EDIT)
								values('" . User( 'STAFF_ID' ) . "','Students/Student.php&category_id=" . $id . "','Y','Y')" );
						}
					}

					$go = false;

					foreach ( (array) $columns as $column => $value )
					{
						if ( ! empty( $value )
							|| $value == '0' )
						{
							$fields .= $column . ',';

							$values .= "'" . $value . "',";

							$go = true;
						}
					}
					$sql .= '(' . mb_substr( $fields, 0, -1 ) . ') values(' . mb_substr( $values, 0, -1 ) . ')';
				}

				if ( $go )
				{
					DBQuery( $sql );
				}
			}
			else
				$error[] = _( 'Please fill in the required fields' );
		}
		else
			$error[] = _( 'Please enter valid Numeric data.' );
	}

	// Unset tables & redirect URL.
	RedirectURL( array( 'tables' ) );
}

// Delete Field / Category.
if ( $_REQUEST['modfunc'] === 'delete'
	&& AllowEdit() )
{
	if ( isset( $_REQUEST['id'] )
		&& intval( $_REQUEST['id'] ) > 0 )
	{
		if ( DeletePrompt( _( 'Student Field' ) ) )
		{
			DeleteDBField( 'STUDENTS', $_REQUEST['id'] );

			// Unset modfunc & ID & redirect URL.
			RedirectURL( array( 'modfunc', 'id' ) );
		}
	}
	elseif ( isset( $_REQUEST['category_id'] )
		&& intval( $_REQUEST['category_id'] ) > 0 )
	{
		if ( DeletePrompt( _( 'Student Field Category' ) . ' ' .
				_( 'and all fields in the category' ) ) )
		{
			DeleteDBFieldCategory( 'STUDENTS', $_REQUEST['category_id'] );

			// Remove from profiles and permissions.
			DBQuery( "DELETE FROM PROFILE_EXCEPTIONS
				WHERE MODNAME='Students/Student.php&category_id=" . $_REQUEST['category_id'] . "'" );

			DBQuery( "DELETE FROM STAFF_EXCEPTIONS
				WHERE MODNAME='Students/Student.php&category_id=" . $_REQUEST['category_id'] . "'" );

			// Unset modfunc & category ID redirect URL.
			RedirectURL( array( 'modfunc', 'category_id' ) );
		}
	}
}

if ( ! $_REQUEST['modfunc'] )
{
	echo ErrorMessage( $error );

	// ADDING & EDITING FORM.
	if ( $_REQUEST['id']
		&& $_REQUEST['id'] !== 'new' )
	{
		$RET = DBGet( DBQuery( "SELECT ID,CATEGORY_ID,TITLE,TYPE,SELECT_OPTIONS,
			DEFAULT_SELECTION,SORT_ORDER,REQUIRED,
			(SELECT TITLE
				FROM STUDENT_FIELD_CATEGORIES
				WHERE ID=CATEGORY_ID) AS CATEGORY_TITLE
			FROM CUSTOM_FIELDS
			WHERE ID='" . $_REQUEST['id'] . "'" ) );

		$RET = $RET[1];

		$title = ParseMLField( $RET['CATEGORY_TITLE'] ) . ' - ' . ParseMLField( $RET['TITLE'] );
	}
	elseif ( $_REQUEST['category_id']
		&& $_REQUEST['category_id'] !== 'new'
		&& $_REQUEST['id'] !== 'new' )
	{
		$RET = DBGet( DBQuery( "SELECT ID AS CATEGORY_ID,TITLE,SORT_ORDER,INCLUDE,COLUMNS
			FROM STUDENT_FIELD_CATEGORIES
			WHERE ID='" . $_REQUEST['category_id'] . "'" ) );

		$RET = $RET[1];

		$title = ParseMLField( $RET['TITLE'] );
	}
	elseif ( $_REQUEST['id'] === 'new' )
	{
		$title = _( 'New Student Field' );

		$RET['ID'] = 'new';

		$RET['CATEGORY_ID'] = isset( $_REQUEST['category_id'] ) ? $_REQUEST['category_id'] : null;
	}
	elseif ( $_REQUEST['category_id'] === 'new' )
	{
		$title = _( 'New Student Field Category' );

		$RET['CATEGORY_ID'] = 'new';
	}

	if ( $_REQUEST['category_id']
		&& ! $_REQUEST['id'] )
	{
		$extra_fields = array( TextInput(
			$RET['COLUMNS'],
			'tables[' . $_REQUEST['category_id'] . '][COLUMNS]',
			_( 'Display Columns' ),
			'size=5'
		) );

		if ( $_REQUEST['category_id'] > 4
			|| $_REQUEST['category_id'] === 'new' )
		{
			// TODO: check if INCLUDE file (+ ".inc.php") exsits.
			$extra_fields[] = TextInput(
				$RET['INCLUDE'],
				'tables[' . $_REQUEST['category_id'] . '][INCLUDE]',
				_( 'Include (should be left blank for most categories)' )
			);
		}
	}

	echo GetFieldsForm(
		'STUDENT',
		$title,
		$RET,
		isset( $extra_fields ) ? $extra_fields : array()
	);

	// CATEGORIES.
	$categories_RET = DBGet( DBQuery( "SELECT ID,TITLE,SORT_ORDER
		FROM STUDENT_FIELD_CATEGORIES
		ORDER BY SORT_ORDER,TITLE" ) );

	// DISPLAY THE MENU.
	echo '<div class="st">';

	FieldsMenuOutput( $categories_RET, $_REQUEST['category_id'] );

	echo '</div>';

	// FIELDS.
	if ( $_REQUEST['category_id']
		&& $_REQUEST['category_id'] !=='new'
		&& $categories_RET )
	{
		$fields_RET = DBGet( DBQuery( "SELECT ID,TITLE,TYPE,SORT_ORDER
			FROM CUSTOM_FIELDS
			WHERE CATEGORY_ID='" . $_REQUEST['category_id'] . "'
			ORDER BY SORT_ORDER,TITLE" ), array( 'TYPE' => 'MakeFieldType' ) );

		echo '<div class="st">';

		FieldsMenuOutput( $fields_RET, $_REQUEST['id'], $_REQUEST['category_id'] );

		echo '</div>';
	}
}
