<?php

/**
 * @file
 * Administrative page callbacks for the Logger_User module.
 * 
 * Referring largely to dblog module.
 */

global $language;
//define('LOGGER_USER_USE_OWN_DB', FALSE);	// Not use our database to display.
define('LOGGER_USER_USE_OWN_DB', TRUE);
define('LOGGER_USER_DEFAULT_N_ENTRIES', 50);	// Default number of entries per page.
define('LOGGER_USER_DEFAULT_FMT', 'standard');	// Default format.

$is_found = FALSE;
foreach (array('', '.php') as $suffix) {
	$f = sprintf(
		'%s/%s/%s',
		DRUPAL_ROOT,
		drupal_get_path('module', 'logger_user'),
		'logger_user.common.inc' . $suffix
	);
	if (file_exists($f)) {
		require_once $f;
		$is_found = TRUE;
		break;
	}
}

if (! $is_found) {
	drupal_set_message('logger_user.common.inc is not found.', 'error');
	return FALSE;
}


/**
 * Page callback: Displays a listing of database log messages.
 *
 * @see logger_user_filter_form()
 * @see logger_user_menu()
 *
 * @return array
 *   An associative array containing:
 *   - logger_user_table:
 *   - logger_user_pager:
 *   - logger_user_filter_form:
 */
