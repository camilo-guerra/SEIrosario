<?php
/**
 * Address Fields
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
					if ( $table === 'ADDRESS_FIELDS' )
					{
						if ( isset( $columns['CATEGORY_ID'] ) )
						{
							$_REQUEST['category_id'] = $columns['CATEGORY_ID'];

							unset( $columns['CATEGORY_ID'] );
						}

						$_REQUEST['id'] = AddDBField( 'ADDRESS', 'address_fields_seq', $columns['TYPE'] );

						$fields = 'ID,CATEGORY_ID,';

						$values = $_REQUEST['id'] . ",'" . $_REQUEST['category_id'] . "',";
					}
					// New Category.
					elseif ( $table === 'ADDRESS_FIELD_CATEGORIES' )
					{
						$id = DBGet( DBQuery( 'SELECT ' . db_seq_nextval( 'ADDRESS_FIELD_CATEGORIES_SEQ' ) . ' AS ID ' ) );

						$id = $id[1]['ID'];

						$fields = "ID,";

						$values = $id . ",";

						$_REQUEST['category_id'] = $id;
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
		if ( DeletePrompt( _( 'Address Field' ) ) )
		{
			DeleteDBField( 'ADDRESS', $_REQUEST['id'] );

			// Unset modfunc & ID & redirect URL.
			RedirectURL( array( 'modfunc', 'id' ) );
		}
	}
	elseif ( isset( $_REQUEST['category_id'] )
		&& intval( $_REQUEST['category_id'] ) > 0 )
	{
		if ( DeletePrompt( _( 'Address Field Category' ) . ' ' .
				_( 'and all fields in the category' ) ) )
		{
			DeleteDBFieldCategory( 'ADDRESS', $_REQUEST['category_id'] );

			// Unset modfunc & category ID & redirect URL.
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
				FROM ADDRESS_FIELD_CATEGORIES
				WHERE ID=CATEGORY_ID) AS CATEGORY_TITLE
			FROM ADDRESS_FIELDS
			WHERE ID='" . $_REQUEST['id'] . "'" ) );

		$RET = $RET[1];

		$title = ParseMLField( $RET['CATEGORY_TITLE'] ) . ' - ' . ParseMLField( $RET['TITLE'] );
	}
	elseif ( $_REQUEST['category_id']
		&& $_REQUEST['category_id'] !== 'new'
		&& $_REQUEST['id'] !== 'new' )
	{
		$RET = DBGet( DBQuery( "SELECT ID AS CATEGORY_ID,TITLE,RESIDENCE,MAILING,BUS,SORT_ORDER
			FROM ADDRESS_FIELD_CATEGORIES
			WHERE ID='" . $_REQUEST['category_id'] . "'" ) );

		$RET = $RET[1];

		$title = ParseMLField( $RET['TITLE'] );
	}
	elseif ( $_REQUEST['id'] === 'new' )
	{
		$title = _( 'New Address Field' );

		$RET['ID'] = 'new';

		$RET['CATEGORY_ID'] = isset( $_REQUEST['category_id'] ) ? $_REQUEST['category_id'] : null;
	}
	elseif ( $_REQUEST['category_id'] === 'new' )
	{
		$title = _( 'New Address Field Category' );

		$RET['CATEGORY_ID'] = 'new';
	}

	if ( $_REQUEST['category_id']
		&& ! $_REQUEST['id'] )
	{
		$extra_fields = array(
			'<table class="width-100p cellspacing-0"><tr class="st"><td>' .
			CheckboxInput(
				$RET['RESIDENCE'],
				'tables[' . $_REQUEST['category_id'] . '][RESIDENCE]',
				_( 'Residence' ),
				'',
				$_REQUEST['category_id'] === 'new',
				button( 'check' ),
				button( 'x' )
			) . '</td><td>' .
			CheckboxInput(
				$RET['MAILING'],
				'tables[' . $_REQUEST['category_id'] . '][MAILING]',
				_( 'Mailing' ),
				'',
				$_REQUEST['category_id'] === 'new',
				button( 'check' ),
				button( 'x' )
			) . '</td><td>' .
			CheckboxInput(
				$RET['BUS'],
				'tables[' . $_REQUEST['category_id'] . '][BUS]',
				_( 'Bus' ),
				'',
				$_REQUEST['category_id'] === 'new',
				button( 'check' ),
				button( 'x' )
			) . '</td></tr></table>' .
			FormatInputTitle(
				_( 'Note: All unchecked means applies to all addresses' ),
				'',
				false,
				''
			)
		);
	}

	echo GetFieldsForm(
		'ADDRESS',
		$title,
		$RET,
		isset( $extra_fields ) ? $extra_fields : array()
	);

	// CATEGORIES.
	$categories_RET = DBGet( DBQuery( "SELECT ID,TITLE,SORT_ORDER
		FROM ADDRESS_FIELD_CATEGORIES
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
			FROM ADDRESS_FIELDS
			WHERE CATEGORY_ID='" . $_REQUEST['category_id'] . "'
			ORDER BY SORT_ORDER,TITLE" ), array( 'TYPE' => 'MakeFieldType' ) );

		echo '<div class="st">';

		FieldsMenuOutput( $fields_RET, $_REQUEST['id'], $_REQUEST['category_id'] );

		echo '</div>';
	}
}
