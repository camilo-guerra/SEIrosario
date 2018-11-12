<?php

if ( $_REQUEST['modfunc'] !== 'backup' )
{
	Drawheader( ProgramTitle() );
}

if ( $_REQUEST['modfunc'] === 'backup'
	&& isset( $_REQUEST['_ROSARIO_PDF'] ) )
{

	// FJ code inspired by phpPgAdmin.
	putenv( 'PGHOST=' . $DatabaseServer );
	putenv( 'PGPORT=' . $DatabasePort );
	putenv( 'PGDATABASE=' . $DatabaseName );
	putenv( 'PGUSER=' . $DatabaseUsername );
	putenv( 'PGPASSWORD=' . $DatabasePassword );

	$exe = escapeShellCmd( $pg_dumpPath );

	// Obtain the pg_dump version number and check if the path is good.
	$version = array();
	preg_match( "/(\d+(?:\.\d+)?)(?:\.\d+)?.*$/", exec( $exe . " --version" ), $version );

	if ( empty( $version ) )
	{
		$error[] = sprintf('The path to the database dump utility specified in the configuration file is wrong! (%s)', $pg_dumpPath);

		ErrorMessage( $error, 'fatal' );
	}

	$ctype = "application/force-download";

	header( "Pragma: public" );
	header( "Expires: 0" );
	header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
	header( "Cache-Control: public" );
	header( "Content-Description: File Transfer" );
	header( "Content-Type: $ctype" );

	$filename = Config( 'NAME' ) . '_database_backup_' . date( 'Y.m.d' ) . '.sql';
	$header="Content-Disposition: attachment; filename=" . $filename . ";";

	header( $header );
	header( "Content-Transfer-Encoding: binary" );

	// Build command for executing pg_dump.  '-i' means ignore version differences.
	$cmd = $exe . " -i";
	$cmd .= ' --inserts';

	// Execute command and return the output to the screen.
	passthru( $cmd );

	exit;
}

if ( ! $_REQUEST['modfunc'] )
{
	echo '<br />';
	PopTable('header',_('Database Backup'));
	echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=backup&_ROSARIO_PDF=true" method="POST">';
	echo '<br />';
	echo _('Download backup files periodically in case of system failure.');
	echo '<br /><br />';
	echo '<div class="center">' . SubmitButton( _( 'Download Backup File' ) ) . '</div>';
	echo '</form>';
	PopTable('footer');
}