function logger_user_overview() {
  $n_limit = LOGGER_USER_DEFAULT_N_ENTRIES;	// Default

  $filter = logger_user_build_filter_query();
  if (empty($filter['columnsdisplayed'])) {
		// Not specified, so default is set.
    $filter['columnsdisplayed'] = LOGGER_USER_DEFAULT_FMT;
  }

	$hsnrow = LoggerUser::update_db_users();
  $arprint = LoggerUser::message_update_db_users(
		$hsnrow,
		//array('purpose' => 'message')	// No log in watchdog, but displays.
		array('purpose' => 'watchdog')	// Leaves the log in watchdog, too.
	);
	foreach ($arprint as $eachh) {
		$eachh['message'] = preg_replace('/\n/', '<br />', $eachh['message']);
		$ty = LoggerUser::get_drupal_message_type($eachh['severity']);
		drupal_set_message($eachh['message'], $ty);
	}

//drupal_set_message(var_export($filter, TRUE), 'status');
  $rows = array();
  $classes = array(
    WATCHDOG_INFO      => 'logger_user-info',	// For CSS
    // WATCHDOG_INFO      => 'dblog-info',
  );

  $build['logger_user_filter_form'] = drupal_get_form('logger_user_filter_form');
  // $build['logger_user_clear_log_form'] = drupal_get_form('logger_user_clear_log_form');

//	$allfields = LoggerUser::select_columns(
//		$filter['columnsdisplayed'],
//		'colnames',
//		array('use_own_db' => TRUE)
//	);
//drupal_set_message('238column=: '.var_export($allfields, TRUE), 'status');	// DEBUG
	$allfields = LoggerUser::select_columns(
		$filter['columnsdisplayed'],
		'colnames',
		array(
			'tbls' => array('u', 'w', 'ur', 'r', 'l'),
			'use_own_db' => LOGGER_USER_USE_OWN_DB,
		)
	);
	// $allfields = LoggerUser::select_columns($filter['columnsdisplayed'], 'colnames');
//drupal_set_message('allfields=: '.var_export($allfields, TRUE), 'status');	// DEBUG
  // $header = _logger_user_select_columns($filter['columnsdisplayed'], 'header');
  // $header = LoggerUser::select_columns($filter['columnsdisplayed'], 'header');
  // $header = LoggerUser::select_columns(
	// 	$filter['columnsdisplayed'],
	// 	'header',
	// 	array('use_own_db' => TRUE)
	// );
// drupal_set_message('239header=: '.var_export($header, TRUE), 'status');	// DEBUG
  $header = LoggerUser::select_columns(
		$filter['columnsdisplayed'],
		'header',
		array(
			'tbls' => array('u', 'w', 'ur', 'r', 'l'),
			'use_own_db' => LOGGER_USER_USE_OWN_DB,
		)
	);
//drupal_set_message('240header=: '.var_export($header, TRUE), 'status');	// DEBUG

	if (LOGGER_USER_USE_OWN_DB) {
		$aliasout = LoggerUser::tablealiases('self')[0];	// 'l'
    $query = db_select(LoggerUser::DB_NAME, $aliasout)
			->extend('PagerDefault')
			->extend('TableSort');
		$query->leftJoin('role', 'r', $aliasout . '.rid = r.rid');

	}
	else {

  $query = db_select('watchdog', 'w')->extend('PagerDefault')->extend('TableSort');
  $query->rightJoin('users', 'u', "SUBSTRING(w.link FROM POSITION('user/' IN w.link)+5 FOR POSITION('/edit' IN w.link)-POSITION('user/' IN w.link)-5) = u.uid");
  $query->leftJoin('users_roles', 'ur', "u.uid = ur.uid");
  $query->leftJoin('role', 'r', "ur.rid = r.rid");
	}
  foreach ($allfields as $key => $eacha) {
    if ('alias' === $key) {
      continue;
    }
    else {
      $query->fields($key, $eacha);	// Can not specify the alias.
    }
  }
  // $query->fields('u', array('uid', 'name', 'mail', 'created', 'access', 'login', 'status', 'timezone', 'language', 'init'));	// Can not specify the alias.
  // $query->fields('w', array('wid', 'timestamp', 'hostname', 'message', 'link'));	// Can not specify the alias.
  foreach ($allfields['alias'] as $key_in_db => $allary) {
    foreach ($allary as $name_in_db => $alias) {
      $query->addField($key_in_db, $name_in_db, $alias);
   // $query->addField('r', 'name', 'rolename');	// Name crash with u.name
    }
  }
	if (LOGGER_USER_USE_OWN_DB) {
	}
	else {
  $query
    ->condition('u.uid', '0', '>') 
    ->condition('w.type', 'user') 
    ->where("SUBSTRING(w.message FROM 1 FOR 8) = 'New user'");
    // ->condition('SUBSTRING(w.message FROM 1 FOR 8)', 'New user');	// => Error!
    // ->where("LEFT(n.nid, :len) = :str", array(':len' => strlen($string), ':str' => $string);	// Example use of the place holder.

//   $result = db_query(<<<EOD
//     SELECT w.type,u.name,u.mail,u.created,w.timestamp,w.link,u.uid,ur.rid,r.name
//      FROM {watchdog} w
//      LEFT JOIN {users} u
//       ON SUBSTRING(w.link FROM POSITION('user/' IN w.link)+5 FOR POSITION('/edit' IN w.link)-POSITION('user/' IN w.link)-5) = u.uid 
//      LEFT JOIN {users_roles} ur ON u.uid = ur.uid
//      LEFT JOIN {role} r ON ur.rid = r.rid
//      WHERE w.type = 'user' AND SUBSTRING(w.message FROM 1 FOR 8) = 'New user'
//     UNION
//     SELECT w.type,u.name,u.mail,u.created,w.timestamp,w.link,u.uid,ur.rid,r.name
//      FROM {watchdog} w
//      RIGHT JOIN {users} u
//       ON SUBSTRING(w.link FROM POSITION('user/' IN w.link)+5 FOR POSITION('/edit' IN w.link)-POSITION('user/' IN w.link)-5) = u.uid 
//      LEFT JOIN {users_roles} ur ON u.uid = ur.uid
//      LEFT JOIN {role} r ON ur.rid = r.rid
//      WHERE u.uid != 0
//      ORDER BY uid
//      LIMIT 3;
// EOD
//     );
	}

  if (!empty($filter['ar_condition_like_field'])) {
    foreach ($filter['ar_condition_like_field'] as $i => $val) {
      $query->condition($val, $filter['ar_condition_like_data'][$i], 'LIKE');
// drupal_set_message('Filter-condition is defined: '.var_export($filter['ar_condition_like_data'], TRUE), 'status');	// DEBUG
//$query->condition($val, $filter['ar_condition_like_data'][$i], 'LIKE');
    }
  }

  if (!empty($filter['where'])) {
// drupal_set_message('Filter is defined: '.var_export($filter['args'], TRUE), 'status');	// DEBUG
    // $query->where("r.name = :abc", array(':abc' => 'administrator'));
    // $query->where("r.name = ?", array('administrator'));
    // $query->where("r.name = 'administrator'");
    $query->where($filter['where'], $filter['args']);
//drupal_set_message('Filter is defined: query='.var_export($query, TRUE), 'status');	// DEBUG
  }

  if (!empty($filter['entriesperpage'])) {
    if ($filter['entriesperpage'] < 1) {
      drupal_set_message(sprintf('Entries=(%s). Contact the developer.', $filter['entriesperpage']), 'error');	// This must NOT happen, as it must be checked in the evaluation.
    }
    else {
      $n_limit = $filter['entriesperpage'];
    }
  }

  // $query->range(0,9)	// Standard; This puts SQL "LIMIT 9 OFFSET 0"
  $query->limit($n_limit)	// extended by 'PagerDefault', no LIMIT is set in SQL.
    ->orderByHeader($header);
// drupal_set_message('QUERY='.$query->__toString(), 'status');	// DEBUG

  ////
  // The following is taken from 
  // http://knackforge.com/blog/karalmax/drupal-7-creating-drupal-style-tables-paging-sorting-and-filter
  // to make the multiple headers sortable.  But it does not work...
  //
  // // Check if there is sorting request
  // if(isset($_GET['sort']) && isset($_GET['order'])){
  //   // Sort it Ascending or Descending?
  //   if($_GET['sort'] == 'asc')
  //     $sort = 'ASC';
  //   else
  //     $sort = 'DESC';
  //   // Which column will be sorted
  //   switch($_GET['order']){
  //   case 'Date':
  //     $order = 'w.wid';
  //     break;
  //   case 'UID':
  //     $order = 'u.uid';
  //     break;
  //   case 'Status':
  //     $order = 'u.status';
  //     break;
  //   default:
  //     $order = 'w.wid';
  //   }
  // }
  // else {
  //   // Default sort
  //   $sort = 'ASC';
  //   $order = 'w.wid';
  // }
  // 
  // $query->orderBy($order, $sort);
  //
  //// Up to here.

// drupal_set_message(var_export($query, TRUE), 'status');

  $result = $query
    ->execute();
// drupal_set_message('After execute', 'warning');


  foreach ($result as $eachres) {
    if ($eachres->login < 1) {
      $userstatus = 2;
    }
    else {
      $userstatus = $eachres->status;
    }
    // $rows[] = _logger_user_select_columns($filter['columnsdisplayed'], 'main', NULL, $eachres);
    $rows[] = LoggerUser::select_columns(
			$filter['columnsdisplayed'],
			'main',
		array(
			'tbls' => array('u', 'w', 'ur', 'r', 'l'),
			'use_own_db' => LOGGER_USER_USE_OWN_DB,
		),
			//array('tbls' => array('u', 'w', 'ur', 'r', 'l')),
			$eachres
		);
//drupal_set_message('rows2=: '.var_export($rows2, TRUE), 'status');	// DEBUG

    // $rows[] = array('data' =>
    //   array(
    //     // Cells
    //     // array('class' => 'icon'),
    //     // t($eachres->type),
    //     format_date($eachres->timestamp, 'short'),
    //     // theme('logger_user_message', array('event' => $eachres, 'link' => TRUE)),	// @see logger_user_theme() in logger_user.module
    //     theme('username', array('account' => $eachres)),
    //     // trim($eachres->name),
    //     trim($eachres->rolename),
    //     $hsuserstatus[$userstatus],
    //     trim($eachres->mail),
    //     trim($eachres->hostname),
    //     sprintf("%d", $eachres->uid),
    //     trim($eachres->language),
    //     filter_xss($eachres->link),
    //   ),
    //   // Attributes for tr	// for CSS?
    //   // 'class' => array(drupal_html_class('logger_user-' . $eachres->type), $classes[$eachres->severity]),
    // );
  }

  $build['logger_user_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#attributes' => array('id' => 'admin-logger_user'),
    '#empty' => t('No log messages available.'),
  );
  $build['logger_user_pager'] = array('#theme' => 'pager');

  return $build;
}


