<?php
/**
 * Bottom
 *
 * Displays bottom menu
 * Handles Print & Inline Help functionalities
 *
 * @package RosarioSIS
 */

require_once 'Warehouse.php';

if ( isAJAX() )
{
	ETagCache( 'start' );
}

// Output Bottom menu.
if ( empty( $_REQUEST['bottomfunc'] ) ) : ?>

	<div id="footerwrap">
		<a id="BottomButtonMenu" href="#" onclick="expandMenu(); return false;" title="<?php echo _( 'Menu' ); ?>" class="BottomButton">
			<span><?php echo _( 'Menu' ); ?></span>
		</a>

		<?php // FJ icons.

		$btn_path = 'assets/themes/' . Preferences( 'THEME' ) . '/btn/';

		if ( isset( $_SESSION['List_PHP_SELF'] )
			&& ( User( 'PROFILE' ) === 'admin'
				|| User( 'PROFILE' ) === 'teacher') ) :

			switch ( $_SESSION['Back_PHP_SELF'] )
			{
				case 'student':

					$back_text = _( 'Student List' );
				break;

				case 'staff':

					$back_text = _( 'User List' );
				break;

				case 'course':

					$back_text = _( 'Course List' );
				break;

				default:

					$back_text = sprintf( _( '%s List' ), $_SESSION['Back_PHP_SELF'] );
			} ?>

			<a href="<?php echo $_SESSION['List_PHP_SELF']; ?>&amp;bottom_back=true" title="<?php echo $back_text; ?>" class="BottomButton">
				<img src="<?php echo $btn_path; ?>back.png" />
				<span><?php echo $back_text; ?></span>
			</a>

		<?php endif;

		if ( isset( $_SESSION['Search_PHP_SELF'] )
			&& ( User( 'PROFILE' ) === 'admin'
				|| User( 'PROFILE' ) === 'teacher' ) ) :

			switch ( $_SESSION['Back_PHP_SELF'] )
			{
				case 'student':

					$back_text = _( 'Student Search' );
				break;

				case 'staff':

					$back_text = _( 'User Search' );
				break;

				case 'course':

					$back_text = _( 'Course Search' );
				break;

				default:

					$back_text = sprintf( _( '%s Search' ), $_SESSION['Back_PHP_SELF'] );
			} ?>

			<a href="<?php echo $_SESSION['Search_PHP_SELF']; ?>&amp;bottom_back=true" title="<?php echo $back_text; ?>" class="BottomButton">
				<img src="<?php echo $btn_path; ?>back.png" />
				<span><?php echo $back_text; ?></span>
			</a>

		<?php endif;

		// Do bottom_buttons hook.
		do_action( 'Bottom.php|bottom_buttons' ); ?>

		<a href="Bottom.php?bottomfunc=print" target="_blank" title="<?php echo _( 'Print' ); ?>" class="BottomButton">
			<img src="<?php echo $btn_path; ?>print.png" />
			<span><?php echo _( 'Print' ); ?></span>
		</a>
		<a href="#" onclick="toggleHelp();return false;" title="<?php echo _( 'Help' ); ?>" class="BottomButton">
			<img src="<?php echo $btn_path; ?>help.png" />
			<span><?php echo _( 'Help' ); ?></span>
		</a>
		<a href="index.php?modfunc=logout" target="_top" title="<?php echo _( 'Logout' ); ?>" class="BottomButton">
			<img src="<?php echo $btn_path; ?>logout.png" />
			<span><?php echo _( 'Logout' ); ?></span>
		</a>
		<span class="loading BottomButton"></span>
	</div>

	<div id="footerhelp"></div>
<?php
// Print PDF.
elseif ( $_REQUEST['bottomfunc'] === 'print' ) :

	if ( ! empty( $_REQUEST['expanded_view'] ) )
	{
		$_SESSION['orientation'] = 'landscape';
	}

	// FJ call PDFStart to generate Print PDF.
	$print_data = PDFStart();

	$_REQUEST = $_SESSION['_REQUEST_vars'];

	$_REQUEST['_ROSARIO_PDF'] = true;

	$modname = $_REQUEST['modname'];

	if ( ! $wkhtmltopdfPath )
	{
		$_ROSARIO['allow_edit'] = false;
	}

	// FJ security fix, cf http://www.securiteam.com/securitynews/6S02U1P6BI.html.
	if ( mb_substr( $modname, -4, 4 ) !== '.php'
		|| mb_strpos( $modname, '..' ) !== false
		|| ! is_file( 'modules/' . $modname ) )
	{
		require_once 'ProgramFunctions/HackingLog.fnc.php';
		HackingLog();
	}
	else
		require_once 'modules/' . $modname;

	// FJ call PDFStop to generate Print PDF.
	PDFStop( $print_data );


// Inline Help.
elseif ( $_REQUEST['bottomfunc'] === 'help' ) :

	require_once 'Help_en.php';

	// FJ add help for non-core modules.
	$non_core_modules = array_diff( array_keys( $RosarioModules ), $RosarioCoreModules );

	$help_english = 'Help_en.php';

	// @deprecated since 3.9 use help text domain: help.po Gettext files.
	$help_translated = 'Help_' . substr( $locale, 0, 2 ) . '.php';

	foreach ( (array) $non_core_modules as $non_core_module )
	{
		$non_core_dir = 'modules/' . $non_core_module . '/';

		if ( file_exists( $non_core_dir . $help_translated ) ) // FJ translated help.
		{
			require_once $non_core_dir . $help_translated;
		}
		elseif ( file_exists( $non_core_dir . $help_english ) )
		{
			require_once $non_core_dir . $help_english;
		}
	}

	$help_text = '';

	if ( ! empty( $_REQUEST['modname'] ) )
	{
		foreach ( (array) $help as $program => $help_txt )
		{
			// FJ fix bug URL Modules.php?modname=Student_Billing/Statements.php&_ROSARIO_PDF.
			if ( $_REQUEST['modname'] === $program ||
				( mb_strpos( $program, $_REQUEST['modname'] ) === 0
					&& mb_strpos( $_SERVER['QUERY_STRING'], $program ) === 21 ) )
			{
				$help_text = $help_txt;
			}
		}
	}

	// Get default help text.
	if ( empty( $help_text ) )
	{
		$help_text = $help['default'];
	}

	if ( User('PROFILE') == 'student' )
	{
		$help_text = str_replace(
			'your child',
			'yourself',
			str_replace( 'your child\'s', 'your', $help_text )
		);
	}

	$help_text = str_replace( 'RosarioSIS', Config( 'NAME' ), $help_text );

	echo $help_text;

endif;

if ( isAJAX() )
{
	ETagCache( 'stop' );
}
