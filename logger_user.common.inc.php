<?php

/**
 * @file
 * Common functions etc in the Logger_User module.
 * 
 */

/**
 * LoggerUser class to encompass functions.
 * 
 */
class LoggerUser {

  const DB_NAME        = 'logger_user_users';
  const WATCHDOG_TYPE  = 'logger_user';
	const VARNAME_STATUS = 'logger_user_status';	// Variable name.
	// @see hook_install().

  const SQL_WD_SUBSTR_COND_NEW = "SUBSTRING(w.message FROM 1 FOR 3) = 'New'";
  const SQL_WD_SUBSTR_UID = "SUBSTRING(w.link FROM POSITION('user/' IN w.link)+5 FOR POSITION('/edit' IN w.link)-POSITION('user/' IN w.link)-5)";

	// public function __construct($arguments) {
	// }


  /**
   * Returns the table alias information.
   * 
	 * An internal function (protected).
	 * This function receives the column-name (maybe aliased)
	 * like 'rolename' and field-name used for the SQL database like 'r.role'.
	 * Basically, they are a key in Array $arbase and $arbase[SOME]['field'],
	 * respectively.
	 *
   * @param string $colname
   *   Column name. eg., 'rolename'
   * @param string $field
   *   Field name.  eg., 'r.role'
   * @return array
   *   An associative array containing:
   *   - is_alias: boolean
   *   - tablealias: string, eg, 'u' (for Table users)
   *   - fieldname: string, eg, 'name' (for u.name)
  **/
	protected static function get_table_alias_info($colname, $field) {
		$arret = array(
			'is_alias' => FALSE,
			'tablealias' => '',
			'fieldname' => '',
		);

		if (preg_match('/^([^.]+)\.(\S+)/', $field, $m)) {
			$arret['tablealias'] = $m[1];
			$arret['fieldname']  = $m[2];
			if ($colname === $m[2]) {
				return $arret;
			}
			else {
				$arret['is_alias'] = TRUE;
				return $arret;
			}
		}
		else {
			drupal_set_message(sprintf('Should not happen. Contact the developer. logger_user_select_columns: eacha_field=(%s). (Check $arbase.)', $field), 'error');
			return FALSE;
		}
	}	// protected static function get_table_alias_info($colname, $field) {