/**
 * Builds a query for database log administration filters based on session.
 *
 * @return array
 *   An associative array containing potentially:
 *    - entriesperpage: numeric
 *    - ar_condition_like_field:
 *    - ar_condition_like_data: A pair with the above
 *    - where:
 *    - args: A pair with the above
 *    - columnsdisplayed: string
 */
function logger_user_build_filter_query() {
  if (empty($_SESSION['logger_user_overview_filter'])) {
    return;
    // e.g., $_SESSION['logger_user_overview_filter'] == array ( 'role' => array ( '3' => t('administrator'), '4' => t('blog editor'), ), 'entriesperpage' => '10', )
  }

  $filters = logger_user_filters();
  // e.g.,  $filters['role'] == array('title' => t('Role'), 'where' => "r.name = ?",)

//drupal_set_message('In logger_user_build_filter_query()...,', 'status');
//drupal_set_message(' filter='.var_export($filters, TRUE), 'status');
//drupal_set_message(' session='.var_export($_SESSION['logger_user_overview_filter'], TRUE), 'status');
  // Build query
  $arret = array();
  $where = $args = array();
  foreach ($_SESSION['logger_user_overview_filter'] as $key => $filter) {
    switch($key){
    case 'entriesperpage':
      $arret[$key] = $filter;
      break;

    case 'columnsdisplayed':
      $arret[$key] = $filter;
      break;

    case 'mail':
      $data = trim($filter);
      if (strpos($data, ' ') !== false) {
        // Sanitising.  Not needed if the validation works.  Just to play safe.
        // @see function logger_user_filter_form_validate()
        break;
      }
      elseif (empty($data)) {
        // NOTE: If there is any other filter that uses the following, this will not work!!
        $arret['ar_condition_like_field'] = array();
        $arret['ar_condition_like_data']  = array();
        break;
      }

      if (! array_key_exists('conditions_like_field', $arret)) {
        $arret['ar_condition_like_field'] = array();
        $arret['ar_condition_like_data']  = array();
      }
      $arret['ar_condition_like_field'][] = $filters[$key]['condition_like_field'];
      $arret['ar_condition_like_data'][] =
        implode('%',
                array_map(function($p){return db_like($p);},
                          explode('%', $data)
                )
        );
//drupal_set_message('In logger_user_build_filter_query(), filter='.var_export($filter, TRUE), 'status');
      // Input('%.pl') => Output('%'.db_like('.pl'))	# Escape for LIKE.
      break;

    case 'role':
//drupal_set_message('In logger_user_build_filter_query(), filter='.var_export($filter, TRUE), 'status');
      $filter_where = array();
      $filter_args  = array();
      foreach ($filter as $keyrid => $value) {
        if ($keyrid <= 2) {
          // authenticated user (rid=2).
          // Authenticated users do not have a rid in {users_roles}.
          // Hence a special treatment is needed.
          // Anyway, all the users here are at least authenticated,
          //   which means no filter is required in terms of the roles
          //   if authenticated users are specified.
          $filter_where = array();
          $filter_args  = array();
          break;
        }
        else {
          $filter_where[] = $filters[$key]['where'];
          $filter_args[] = $keyrid;
        }
      }
      $args = array_merge($args, $filter_args);
      break;

    default:
      // Taken from dblog.admin.inc  (But I am afraid it would not work!!)
      $filter_where = array();
      foreach ($filter as $value) {
        $filter_where[] = $filters[$key]['where'];
        $args[] = $value;
      }
    }
    if (!empty($filter_where)) {
      $where[] = '(' . implode(' OR ', $filter_where) . ')';
    }	// switch($key){
  }	// foreach ($_SESSION['logger_user_overview_filter'] as $key => $filter) {
  $where = !empty($where) ? implode(' AND ', $where) : '';

  // Replace the placeholder '?' with something named.
  // Otherwise, it would not work, if more than one where() (or condition?)
  // clauses are used.
  $hsarg = array();
  foreach ($args as $i => $ec) {
    $k = sprintf(":logger_userbfq%04dpholder", $i);
    $hsarg[$k] = $ec;
    $where = preg_replace('/\?/', $k, $where, 1);
  }
//drupal_set_message('In logger_user_build_filter_query(), where='.var_export($where, TRUE), 'status');
  $arret['where'] = $where;
  $arret['args']  = $hsarg;
  // $arret['args']  = $args;

  return $arret;
}


