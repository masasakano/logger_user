<?php
/**
 * @file
 * Provides the functions for the advanced language switcher.
 */

/**
 * Implements hook_help().
 *
 * Displays help and module information.
 *
 * @param path
 *   Which path of the site we're using to display help
 * @param arg
 *   Array that holds the current path as returned from arg() function
 */
function logger_user_help($path, $arg) {
  switch ($path) {
	case "admin/help#logger_user":
		return '<p>' . t("Display security information of user registration.") . '</p>';
		break;

	case "admin/reports/logger_user":
		return '<p>' . t("Logger User module keeps the user registration information, even after those user accounts are cancelled and/or watchdog log is expired, and displays them on request.") . '</p>';
		break;
  }
} 

/**
 * Implements hook_user_login(). 
 *
 * For testing:
 *  http://befused.com/drupal/admin-interface
 */
/*
function logger_user_user_login(&$edit, $account) {
  // $edit Form values submitted by the user on the user log in form
  // $account The user object for the user that is logging in
  if ($account->uid == 0) {	// If admin
    // drupal_set_message('Thank you for logging in as an admin and welcome!');
    $message = variable_get('logger_user_message', '');
    drupal_set_message(check_plain($message));	# Make sure it is a plain text!
    // drupal_set_message(check_plain(t($message)));
  }
}
*/

/**
 * Implements hook_menu().
 *
 * In this case, add the line
 *    configure = admin/config/people/logger_user
 * in *.info file to enable the "configure" button in the Module page.
 * @see https://www.drupal.org/node/1111212
 */
function logger_user_menu() {
  $items['admin/reports/logger_user'] = array(
    'title' => 'User log by logger_user',
    'description' => 'View the record of registration of users.',
    'page callback' => 'logger_user_overview',
    'access arguments' => array('administer users'),
    'file' => 'logger_user.admin.inc',
    // 'page callback' => 'drupal_get_form',
    // 'page arguments' => array('logger_user_form'),
    // 'type' => MENU_NORMAL_ITEM,
  );
 
  return $items;
}

/**
 * Implements hook_init().
 */
function logger_user_init() {
  if (arg(0) == 'admin' && arg(1) == 'reports') {
    // Add the CSS for this module
    drupal_add_css(drupal_get_path('module', 'dblog') . '/dblog.css');
  }
}

/**
 * Implements hook_cron().
 *
 * Update
 */
/*
function logger_user_cron() {
}
*/


/**
 * Implements hook_theme().
 */
function logger_user_theme() {
  return array(
    'logger_user_message' => array(
      'variables' => array('event' => NULL, 'link' => FALSE),
      'file' => 'logger_user.admin.inc',
    ),
  );
}

/**
 * Implements hook_form().
 * Admin form to configurable logger_user message.
 * The data are stored as 'logger_user_message' in Table 'variable'.
 */
/*
function logger_user_form($form, &$form_state) {
  $form['logger_user_message'] = array(
    '#type' => 'textarea',	// textfield|select
    '#title' => t('Logger_User message'),
    '#rows' => 5,
    '#required' => FALSE,
    '#default_value' => variable_get('logger_user_message', ''),	// The second arg is the default value if the variable 'logger_user_message' does not exist.  This line makes sure the user can reuse the previously submitted value if they want to modify and resubmit it.
    // '#value_callback' => 'logger_user_changedef_value_callback',	// See below
    '#element_validate' => array('logger_user_msgtextarea_validate'),
  );
 
  return system_settings_form($form);
}
*/

/**
 * Implements logger_user_changedef_value_callback().
 * 
 * Called before the variable is presented to the user.
 * Useful when the data a user to edit is different from that stored in the database.
 * @see https://benclark.com/articles/how-to-unlock-the-power-of-system_settings_form.html
 */
/*
function logger_user_changedef_value_callback($element, $edit = FALSE) {
  if (func_num_args() == 1) {
    // No value yet, so use #default_value if available.
    if (isset($element['#default_value']) && is_numeric($element['#default_value'])) {
      // Convert internal value to user-friendly value.
      $value = _logger_user_changedef_transform_from_internal($element['#default_value']);
      // Return the transformed value to the form.
      return $value;
    }
    // If the #default_value is blank, return empty string here.
    return '';
  }
  // For other calls to the value_callback, return the second parameter.
  return $edit;
}
*/


/**
 * Implements logger_user_changedef_value_callback().
 * 
 * Called before the variable is presented to the user.
 * Useful when the data a user to edit is different from that stored in the database.
 * @see https://benclark.com/articles/how-to-unlock-the-power-of-system_settings_form.html
 * @see http://befused.com/drupal/element-validate
 */
/*
function logger_user_colour_validate($element, $form_state) {
  if (!empty($element['#value'])) {
    // Check that the user-friendly value is valid (optional).
    $clean_msg = $check_plain($form_state['values']['logger_user_message']);	# $element['#value'] may be OK, instead??
    // Or, maybe do I need $form_state['values']['logger_user_message']['value']??
    if ($clean_msg != $form_state['values']['logger_user_message']) {
      // // Failed validation, return form error.
      // form_error($element, t('Variable was not valid.'));
      //   Or(?),  form_set_error('logger_user_message', t('You must enter a valid text.'));
      drupal_set_message(t('Message is converted to plain text.'));
    }

    // If passed validation, convert user-friendly value to internal value.
    // Set the value on the form.
    // form_set_value($element, $value, $form_state);
  }
}
*/


/**
 * Implements hook_block_info().
 */
/*
function logger_user_block_info() {
  $blocks['logger_user'] = array(
    // The name that will appear in the block list.
    'info' => t('Language Switcher (Adv)'),
    // Default setting.
    'cache' => DRUPAL_CACHE_PER_ROLE,
  );
  return $blocks;
}
*/


/**
 * Implements hook_block_view().
 *
 * Prepares the contents of the block.
 */
/*
function logger_user_block_view($delta = '') {
  // @see https://www.drupal.org/node/1104498
  switch ($delta) {
    case 'logger_user':
      $block['subject'] = t('Languages');
      $block['content'] = _langswitcheradvanced_list(_langswitcheradvanced_core());
    return $block;
  }
}
*/