  /**
   * Selects which columns to display.
   * 
   * @param string $fmt
   *   Choices: (concise|standard|all). Ignored for ARG[1]:$purpose=='colnames'
   * @param string $purpose
   *   Choices: (colnames|header|main)
   * @param array $opts
   *   (optional) NULL or an associative array containing:
   *     - tbls: array. Default: array('u', 'w', 'ur', 'r'),
	 *       namely, users, watchdog, users_roles, role
   *     - use_own_db: boolean (Default: FALSE). True if using the own Database.
   * @param object $eachres
   *   (optional) Each of the return of @ref db_select()->execute(),
   *     only read when $purpose=='main'.  Received as a reference.
   * @return array
   *   - For the ARG[1] of 'colnames', returns the associative array
	 *     for all the columns regardless of ARG[0], like:
   *     @code
   *    array('u' => array('uid', 'name'), 'w' => array('wid'),
   *          'alias' => array('r' => array('name' => 'rolename')))
   *     @endcode
   *   - For the ARG[1] of 'header', an array of associative arrays like:
   *     @code
	 *    array(
   *      array('data' => 'UID', 'field' => 'u.uid', 'sort' => 'desc'),
   *      ...
	 *    )
   *     @endcode
   *   - For the ARG[1] of 'main', an associative array of an array like:
   *     @code
	 *    array('data' => array(5, 1234, ...));
   *     @endcode
  **/
	public static function select_columns($fmt, $purpose, $opts = array(), &$eachres = NULL) {

		$opts_def = array(
			'tbls' => array('u', 'w', 'ur', 'r'),	// Default.
			'use_own_db' => FALSE,
		);
		if ($opts) {
			$opts = array_merge($opts_def, $opts);
		}
		else {
			// NULL is given.
			$opts = $opts_def;
		}

		if ($opts['use_own_db']) {
			$aliasout = self::tablealiases('self')[0];	// 'l'
		}

		$hsuserstatus = array(
			0 => 'Blocked',
			1 => 'Active',
			2 => 'never',
		);

    $arbase = array(
      'uid'       => array('data' => t('UID'),  'field' => 'u.uid', 'sort' => 'desc'),
      'name'      => array('data' => t('User'), 'field' => 'u.name'),
      'mail'      => array('data' => t('Mail'), 'field' => 'u.mail'),
      'created'   => array('data' => t('Created'),       'field' => 'u.created', 'sort' => 'desc'),
      'access'    => array('data' => t('Last access'),   'field' => 'u.access', 'sort' => 'desc'),
      'login'     => array('data' => t('Last login'),    'field' => 'u.login', 'sort' => 'desc'),
      'status'    => array('data' => t('Status'),        'field' => 'u.status'),
      'timezone'  => array('data' => t('Time zone'),     'field' => 'u.timezone'),
      'language'  => array('data' => t('Lang'),      'field' => 'u.language'),
      'init'      => array('data' => t('Initial email'), 'field' => 'u.init'),
      'wid'       => array('data' => t('WD ID'), 'field' => 'w.wid'),
      'timestamp' => array('data' => t('WD Date'),  'field' => 'w.timestamp', 'sort' => 'desc'),
      'hostname'  => array('data' => t('Hostname'), 'field' => 'w.hostname'),
      'message'   => array('data' => t('Watchdog Message'), 'field' => 'w.message'),
      'variables' => array('data' => t('WD Variables'), 'field' => 'w.variables'),
      'link'      => array('data' => t('WD Link'),  'field' => 'w.link'),
      'inusers'   => array('data' => t('In users?'),    'field' => 'l.inusers'),
      'inwatchdog'=> array('data' => t('In watchdog?'), 'field' => 'l.inwatchdog'),
      'adminregister' => array('data' => t('Registered by admin?'), 'field' => 'l.adminregister'),
      'rid'       => array('data' => t('RID'),  'field' => 'ur.rid'),
      'rolename'  => array('data' => t('Role'), 'field' => 'r.name'),
    );

    $arcol2use = array(
      'concise' => array(
        'uid', 'created', 'name', 'rolename', 'status', 'mail', 'hostname',
      ),
      'standard' => array(
        'uid', 'wid', 'created', 'name', 'rolename', 'status', 'mail', 'hostname', 'language',
      ),
      'all' => array(
        'uid',
        'wid',
        'inwatchdog',
        'created',
        'name',
        'inusers',
        'rolename',
        'status',
        'mail',
        'init',
        'hostname',
        'language',
				'adminregister',
        'access',
        'login',
        'timestamp',
        'message',
        'link',
      ),
    );

    // Modifies $arbase, aka removes the elements not needed,
    // following the argument given to the function.
    $arkey2del = array();
    foreach ($arbase as $kbase => $eacha) {
      $arkey2del[] = $kbase;
      foreach ($opts['tbls'] as $tbl) {
        if (preg_match('/^([^.]+)\.(\S+)/', $eacha['field'], $m)) {
          if ($m[1] === $tbl) {
            // The element is used here.
            array_pop($arkey2del);
            break;
          }
        }
      }
    }
    foreach ($arkey2del as $eachk) {
      unset($arbase[$eachk]);
    }

    switch($purpose){
    case 'colnames':
      $arret = array();
      foreach ($arbase as $colname => $eacha) {
                // 'rolename'  => array('data' => t('Role'), 'field' => 'r.name')
				$ary = self::get_table_alias_info($colname, $eacha['field']);

				if ($ary['is_alias']) {
          $arret['alias'][$ary['tablealias']][$ary['fieldname']] = $colname;
             // ['alias'][ 'r' ]['name']= 'rolename'
				}
				else {
					if ($opts['use_own_db']) {
						$arret[$aliasout][] = $ary['fieldname'];
					}
					else {
						$arret[$ary['tablealias']][] = $ary['fieldname'];
					}
				}
if (FALSE) {
        if (preg_match('/^([^.]+)\.(\S+)/', $eacha['field'], $m)) {
          if ($colname === $m[2]) {
            // No alias.
						if ($opts['use_own_db']) {
							$arret[$aliasout][] = $m[2];
						}
						else {
							$arret[$m[1]][] = $m[2];
						}
          }
          else {
            $arret['alias'][$m[1]][$m[2]] = $colname;
               // ['alias'][ 'r' ]['name']= 'rolename'
          }
        }
        else {
          drupal_set_message(sprintf('Should not happen. Contact the developer. logger_user_select_columns: eacha_field=(%s). (Check $arbase.)', $eacha['field']), 'error');
        }
}
      }
      return $arret;
      break;

		case 'header':
			$arret = array();
			foreach ($arcol2use[$fmt] as $eachcolname) {
				$artmp = array();
				foreach (array('data', 'field', 'sort') as $eacharykey) {
					if (!empty($arbase[$eachcolname][$eacharykey])) {
						// if ($opts['use_own_db'] && ('field' == $eacharykey)) {
						if (('field' == $eacharykey)) {
							$ary = self::get_table_alias_info(
								$eachcolname,
								$arbase[$eachcolname][$eacharykey]
							);
							if ($ary['is_alias']) {
								$artmp[$eacharykey] = $eachcolname;	// 'rolename' only, for now.
							}
							elseif ($opts['use_own_db']) {
								$artmp[$eacharykey] = preg_replace(
									'/([a-z0-9]+)\./i',
									$aliasout . '.',
									$arbase[$eachcolname][$eacharykey],
									1
								);
							}
							else {
								$artmp[$eacharykey] = $arbase[$eachcolname][$eacharykey];
							}
						}
						else {
							  $artmp[$eacharykey] = $arbase[$eachcolname][$eacharykey];
						}
					}
				}
				$arret[] = $artmp;
			}
			if (($fmt !== 'concise') && ($fmt !== 'all')) {
				$arret[] = array('data' => t('Operations'));
			}
			return $arret;
			break;

		case 'main':
			if (is_null($eachres)) {
				drupal_set_message(sprintf('Should not happen. Contact the developer. (eachres==NULL) in logger_user_select_columns: Purpose=(%s).', $purpose), 'error');
				return NULL;
			}

			$arret = array('data' => array());
			$ardata = array();
			foreach ($arcol2use[$fmt] as $eachcolname) {
				switch($eachcolname) {
				case 'name':
					if ($eachres->inusers) {
						$ardata[] = theme('username', array('account' => $eachres));
					}
					else {
						$ardata[] = check_plain($eachres->$eachcolname);
					}
					break;

				case 'status':
					$userstatus = $eachres->$eachcolname;
					if ($userstatus && ($eachres->login < 1)) {
						$userstatus = 2;
					}
					$ardata[] = $hsuserstatus[$userstatus];
					break;

				case 'wid':
					if ($eachres->inwatchdog) {
						$ardata[] = l(
							sprintf('%d', $eachres->$eachcolname),
							sprintf('admin/reports/event/%d', $eachres->$eachcolname),
							array('language' => $GLOBALS['language'])
						);
					}
					else {
						// Not in watchdog table any more, hence no link.
						$ardata[] = sprintf('%d', $eachres->$eachcolname);
					}
					break;

				case 'uid':
					$ardata[] = sprintf("%d", $eachres->$eachcolname);
					break;

				case 'link':
					$ardata[] = filter_xss($eachres->$eachcolname);
					break;

				case 'init':
					if ($eachres->init == $eachres->mail) {
						$ardata[] = '*SAME*';
					}
					else {
						$ardata[] = check_plain(trim($eachres->$eachcolname));
					}
					break;

				case 'inusers':
				case 'inwatchdog':
				case 'adminregister':
					if ($eachres->$eachcolname) {
						$ardata[] = 'T';
					}
					else {
						$ardata[] = 'F';
					}
					break;

				case 'message':
					if (empty($eachres->$eachcolname) || is_null($eachres->variables) ) {
						$ardata[] = '';
					} else {
						$artmp = unserialize($eachres->variables);
						if (empty($artmp)) {
							$ardata[] = '';
						}
						else {
							$a = array();
							foreach ($artmp as $k => $v) {
								$a[$k] = $v;
							}
							$ardata[] = 
								format_string($eachres->$eachcolname, $a);
							// For some reason unserialize($eachres->variables) does not work.
//dpm(unserialize($eachres->variables)); 
//$a=unserialize($eachres->variables); 
// drupal_set_message('DEBUG-blob123 =', var_export($a, TRUE));
//drupal_set_message('DEBUG-blob123 =', print_r($eachres->variables, TRUE));
						}
					}
					break;

				default:
					if (array_key_exists('sort', $arbase[$eachcolname])) {
						// Date-type.
						$ardata[] = format_date($eachres->$eachcolname, 'short');
					}
					else {
						$ardata[] = check_plain(trim($eachres->$eachcolname));
//drupal_set_message(sprintf('logger_user_select_columns: eachcolname=(%s)(%s).', $eachcolname, trim($eachres->$eachcolname)), 'warning');
					}
				}	// switch($eachcolname) {
			}	// foreach ($arcol2use[$fmt] as $eachcolname) {

			if (($fmt !== 'concise') && ($fmt !== 'all')) {
				$ardata[] = filter_xss($eachres->link);
			}

			$arret = array('data' => $ardata);
			return $arret;
			break;

		default:
			drupal_set_message(sprintf('Should not happen. Contact the developer. logger_user_select_columns: Purpose=(%s).', $purpose), 'error');
		}	// switch($purpose){

	}	// public static function select_columns($fmt, $purpose, $opts = array(), &$eachres = NULL) {