/**
 * Gathers a list of defined database user roles.
 *
 * @return array
 *   An array of uniquely defined database user roles.
 *
 * cf. function _dblog_get_message_types() in dblog.module
 */
function logger_user_get_user_roles() {
  $types = array();

  $result = db_query('SELECT rid,name FROM {role} ORDER BY rid');
  foreach ($result as $object) {
    if ($object->rid != 1) {	// rid==1 for 'anonymous user'
      $types[$object->rid] = $object->name;
      // $types[] = $object->name;
    }
  }
  // maybe, $types = $result->fetchCol();
  // Mind you, then the contents in $types may disappear??

//drupal_set_message('In get_user_roles, $types=' . var_export($types, TRUE), 'status');
  return $types;
}

/**
 * Creates a list of database log administration filters that can be applied.
 *
 * @return array
 *   Associative array of filters. The top-level keys are used as the form
 *   element names for the filters, and the values are arrays with the following
 *   elements:
 *   - title: Title of the filter.
 *   - where: The filter condition.
 *   - condition_like_field: Which column is used for 'LIKE' condition in SQL.
 *   - options: Array of options for the select list for the filter.
 */
function logger_user_filters() {
  $filters = array();

  foreach (logger_user_get_user_roles() as $i => $type) {
    if ($i > 1) {		// rid==1 for 'anonymous user'
      $types[$i] = t($type);
    }
  }
  // Authenticated with no role??

  if (!empty($types)) {
    $filters['role'] = array(
      'title' => t('Role'),
      'where' => "r.rid = ?",
      'options' => $types,
    );
  }

  $filters['mail'] = array(
    'title' => t('Mail address'),
    'condition_like_field' => "u.mail",
    // 'condition_like_data'  => array(),
  );

  $filters['columnsdisplayed'] = array(
    'title' => t('Columns to display'),
    'options' => array(
      'concise'  => t('Concise'),
      'standard' => t('Standard'),
      'all'      => t('All'),
    ),
  );

  $filters['entriesperpage'] = array(
    'title' => t('Entries per page'),
  //   // 'where' => 'w.severity = ?',
  //   'options' => array(5,10,20,50,100,500),
  );

  return $filters;
}

