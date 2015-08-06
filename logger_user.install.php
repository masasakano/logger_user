<?php

/**
 * @file
 * Set up the logger_user module.
 */

// global $databases;	// NOT yet set in hook_requirements($phase).
// require_once DRUPAL_ROOT . '/' . drupal_get_path('module', 'logger_user') . '/allbutbook.install.inc';

/**
 * Implements hook_requirements($phase).
 *
 * Makes installation fail if the database is not MySQL.
 */
function logger_user_requirements($phase) {
  $requirements = array();	// An empty array is returned if fine.

  switch($phase) {
  case 'install':
    $databasename = db_driver();	// Global $databases is empty (NULL?).
    // $databasename = $databases['default']['default']['driver'];
    $requirements['logger_user_requirement'] = array(
      'title' => 'logger_user install requirement',	// Careful to use t(). 
      // 'value' => Version-Number, 
      'severity' => REQUIREMENT_OK,
      // 'weight' => -1000,
    );

    if ('mysql' == $databasename) {
      // drupal_set_message(sprintf('Database=(%s) - OK.  Proceed to enable the module logger_user', $databasename), 'status');
      $requirements['logger_user_requirement']['severity'] = REQUIREMENT_INFO;
    }
    else {
      drupal_set_message(sprintf('Enabling the module logger_user failed, because the database in this environment is not MySQL but (%s).  If you know what you are doing and want to proceed anyway, edit hook_requirements() in logger_user.install and try again.', $databasename), 'error');
      $requirements['logger_user_requirement']['severity'] = REQUIREMENT_ERROR;
    }
    break;
  default:
    // Do nothing.
  }

  return $requirements;
}	// function logger_user_requirements($phase) {

/**
 * Implements hook_install().
 *
 * Sets the module-specific variable 'logger_user_status' for Drupal, which is
 * an associative array containing:
 *   - lastaccessed: UNIX time last viewed.
 *   - timeupdated: UNIX time last updated due to users hook or viewed.
 *   - nnewusers: Number of new users since lastaccessed.
 *   - nrows: number of rows updated for each case.
 *     @see LoggerUser::update_db_users()
 */
function logger_user_install() {
  $val = variable_get(LoggerUser::VARNAME_STATUS, NULL);
	if (is_null($val)) {
		$val = array(
			'lastaccessed' => 0,
			'timeupdated'  => 0,
			'nnewusers' => 0,
			'nrows' => array(),
		);
		variable_set(LoggerUser::VARNAME_STATUS, $val);
	}
}

/**
 * Implements hook_uninstall().
 *
 * Necessary even if empty, so as to delete the table defined in the schema,
 * when the module is uninstalled.
 * Also, without this, if it fails to create the table in the first enabling,
 * the table will be never created even after disabling/re-installing/enabling.
 *
 * @see https://api.drupal.org/api/drupal/includes!common.inc/function/drupal_install_schema/7
 */
function logger_user_uninstall() {
  // variable_del(LoggerUser::VARNAME_STATUS);	// LoggerUser may not be seen.
  variable_del('logger_user_status');
}

/*
function logger_user_disable() {
}
*/

/**
 * Implements hook_schema().
 *
 * @see https://api.drupal.org/api/drupal/modules!system!system.api.php/function/hook_schema/7
 * For Schema Reference:
 *   @see https://www.drupal.org/node/146939
 * Schema module (maybe useful?):
 *   @see https://www.drupal.org/project/schema
 * For drupal_get_schema_unprocessed():
 *   @see https://api.drupal.org/api/drupal/includes!common.inc/function/drupal_get_schema_unprocessed/7
 * Note:
 *   @link drupal_get_schema() https://api.drupal.org/api/drupal/includes!bootstrap.inc/function/drupal_get_schema/7 @endlink
 *   does not provide "description" in each column (field),
 *   hence drupal_get_schema_unprocessed() is used here.
 */