  /**
   * Return the array of (Database-Table) aliases.
	 *
	 * @param string $purpose
	 *   Choices: (update_db|)
   * @return array
	 *   Array of table aliases; e.g., array('u', 'ur', 'w')
	 */
  public static function tablealiases($purpose='update_db') {
		switch ($purpose) {
		case 'update_db':
			return array('u', 'ur', 'w');
			break;
		case 'self':
			return array('l');
			break;
		default:
			return NULL;
		}
	}


  /**
   * Return the Database Table name for the given alias.
	 *
	 * @param string $alias
	 *   Choices: (u|r|w)
   * @return string
	 *   Table name.
	 */
  public static function tablename($alias) {
		$hsdbot = array(
			'l'  => self::DB_NAME,
			'u'  => 'users',
			'ur' => 'users_roles',
			'r'  => 'role',
			'w'  => 'watchdog',
		);

		return $hsdbot[$alias];
	}

  /**
   * Return the extra column related to the given Table alias.
	 *
	 * Column 'adminregister' is not considered here, because it can be
	 * set only in hook_user_insert().
	 *
	 * @param string $alias
	 *   Choices: (u|r|w)
   * @return string
	 *   Column name (inusers|inwatchdog).
	 */
  public static function columnextra($alias) {
		$hsquery_set_extra = array(
			'u'  => array('inusers', 'status'),		// Set if found in users.
			'ur' => array(),
			'r'  => array(),
			'w'  => array('inwatchdog'),	// Set if found in watchdog.
		);

		return $hsquery_set_extra[$alias];
	}