/**
 * Form constructor for the database logging filter form.
 *
 * @see logger_user_filter_form_validate()
 * @see logger_user_filter_form_submit()
 * @see logger_user_overview()
 *
 * @ingroup forms
 */
function logger_user_filter_form($form) {
  $filters = logger_user_filters();

  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Filter entries'),
    '#collapsible' => TRUE,
    '#collapsed' => empty($_SESSION['logger_user_overview_filter']),
  );
  foreach ($filters as $key => $filter) {	// $key=~/role|entriesperpage/
    switch ($key) {
    case 'entriesperpage':
      $form['filters']['status'][$key] = array(
        '#title' => $filter['title'],
        '#type' => 'textfield',
        '#description' => t('Number of entries per page'),
        '#maxlength' => 10,
        '#size' => 6,
        '#default_value' => LOGGER_USER_DEFAULT_N_ENTRIES,
        // Note if "Reset" or not submitted, #default_value is NOT returned,
        // but empty is returned.
      );
      break;

    case 'mail':
//drupal_set_message('mail is visited. 076.', 'warning');
      $form['filters']['status'][$key] = array(
        '#title' => $filter['title'],
        '#type' => 'textfield',
        '#description' =>
          t('LIKE condition for email address, e.g., "%yahoo.com"'),
        '#maxlength' => 200,
        '#size' => 100,
      );
      break;

    case 'columnsdisplayed':
      $form['filters']['status'][$key] = array(
        '#title' => $filter['title'],
        '#type' => 'radios',
        '#description' => t('Columns to display.'),
        '#default_value' => LOGGER_USER_DEFAULT_FMT,
        // Note if "Reset" or not submitted, #default_value is NOT returned,
        // but empty is returned.
        '#options' => $filter['options'],
      );
      break;

    default:
      $form['filters']['status'][$key] = array(
        '#title' => $filter['title'],
        '#type' => 'select',
        '#multiple' => TRUE,
        // '#size' => 5,
        '#options' => $filter['options'],
      );
    }

    if (!empty($_SESSION['logger_user_overview_filter'][$key])) {
      $form['filters']['status'][$key]['#default_value'] = $_SESSION['logger_user_overview_filter'][$key];
      // Read the default from the session parameter.
    }

  }

  $form['filters']['actions'] = array(
    '#type' => 'actions',
    '#attributes' => array('class' => array('container-inline')),
  );
  $form['filters']['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Filter'),
  );
  if (!empty($_SESSION['logger_user_overview_filter'])) {
    $form['filters']['actions']['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset')
    );
  }
  return $form;
}