function logger_user_schema() {

  $newtblprefix = 'logger_user_';

  // Column names (except "r.name"(=rolename) in "role")
  $tmplkeys = array(
    'users' => array(
      'uid',
      'name',
      'mail',
      'created',
      'access',
      'login',
      'status',
      'timezone',
      'language',
      'init',
    ),
    'users_roles' => array(
      'rid',
    ),
    'watchdog' => array(
      'wid',
      'timestamp',
      'hostname',
      'message',
      'variables',
      'link',
    ),
  );

  $newschemafields = array(
    ($newtblprefix . 'users') => array(
      'inwatchdog' => array(
        'description' => 'Exists(1) in watchdog or not(0)',
        'type' => 'int',
        'size' => 'tiny',
        'not null' => TRUE,
        'default' => 0,
      ),
      'inusers' => array(
        'description' => 'Exists(1) in users or not(0)',
        'type' => 'int',
        'size' => 'tiny',
        'not null' => TRUE,
        'default' => 0,
      ),
      'adminregister' => array(
        'description' => 'Registered by admin(1) or oneself(0)',
        'type' => 'int',
        'size' => 'tiny',
        'not null' => TRUE,
        'default' => 0,
      ),
    ),
  );

  // The following info is used in drupal_get_schema_unprocessed().
  $artblmodule = array(
    // Table          Module
    'watchdog'    => 'dblog',
    'users'       => 'user',
    'users_roles' => 'user',
    'role'        => 'user',
  );

  // Reads the existing schema.
  $sc_tmpl = array();
//drupal_set_message('In install, starting getting $sc_tmpl.', 'status');
  foreach (array_keys($tmplkeys) as $value) {
    $sc_tmpl[$value] = drupal_get_schema_unprocessed($artblmodule[$value], $value)['fields'];
  }
//drupal_set_message('In install, $sc_tmpl=' . var_export($sc_tmpl, TRUE), 'error');

  $arfield = array();

  foreach ($tmplkeys as $tbl => $arcol) {
    foreach ($arcol as $value) {
      $arfield[$value] = $sc_tmpl[$tbl][$value];
      $arforeign = array(
        ($newtblprefix . $value) => array(
          'table' => $tbl,
          'columns' => array($value => $value),
        ),
      );
    }
  }

  // Changes "serial" of "type" into "unsigned int" (in watchdog.wid)
  // @see https://www.drupal.org/node/159605
  foreach ($arfield as $key => $value) {
    if ('serial' == $arfield[$key]['type']) {
      $arfield[$key]['type'] = 'int';
      $arfield[$key]['unsigned'] = TRUE;
    }
  }

  // Updates (most of) columns from "watchdog" and "users" to accept NULL.
  foreach (array('watchdog', 'users') as $tbl) {
    foreach ($tmplkeys[$tbl] as $val) {
      switch ($val) {
      case 'uid':
      case 'name':
      case 'created':
      case 'rid':
        $arfield[$val]['not null'] = TRUE;	// Should not be needed.
        break;
      default:
        $arfield[$val]['not null'] = FALSE;
      }
    }
  }

  // Deletes "Primary Key: " from descriptions in some (rid, wid).
  foreach ($arfield as $key => $value) {
    if ($key == 'uid') {
      continue;
    } else {
      $arfield[$key]['description'] =
        preg_replace('/^\s*Primary\s+Key:?\s*/',
                     '',
                     $arfield[$key]['description']
        );
    }
  }

//drupal_set_message('In install, $arfield=' . var_export($arfield, TRUE), 'error');

  // You can add another $schema[$newtblprefix . 'another'] later if you want.
  $schema[$newtblprefix . 'users'] = array(
    'description' => 'Integrated from {watchdog} and {users}.',
    // No t() here,
    // @see https://www.drupal.org/node/224333#schema_translation
    'fields' => $arfield + $newschemafields[$newtblprefix . 'users'],
    'primary key' => array('uid'),
    'unique keys' => array('wid' => array('wid')),
    'foreign keys' => $arforeign,	// For documentation only in Drupal 7
    'indexes' => array(
      'uid' => array('uid'),
      'rid' => array('rid'),
			'name' => array('name'),
      'mail' => array('mail'),
      'timezone' => array('timezone'),
      'language' => array('language'),
      'hostname' => array('hostname'),
    ),	// @see http://drupal.stackexchange.com/questions/75641/what-is-the-reason-to-define-indexes-in-hook-schema
    //
    // The following two are probably not needed in default.
    // @see http://api.drupalhelp.net/api/drupal/includes--database--mysql--database.inc/7
    // @code
    // 'mysql_character_set' => 'UTF8',
    // 'collation' => 'utf8_general_ci',
    // @endcode
    //
    // This is Drupal 6 only:
    // @code
    // 'mysql_suffix' => " DEFAULT CHARACTER SET UTF8 ENGINE = INNODB AUTO_INCREMENT=3844 ",
    // @endcode
    // @see https://www.drupal.org/node/146939
	// @see https://www.drupal.org/node/146939
  );

  // NOTE: 'fields' =>
      // array(
      // 'list' => array(
      //   'description' => 'Example list',	// No t() here.  See above.
      //   'type' => 'varchar',	// (varchar|char|int|serial|float|numeric|text|blob|datetime)
      //   'not null' => true,
      //   'default' => 'ABC',	// If 'not null'==true, this should be set.
      //   'length' => true,	// mandatory for varchar
      //   // 'precision' => true,	// mandatory for numeric
      //   // 'scale' => true,	// mandatory for numeric
      // ),


  // PREFIX_'watchdog'
  // | Field            | Type             | Null | Key | Default | Extra |
  // | hostname         | varchar(128)     | NO   |     |         |       |
  // 
  // PREFIX_'users'
  // | uid              | int(10) unsigned | NO   | PRI | 0       |       |
  // | name             | varchar(60)      | NO   | UNI |         |       |
  // | pass             | varchar(128)     | NO   |     |         |       |
  // | mail             | varchar(254)     | YES  | MUL |         |       |
  // | theme            | varchar(255)     | NO   |     |         |       |
  // | signature        | varchar(255)     | NO   |     |         |       |
  // | signature_format | varchar(255)     | YES  |     | NULL    |       |
  // | created          | int(11)          | NO   | MUL | 0       |       |
  // | access           | int(11)          | NO   | MUL | 0       |       |
  // | login            | int(11)          | NO   |     | 0       |       |
  // | status           | tinyint(4)       | NO   |     | 0       |       |
  // | timezone         | varchar(32)      | YES  |     | NULL    |       |
  // | language         | varchar(12)      | NO   |     |         |       |
  // | picture          | int(11)          | NO   | MUL | 0       |       |
  // | init             | varchar(254)     | YES  |     |         |       |

  return $schema;	// Do NOT delete!
}


/**
 * If you add a column 'newcol' in the table 'logger_user_users',
 * or add an entirely new table 'another2', update the follwoing!
 * Or, if you change the primary key, too.
 * https://www.drupal.org/node/146862
 * https://api.drupal.org/api/drupal/modules!system!system.api.php/function/hook_update_N/7
 * https://www.drupal.org/node/150215	"Updating tables: hook_update_N() functions"
 * 
 * Note the number of the hook name (_7000 below) is important.
 * See the above API!
 * The last two digits MUST match that described in .info (!).
 */
/*
function logger_user_update_7000() {
  $schema = drupal_get_schema('logger_user_users');
  db_add_field('logger_user_users', 'newcol', $schema['newcol']);
  // You may use:    if (!db_field_exists('mytable', 'key')) {}
  $schema2 = drupal_get_schema('another2');
  db_create_table('another2', $schema2['another2']);
}
*/