  /**
   * Return the 'type' for drupal_set_message() for the given watchdog constant.
	 *
	 * @param int $watchdog_const
	 *   WATCHDOG_NOTICE etc.
   * @return string
	 *   (status|warning|error)
	 */
  public static function get_drupal_message_type($watchdog_const) {
		switch($watchdog_const) {
		case WATCHDOG_EMERGENCY:
		case WATCHDOG_ALERT:
		case WATCHDOG_CRITICAL:
		case WATCHDOG_ERROR:
			return 'error';
			break;

		case WATCHDOG_WARNING:
			return 'warning';
			break;

		case WATCHDOG_NOTICE:
		case WATCHDOG_INFO:
		case WATCHDOG_DEBUG:
			return 'status';
			break;

		default:
			return FALSE;
		}
	}	// public static function get_drupal_message_type($watchdog_const) {


  /**
   * Return the number of the updated columns related to the given Table alias.
	 *
	 * @param array $aliases
	 *   eg., array('u', 'ur', 'w'), or self::tablealiases()
   * @return array
	 *   An associative array containing,
	 *   each table-alias with the number of affected rows, e.g, $ret['w']==67
	 */
  public static function update_db_users_update($aliases) {
		$hsret = array();

    $aliasout = self::tablealiases('self')[0];
		$hswhere = array(
			'u'  => 'l.uid = u.uid AND (l.created = u.created OR l.created < 1)',
			'ur' => 'l.uid = ur.uid AND l.rid != ur.rid',
			'w'  => implode(
				' AND ',
				array(
					"w.type = 'user'",
					self::SQL_WD_SUBSTR_COND_NEW,
					self::SQL_WD_SUBSTR_UID . ' = l.uid',
				)
			),
		);

		foreach ($aliases as $aliasin) {
			// Main loop for updating Table 'logger_user_users'

			$dbin = self::tablename($aliasin);
			$allfields = self::select_columns(
				'all',	// All the columns
				'colnames',
				array('tbls' => array($aliasin))
			);	// eg., array('wid', 'hostname', ...)

			// Constructing a string for "UPDATE SET", ie., "l.name=u.name, ...".
			$arcolname = array();
			foreach ($allfields as $k => $eacha) {
				if ($aliasin != $k) {
					continue;	// Should not happen.
				}
				foreach ($eacha as $eachv) {
					if ('uid' == $eachv) {
						continue;
					}
					$arcolname[] = sprintf('%s.%s = %s.%s', $aliasout, $eachv, $aliasin, $eachv);
				}
			}

			$s = self::columnextra($aliasin);
			if (! empty($s)) {
				$arcolname[] = sprintf('%s.%s = 1', $aliasout, $s[0]);
				// eg., ', l.inusers = 1'
				// nb., for 'u', $s[1]=='status', which is ignored here.
			}

			if ('u' == $aliasin) {
				$hsaccess_prev = self::get_admins_access();
			}

      $query = sprintf(
				'UPDATE {%s} AS %s, {%s} AS %s SET %s WHERE %s;',
				self::DB_NAME,
				$aliasout,
				$dbin, 
				$aliasin,
				implode(', ', $arcolname),
				$hswhere[$aliasin]
			);

      $result = db_query($query);
  
			$hsret[$aliasin] = $result->rowCount();
			// Number of updated rows.

			if (('u' == $aliasin) && $hsaccess_prev['isdefined']) {
				$hsaccess_now = self::get_admins_access();
				if ($hsaccess_now['access'] > $hsaccess_prev['access']) {
					// 'access' time of you has changed in the table users.
					// That is expected, hence the count is removed from the report.
					$hsret[$aliasin]--;
				}
			}

		}	// foreach ($aliases as $aliasin) {

    return $hsret;

	}	// public static function update_db_users_update($alias) {


  /**
   * Gets the 'access' (time) for yourself (usually admin).
	 *
	 * This value changes all the time while you are browsing, of course!
	 * And, you wouldn't need that information when seeing a log.
	 *
   * @return array
	 *   An associative array containing:
	 *   - isdefined: boolean (FALSE if user's access is not found)
	 *   - access: int
	 */
  protected static function get_admins_access() {
    $aliasout = self::tablealiases('self')[0];	// 'l'
		$hsret = array(
			'isdefined' => FALSE,
			'access'  => NULL,
		);

		$result = db_select(self::DB_NAME, $aliasout)
			->fields($aliasout, array('access'))
			->condition('uid', $GLOBALS['user']->uid)
			->execute();

		$isaccessdefined = FALSE;
		if ($result->rowCount() > 0) {
			$hsret['isdefined'] = TRUE;
			$hsret['access']  = $result->fetchCol()[0];
		}

		return $hsret;
	}