/**
 * Form validation handler for logger_user_filter_form().
 *
 * @see logger_user_filter_form_submit()
 */
function logger_user_filter_form_validate($form, &$form_state) {
//drupal_set_message('Validation starts. 022.', 'error');
  if ($form_state['values']['op'] == t('Filter')) {
//drupal_set_message('Validation starts. 025.', 'error');
    if (empty($form_state['values']['role']) && empty($form_state['values']['entriesperpage'])) {
//drupal_set_message('Validation starts. 033.', 'error');
      form_set_error('role', t('You must select something to filter by.'));
    }
    elseif ((! empty($form_state['values']['mail'])) && (preg_match('/[[:space:]\{\}\[\]\(\)\<\>!#\$\^\&\*\?\|\\\\]/u', trim($form_state['values']['mail'])))) {
      form_set_error('mail', t('Conditions for Mail must not include a whitespace or symbols.'));
    }
    elseif ((! empty($form_state['values']['entriesperpage'])) && ($form_state['values']['entriesperpage'] < 1)) {
//drupal_set_message(sprintf('Entries=(%s). Contact the developer.', $form_state['values']['entriesperpage']), 'error');
      form_set_error('entriesperpage', t('The number entries per page must be positive.'));
    }
  }
//drupal_set_message('Validation ends. 055.', 'status');
}

/**
 * Form submission handler for logger_user_filter_form().
 *
 * @see logger_user_filter_form_validate()
 */
function logger_user_filter_form_submit($form, &$form_state) {
  $op = $form_state['values']['op'];
  $filters = logger_user_filters();
  switch ($op) {
    case t('Filter'):
      foreach ($filters as $name => $filter) {
        if (isset($form_state['values'][$name])) {
          $_SESSION['logger_user_overview_filter'][$name] =
            $form_state['values'][$name];
        }
      }
      break;
    case t('Reset'):
      $_SESSION['logger_user_overview_filter'] = array();
      break;
  }
  // $form_state['rebuild'] = TRUE;
  return 'admin/reports/logger_user';
}


//   $result = db_query(<<<EOD
//     SELECT w.type,u.name,u.mail,u.created,w.timestamp,w.link,u.uid,ur.rid,r.name
//      FROM {watchdog} w
//      LEFT JOIN {users} u
//       ON SUBSTRING(w.link FROM POSITION('user/' IN w.link)+5 FOR POSITION('/edit' IN w.link)-POSITION('user/' IN w.link)-5) = u.uid 
//      LEFT JOIN {users_roles} ur ON u.uid = ur.uid
//      LEFT JOIN {role} r ON ur.rid = r.rid
//      WHERE w.type = 'user' AND SUBSTRING(w.message FROM 1 FOR 8) = 'New user'
//     UNION
//     SELECT w.type,u.name,u.mail,u.created,w.timestamp,w.link,u.uid,ur.rid,r.name
//      FROM {watchdog} w
//      RIGHT JOIN {users} u
//       ON SUBSTRING(w.link FROM POSITION('user/' IN w.link)+5 FOR POSITION('/edit' IN w.link)-POSITION('user/' IN w.link)-5) = u.uid 
//      LEFT JOIN {users_roles} ur ON u.uid = ur.uid
//      LEFT JOIN {role} r ON ur.rid = r.rid
//      WHERE u.uid != 0
//      ORDER BY uid
//      LIMIT 3;

