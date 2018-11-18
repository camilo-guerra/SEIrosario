<?php
/**
 * The base configurations of RosarioSIS
 *
 * You can find more information in the INSTALL.md file
 *
 * @package RosarioSIS
 */

/**
 * PostgreSQL Database Settings
 *
 * You can get this info from your web host
 */

// Database server hostname: use localhost if on same server.
$DatabaseServer = 'localhost';


// Database port: default is 5432.
$DatabasePort = '5432';

$DatabaseUsername = 'soyrosar_root';

$DatabasePassword = 'administrador';

$DatabaseName = 'soyrosar_sei';

// Note: we use slashes "/" instead of antislashes as directory separators.
//$pg_dumpPath = 'C:/wappstack/postgresql/bin/pg_dump.exe';

//$wkhtmltopdfPath = 'C:/wkhtmltopdf/bin/wkhtmltopdf.exe';

// Optional: activate other languages; here, for Spanish & French:
//$RosarioLocales = array( 'en_US.utf8', 'es_ES.utf8', 'fr_FR.utf8' );

/**
 * Paths
 */

/**
 * Full path to the PostrgeSQL database dump utility for this server
 *
 * @example /usr/bin/pg_dump
 * @example C:/Progra~1/PostgreSQL/bin/pg_dump.exe
 */
$pg_dumpPath = '';

/**
 * Full path to wkhtmltopdf binary file
 *
 * An empty string means wkhtmltopdf will not be called
 * and reports will be rendered in HTML instead of PDF
 *
 * @link http://wkhtmltopdf.org
 *
 * @example /usr/local/bin/wkhtmltopdf
 * @example C:/Progra~1/wkhtmltopdf/bin/wkhtmltopdf.exe
 */
$wkhtmltopdfPath = '';


/**
 * Default school year
 *
 * Do not change on install
 * Change after rollover
 * Should match the database to be able to login
 */
$DefaultSyear = '2018';


/**
 * Notify email address where to send
 * error,
 * new administrator,
 * new registration notifications
 *
 * Leave empty to not receive email notifications
 */
$RosarioNotifyAddress = 'plataeducativa@gmail.com';


/**
 * Locales
 *
 * Add other languages you want to support here
 *
 * @see locale/ folder
 *
 * For American, French and Spanish:
 *
 * @example array( 'en_US.utf8', 'fr_FR.utf8', 'es_ES.utf8' );
 */
$RosarioLocales = array( 'en_US.utf8' , 'es_ES.utf8');