  /**
   * Return the number of the inserted columns related to the given Table alias.
	 *
	 * @param array $aliases
	 *   eg., array('u', 'ur', 'w'), or self::tablealiases()
   * @return array
	 *   An associative array containing,
	 *   each table-alias with the number of affected rows, e.g, $ret['w']==67
	 */
  public static function update_db_users_insert($aliases) {
		$hsret = array();

		$hswhere = array(
			'u' => 'u.uid > 0',
			'ur' => 'ur.uid > 0',
			'w' => implode(
				' AND ',
				array(
					"w.type = 'user'",
					self::SQL_WD_SUBSTR_COND_NEW,
					self::SQL_WD_SUBSTR_UID . ' > 0',
				)
			),
		);

		$hsorderby = array(
			'u' => 'u.uid',
			'ur' => 'ur.uid',
			'w' => self::SQL_WD_SUBSTR_UID,
		);

		foreach ($aliases as $aliasin) {
			// Main loop for inserting rows to Table 'logger_user_users'
			// Note it is necessary to loop over 'u' (users) and 'w' (watchdog).
			// Expetedly most of users recorded in watchdog are probably in users.
			// But if the admin cancelled some accounts before this module detects,
			// the record of those appear only in watchdog but not in users.

			if ('r' == $aliasin OR 'ur' == $aliasin) {
				// No column inserted due to updated Table users_roles
				continue;
			}

			$dbin = self::tablename($aliasin);
			$allfields = self::select_columns(
				'all',	// All the columns
				'colnames',
				array('tbls' => array($aliasin))
			);	// eg., array('wid', 'hostname', ...)

			// Constructing a string for "INSERT INTO", ie., "u.uid, u.name, ...".
			$arcolname = array();
			$arcoloutname = array();
			foreach ($allfields as $k => $eacha) {
				if ($aliasin != $k) {
					continue;	// Should not happen.
				}
				foreach ($eacha as $eachv) {
					// nb, UID is needed; Otherwise all columns are regarded as UID==0.
					$arcolname[] = sprintf('%s.%s', $aliasin, $eachv);
					$arcoloutname[] = $eachv;
				}
			}

			$s = self::columnextra($aliasin);
			if (! empty($s)) {
				$arcolname[] = '1';
				$arcoloutname[] = $s[0];	// eg. "inusers"
			}

			if ('w' === $aliasin) {
				// Add 'uid' in the case of watchdog.
				// Otherwise, it would be rejected, because uid is the primary key.
				array_unshift($arcolname, self::SQL_WD_SUBSTR_UID);
				array_unshift($arcoloutname, 'uid');
			}

			$query = sprintf(
				'INSERT IGNORE INTO {%s} (%s) SELECT %s FROM {%s} AS %s WHERE %s ORDER BY %s;',
				self::DB_NAME,
				implode(', ', $arcoloutname),
				implode(', ', $arcolname),
				$dbin,
				$aliasin,
				$hswhere[$aliasin],
				$hsorderby[$aliasin]
			);
			// Note:
			// INSERT does not accept an alias, so "WHERE uid<>u.uid" raises error.
			// Therefore, INSERT "IGNORE" is essential.

			$result = db_query($query);
			$hsret[$aliasin] = $result->rowCount();
			// Number of inserted rows.

		}	// foreach ($aliases as $aliasin) {


		if ($hsret['w'] > 0) {
		// if (TRUE) {
			$ncols = self::update_db_users_update_with_wd();
			if ($ncols > 0) {
				$errmsg = sprintf(
					'%d columns (likely %d rows) did not have values in name, init, created in users table, hence they are imported from watchdog table.',
					$ncols,
					$ncols/3
				);
				drupal_set_message($errmsg, 'status');
				watchdog(self::WATCHDOG_TYPE, $errmsg, NULL, WATCHDOG_NOTICE);
			}
		}

    return $hsret;

	}	// public static function update_db_users_insert($aliases) {


