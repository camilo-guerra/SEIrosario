<?php

// set start date
if ( isset( $_REQUEST['day_start'] )
	&& isset( $_REQUEST['month_start'] )
	&& isset( $_REQUEST['year_start'] ) )
{
	$start_date = RequestedDate(
		$_REQUEST['year_start'],
		$_REQUEST['month_start'],
		$_REQUEST['day_start']
	);
}

if ( empty( $start_date ) )
{
	$_REQUEST['day_start'] = '01';
	$_REQUEST['month_start'] = date('m');
	$_REQUEST['year_start'] = date('Y');

	$start_date = $_REQUEST['year_start'] . '-' . $_REQUEST['month_start'] . '-' . $_REQUEST['day_start'];
}

// set end date
if ( isset( $_REQUEST['day_end'] )
	&& isset( $_REQUEST['month_end'] )
	&& isset( $_REQUEST['year_end'] ) )
{
	$end_date = RequestedDate(
		$_REQUEST['year_end'],
		$_REQUEST['month_end'],
		$_REQUEST['day_end']
	);
}

if ( empty( $end_date ) )
{
	$_REQUEST['day_end'] = date('d');
	$_REQUEST['month_end'] = date('m');
	$_REQUEST['year_end'] = date('Y');

	$end_date = $_REQUEST['year_end'] . '-' . $_REQUEST['month_end'] . '-' . $_REQUEST['day_end'];
}

if ( ! empty( $_REQUEST['type'] ) )
	$_SESSION['FSA_type'] = $_REQUEST['type'];
else
	$_SESSION['_REQUEST_vars']['type'] = $_REQUEST['type'] = $_SESSION['FSA_type'];

$header = '<a href="Modules.php?modname=' . $_REQUEST['modname'] .
	'&day_start=' . $_REQUEST['day_start'] . '&month_start=' . $_REQUEST['month_start'] . '&year_start=' . $_REQUEST['year_start'] .
	'&day_end=' . $_REQUEST['day_end'] . '&month_end=' . $_REQUEST['month_end'] . '&year_end=' . $_REQUEST['year_end'] .
	'&type=student">' .
	( ! isset( $_REQUEST['type'] ) || $_REQUEST['type'] === 'student' ?
		'<b>' . _( 'Students' ) . '</b>' : _( 'Students' ) ) . '</a>';

$header .= ' | <a href="Modules.php?modname='.$_REQUEST['modname'] .
	'&day_start=' . $_REQUEST['day_start'] . '&month_start=' . $_REQUEST['month_start'] . '&year_start=' . $_REQUEST['year_start'] .
	'&day_end=' . $_REQUEST['day_end'] . '&month_end=' . $_REQUEST['month_end'] . '&year_end=' . $_REQUEST['year_end'] .
	'&type=staff">' .
	( isset( $_REQUEST['type'] ) && $_REQUEST['type'] === 'staff' ?
		'<b>' . _( 'Users' ) . '</b>' : _( 'Users' ) ) . '</a>';

DrawHeader(($_REQUEST['type']=='staff'?_('User'):_('Student')).' &minus; '.ProgramTitle());
User('PROFILE')=='student'?'':DrawHeader($header);

if ( $_REQUEST['modfunc'] === 'delete'
	&& AllowEdit() )
{
	if ( $_REQUEST['item_id'] != '' )
	{
		if ( DeletePrompt( _( 'Transaction Item' ) ) )
		{
			require_once 'modules/Food_Service/includes/DeleteTransactionItem.fnc.php';

			DeleteTransactionItem(
				$_REQUEST['transaction_id'],
				$_REQUEST['item_id'],
				$_REQUEST['type']
			);

			// Unset modfunc & transaction ID & item ID & redirect URL.
			RedirectURL( array( 'modfunc', 'transaction_id', 'item_id' ) );
		}
	}
	elseif ( DeletePrompt( _( 'Transaction' ) ) )
	{
		require_once 'modules/Food_Service/includes/DeleteTransaction.fnc.php';

		DeleteTransaction( $_REQUEST['transaction_id'], $_REQUEST['type'] );

		// Unset modfunc & transaction ID & redirect URL.
		RedirectURL( array( 'modfunc', 'transaction_id' ) );
	}
}


$types = array('DEPOSIT' => _('Deposit'),'CREDIT' => _('Credit'),'DEBIT' => _('Debit'));
$menus_RET = DBGet(DBQuery('SELECT TITLE FROM FOOD_SERVICE_MENUS WHERE SCHOOL_ID=\''.UserSchool().'\' ORDER BY SORT_ORDER'));

$type_select = _('Type').': <select name=type_select><option value=\'\'>'._('Not Specified').'</option>';
foreach ( (array) $types as $short_name => $type)
	$type_select .= '<option value="'.$short_name.'"'.($_REQUEST['type_select']==$short_name ? ' selected' : '').'>'.$type.'</option>';
foreach ( (array) $menus_RET as $menu)
	$type_select .= '<option value="'.$menu['TITLE'].'"'.($_REQUEST['type_select']==$menu['TITLE'] ? ' selected' : '').'>'.$menu['TITLE'].'</option>';
$type_select .= '</select>';

//FJ add translation
function types_locale($type) {
	$types = array('Deposit' => _('Deposit'),'Credit' => _('Credit'),'Debit' => _('Debit'));
	if (array_key_exists($type, $types)) {
		return $types[ $type ];
	}
	return $type;
}
function options_locale($option) {
	$options = array('Cash ' => _('Cash'),'Check' => _('Check'),'Credit Card' => _('Credit Card'),'Debit Card' => _('Debit Card'),'Transfer' => _('Transfer'));
	if (array_key_exists($option, $options)) {
		return $options[ $option ];
	}
	return $option;
}
require_once 'modules/Food_Service/'.($_REQUEST['type']=='staff'?'Users':'Students').'/Statements.php';


function red($value)
{
	if ( $value<0)
		return '<span style="color:red">'.$value.'</span>';
	else
		return $value;
}