  /**
   * Updates name and init (email) based on watchdog information.
   *
   * Background is as follows.
   * When user accounts were cancelled before this module is enabled,
   * those user information no longer exists in "users" table.
   * However, the record may remain in "watchdog".
   * In that case, it is possible to retrieve the user name and email address
	 * when the user is registered from the watchdog table: the column
	 * "variables", which is copied into the our table, logger_user_users.
   *
	 * This routine tries to recover those two information, which will be
	 * stored in the column "name" and "init".
	 * Note the "variables" column is a bynary-type data, hence it is tricky
	 * to deal within the SQL. We update those rows one by one with Drupal.
	 * It is expected there aren't a huge number of such rows, so it'd be OK.
   *
   * @return int
	 *   The number of columns updated; usually 3 x rows (name, init, created).
   */
  public static function update_db_users_update_with_wd() {
		$aliasout = self::tablealiases('self')[0];
		$wdkeynames = array(
			'name' => '%name',	// Key name embedded in the 'variables' column.
			'init' => '%email',	// The same.
			'created' => 'timestamp',
		);
		$nullvalues = array(
			'name' => '',	// Technically, no null value is allowed for this in Schema.
			'init' => '',
			'created' => 0,
		);
		$extracol = array(	// Required extra column to process each.
			'name' => 'variables',
			'init' => 'variables',
			'created' => 'timestamp',
		);

		$ncols = 0;
		foreach (array_keys($wdkeynames) as $col2update) {
			// foreach (array('mail') as $col2update) {
			$allfields = array(
				'wid',
				$col2update,
				'inwatchdog',
				$extracol[$col2update],	// Either "variables" or "timestamp"
			);

			$result = db_select(self::DB_NAME, $aliasout)
				->fields($aliasout, $allfields)
				->condition('inwatchdog', TRUE)
				->condition($col2update, $nullvalues[$col2update])
				->execute();

			foreach ($result as $eachres) {
				switch ($col2update) {
				case 'created':
					$wdval = $eachres->{$wdkeynames[$col2update]} + 1;
					// 1 second after 'timestamp' seems a norm for 'created'.
					break;
				default:
					$a = unserialize($eachres->variables);	// Binary BLOB data.
					$wdval = $a[$wdkeynames[$col2update]];
				}

				$num_updated = db_update(self::DB_NAME)
					->fields(array( $col2update => $wdval ))
					->condition('wid', $eachres->wid, '=')
					->execute();

				if ($num_updated !== 1) {
					$errmsg = sprintf(
						'Row (UID=%d, WID=%d, name=(%s)) failed to be updated to (%s) for Column=(%s) based on watchdog (num_updated=%d).',
						$eachres->uid,
						$eachres->wid,
						$eachres->name,
						$wdval,
						$col2update,
						$num_updated
					);
					drupal_set_message($errmsg . '.', 'error');
				}
				else {
					$ncols++;
				}	// if ($num_updated !== 1) {
			}	// foreach ($result as $eachres) {
		}	// foreach (array('name', 'mail') as $col2update) {

		return $ncols;
	}	// public static function update_db_users_update_with_wd() {


  /**
   * Initialise Database table logger_user_users
   *
   * Algorithm is,
   *   1. UPDATE (existing rows, reflecting the newest parent tables).
   *   2. Finds change in the column "created", if any (should not happen!).
   *   3. INSERT IGNORE INTO
   *   4. UPDATE for users_roles and watchdog
   *   5. Updates inusers and inwatchdog to FALSE, if that is the case.
   *
   * @return array
	 *   An associative array ($hsnrow) containing:
	 *     - status: Boolean.  True if successful.
	 *     - initial: Number of rows before the update.
	 *     - update: An associative array containing (u|r|w).
	 *         Number of rows simply updated (not including those added).
	 *     - created_changed: array (u|r|w). Number of rows, where the column
	 *         "created" (time) differ between logger_user_users and users.
	 *         In general, "created" should never change.
	 *         Hence if this is non-zero, either some manual operation
	 *         has been performed, or something went very wrong.
	 *     - insert: array (u|r|w). Number of rows inserted by updated tables.
	 *         If ['w'] is non-zero, that means the account information is
	 *         not found on users, presumably because the user account(s)
	 *         that were created after the last run have been already cancelled.
	 *     - insert-update: array (r|w). Number of rows updated by users_roles
	 *         or watchdog for the rows inserted by users.
	 *         In general, ['w'] of this should agree with ['insert']['u'].
	 *     - disappeared: Number of rows disappeared from users/watchdog.
	 *     - end: Resultant current number of rows.
   */
  public static function update_db_users() {
    $aliasout = self::tablealiases('self')[0];

		// Initializes the returned variable: $hsnrow
    $hsnrow = array('status' => FALSE);

    $hsnrow['initial'] = db_select(self::DB_NAME, $aliasout)
      ->fields($aliasout, array('uid'))
      ->execute()
      ->rowCount();

		$ary = array('update', 'insert', 'insert-update', 'disappeared');
		foreach ($ary as $knrow) {
			$hsnrow[$knrow] = array();	// Redundant statment.
			foreach (self::tablealiases('update_db') as $aliasin) {
				$hsnrow[$knrow][$aliasin] = 0;
			}
		}

		// 1. UPDATE the database table logger_user_users first.
    if ($hsnrow['initial'] > 0) {
			// Not fired in the very first run after the module is enabled.
			$hsnrow['update'] =
				self::update_db_users_update(self::tablealiases('update_db'));
    }


    // 2. Finds change in CREATED, if there is any (which should not happen).
    $query = db_select(self::DB_NAME, $aliasout)
      ->fields($aliasout, array('uid', 'created', 'name'))
      ->fields('u',       array('uid', 'created', 'name'));
		$query->leftJoin('users', 'u', $aliasout . '.uid = u.uid');
    // $query->condition($aliasout . '.created', 'u.created', '<>');
    $query->condition($aliasout . '.created', 0, '>');
		$q = sprintf('%s.created <> u.created AND l.created > 0', $aliasout);
    $query->where($q);
		$result = $query->execute();

    $hsnrow['created_changed'] = $result->rowCount();

		if ($hsnrow['created_changed'] > 0) {
			$errmsg = sprintf(
				"'created' in the following rows have changed in Table users! They are not updated in Table (%s).\n",
				self::DB_NAME
			);
			foreach ($result as $er) {
				$errmsg .= sprintf(
					'<br />users.name="%s" (UID=%d) created=(%s => %s)',
					$er->u_name,	// The table alias connected with "_"
					$er->u_uid,
					format_date($er->created,   'medium'),
					format_date($er->u_created, 'medium')
				);
			}
			drupal_set_message($errmsg . '.', 'warning');
		}
    // NOTE: SQL statement to find out the rows, in which created has changed:
    // mysql> SELECT l.uid AS uid, l.created AS created, l.name AS name, u.uid AS u_uid, u.created AS u_created, u.name AS u_name FROM mydrupal_logger_user_users l LEFT OUTER JOIN mydrupal_users u ON l.uid = u.uid WHERE (l.created <> u.created) AND (l.created > 0);
    // Change the table prefix ('mydrupal_') in the above.


		// 3. INSERT IGNORE INTO
		$hsnrow['insert'] =
			self::update_db_users_insert(self::tablealiases('update_db'));


    // 4. UPDATE for users_roles and watchdog
		$hsnrow['insert-update'] =
			self::update_db_users_update(array('ur', 'w'));


    // 5. Updates inusers (& status) and inwatchdog to FALSE, if it is the case.
    //   All the rows that have been detected in users and watchdog should
    //   be assigned with the right flag (TRUE) above.
    //   However, if a record has disappeared from inusers and/or inwatchdog
    //   after the last recording of this module, they remain TRUE;
    //   those values should be set to FALSE.

		$hscol2examine = array(
			'u' => 'uid',
			'w' => 'wid',
		);

		foreach (array('u', 'w') as $aliasin) {
			$dbin = self::tablename($aliasin);

			$colextra = self::columnextra($aliasin);
			$ar = array_map(
				function($p) use ($aliasout) {
					return sprintf(
						'%s.%s = FALSE', 
						$aliasout, 
						$p
					);
				},
				$colextra
			);
			$stset = implode(', ',$ar);

			$query = sprintf(
				// 'UPDATE {%s} AS %s SET %s.%s = FALSE WHERE %s.%s > 0 AND NOT EXISTS (SELECT * FROM {%s} AS %s WHERE %s.%s = %s.%s);',
				'UPDATE {%s} AS %s SET %s WHERE %s.%s > 0 AND NOT EXISTS (SELECT * FROM {%s} AS %s WHERE %s.%s = %s.%s);',
				self::DB_NAME,
				$aliasout,	// self::tablealiases('self')[0],
				$stset,	    // self::tablealiases('self')[0],
				// self::columnextra($aliasin),
				$aliasout,	// self::tablealiases('self')[0],
				$hscol2examine[$aliasin],
				self::tablename($aliasin),
				$aliasin,
				$aliasin,
				$hscol2examine[$aliasin],
				$aliasout,	// self::tablealiases('self')[0],
				$hscol2examine[$aliasin]
			);
			// e.g.,
			// UPDATE sc_logger_user_users AS l SET l.inusers = 0
			//  WHERE l.uid > 0 AND
			//        NOT EXISTS (SELECT * FROM sc_users AS u WHERE u.uid = l.uid);

      $result = db_query($query);
			$hsnrow['disappeared'][$aliasin] = $result->rowCount();

		}	// foreach (array('u', 'w') as $aliasin) {


    $hsnrow['end'] = db_select(self::DB_NAME, $aliasout)
      ->fields($aliasout, array('uid'))
      ->execute()
      ->rowCount();

    $hsnrow['status'] = TRUE;
		// Database successfully updated.

		// Sets the variable 'logger_user_status' (registered in Drupal)
		// @see logger_user.install
		$gstatus = variable_get(self::VARNAME_STATUS);
		$gstatus['timeupdated'] = time();
		variable_set(self::VARNAME_STATUS, $gstatus);

		return $hsnrow;
  }	// public static function update_db_users() {


  /**
   * Construct the notice/warning message based on the affected rows.
	 *
	 * @param array $hsnrow
	 *   An associative array; for detail,
   *   @see function update_db_users()
	 * @param array $opts
	 *   An optional associative array (or NULL) containing:
	 *   - purpose: string (watchdog|message(Default))
	 *   - dryrun: boolean (Default:FALSE), to actually write to watchdog.
   * @return array
	 *   Array of an associative array containing:
	 *   - severity: WATCHDOG_NOTICE(default)|WATCHDOG_WARNING|WATCHDOG_ERROR
	 *   - message: Notice/Warning message.  It is empty if there is no issue.
	 */
  public static function message_update_db_users($hsnrow, $opts=NULL) {
		$opts_def = array(
			'purpose' => 'message',	// Default
			'dryrun' => FALSE,
		);
		if ($opts) {
			$opts = array_merge($opts_def, $opts);
		} else {
			$opts = $opts_def;
		}

		if ($opts['purpose'] !== 'watchdog') {
			// watchdog() is called only when ['purpose']=='watchdog' and NOT dryrun.
			$opts['dryrun'] = TRUE;
		}

		$arret = array();	// Array of an associated array: keys (severity|message).

		$msgen = '';
		if (! $hsnrow['status']) {
			$msgen = 'Update failed.  Hash of the number of affected rows: @data';
			$vars = array('@data' => var_export($hsnrow, TRUE));
			$lastindex = count($arret) - 1;
			$arret[$lastindex]['severity'] = WATCHDOG_ERROR;
			$arret[$lastindex]['message']  = t($msgen, $vars);
		}
		else {

			if ($hsnrow['created_changed'] > 0) {
				$msgen = 'Column "created" in Table users had changed in @created row(s).';
				$vars = array('@created' => sprintf('%d', $hsnrow['created_changed']));
				$lastindex = count($arret) - 1;
				$arret[$lastindex]['severity'] = WATCHDOG_WARNING;
				$arret[$lastindex]['message']  = t($msgen, $vars);
			}

			// if ($hsnrow['initial'] === $hsnrow['end']) {
			// 	// No change happened.
			// }
			// else {

				$armsg = array();
				$vars = array();
				$ntotal = 0;
				// The total number of rows that have changed.
				// If the same row is inserted and updated, then it can be +2 or more.
				// This variable is used to judge simply if any row is updated.

				$gstatus = variable_get(self::VARNAME_STATUS);
				$ntotal += $gstatus['nnewusers'];

//drupal_set_message('DEBUGms: gstatus=' . var_export($gstatus, TRUE), 'warning');
//drupal_set_message('DEBUGmsg: hsnrow=' . var_export($hsnrow, TRUE), 'warning');
				$tmpmsg =
					'Database table (@tbl) updated with +@diff new rows (@last => @end) ';
				if ($gstatus['lastaccessed']) {
					$tmpmsg .= 'since the last explicit update at @lasttime.  Details: ';
					$vars['@lasttime'] = format_date($gstatus['lastaccessed']);
				} else {
					$tmpmsg .= 'as the first update.  Details: ';
				}

				$armsg[] = $tmpmsg;
				$vars['@tbl'] = self::DB_NAME;
				$nrowslast = $hsnrow['initial'] - $gstatus['nnewusers'];
				$vars['@last'] = sprintf('%d', $nrowslast);
				$vars['@end']  = sprintf('%d', $hsnrow['end']);
				$ndiff = $hsnrow['end'] - $nrowslast;
				$ntotal += $ndiff;
				$vars['@diff'] = sprintf('%d', $ndiff);
				// Now, ($vars['@initial'] == $vars['@end']) should be the case,
				// except for the initial run, providing hook_user_insert() worked.

				$ary = array('update', 'insert', 'insert-update', 'disappeared');
				foreach ($ary as $knrow) {
					$armsgtmp = array();
					$arvartmp = array();

					foreach ($hsnrow[$knrow] as $alias => $nrow) {
					  //      eg., ['update']    'w'   =>  7 // rows updated for watchdog
						if ($nrow > 0) {
							$ph = '@' . $knrow . $alias;	// Place Holder
							$armsgtmp[] = sprintf('%s(%s)', self::tablename($alias), $ph); 
							// e.g., 'watchdog(@updatew)'
							$arvartmp[$ph] = sprintf('%d', $nrow);
							$ntotal += $nrow;
						}
					}
					if (!empty($arvartmp)) {
						$armsg[] = sprintf(
							' %s: %s ',
							preg_replace('/(ed?)?$/', 'ed', ucfirst($knrow), 1),
							implode(", ", $armsgtmp)
						);	// e.g., ' Updated: users(@UpdateU), watchdog(@UpdateW)] '
						$vars = array_merge($vars, $arvartmp);
					}
				}

				if ($ntotal > 0) {
					$lastindex = count($arret) - 1;
					$arret[$lastindex]['severity'] = WATCHDOG_NOTICE;
					$arret[$lastindex]['message'] = t(implode("\n", $armsg), $vars);
				}
			// }

		}	// if (! $hsnrow['status']) {  --  else

		// Run watchdog() if specified so.
		if (! $opts['dryrun']) {
			foreach ($arret as $eachwd) {
				$message  = $eachwd['message'];
				$severity = $eachwd['severity'];
				watchdog(self::WATCHDOG_TYPE, $message, NULL, $severity);
			}
		}

		// Updates (resets) the registered status variable.
		// @see logger_user.install
		$gstatus['lastaccessed'] = $gstatus['timeupdated'];
		// $gstatus['timeupdated']; // unchanged.
		$gstatus['nnewusers'] = 0;
		// $gstatus['nrows'] = array();	// not used.
		variable_set(self::VARNAME_STATUS, $gstatus);

		return $arret;
	}	// public static function message_update_db_users($hsnrow, $opts=NULL) {

}	// class LoggerUser {


