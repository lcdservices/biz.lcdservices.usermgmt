<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * This api exposes CiviCRM the user framework user account.
 *
 * @package CiviCRM_APIv3
 */

/**
 * Get details about the CMS Userlist entity.
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_userlist_get($params) {
  $options = _civicrm_api3_get_options_from_params($params, TRUE);
  $inputParams = CRM_Utils_Array::value('input_params', $options, array());
  $newParams = CRM_Contact_BAO_Query::convertFormValues($inputParams);
  $result = array();
  global $civicrm_root;
  $config = CRM_Core_Config::singleton();
  if ($config->userSystem->is_drupal) {
    $query = db_select('users', 'u')->fields('u', array('uid', 'name', 'mail'));
    if( !empty($inputParams) ){
      $db_or = db_or();
      foreach ($inputParams as $field=>$value) {
        if($field == '_id'){
          $column = 'u.uid';
        }
        else{
          $column = 'u.'.$field;
        }
        if( is_array($value) ){
          foreach ($value as $opkey=>$opvalue) {
            if($field == 'name'){
              $db_or->condition('u.mail', $opvalue, $opkey);
            }
            $db_or->condition($column, $opvalue, $opkey);
          }
        }
        else{
           $db_or->condition($column, $value);
        }
        
      }
      $query->condition($db_or);
    }  
    $users = $query->execute()->fetchAll();
    foreach($users as $key=>$user_list) {
      if( !empty($user_list->uid) ) {
        $result[$user_list->uid] = array('id'=>$user_list->uid, 'name'=> $user_list->name, 'email'=> $user_list->mail );
      }
    }
  }
  elseif ($config->userFramework == 'WordPress') {
    global $wpdb;
    $clause = array();
    if( !empty($inputParams) ){
      foreach ($inputParams as $field=>$value) {
        if($field == '_id'){
          $column = 'ID';
        }
       if($field == 'name'){
          $column = 'user_login';
        }
        if( is_array($value) ){
          foreach ($value as $opkey=>$opvalue) {
            if($field == 'name'){
              $clause[] = "user_email $opkey '$opvalue'";
            }
            if($opkey == 'IN'){
              $opstringvalue = implode(',', $opvalue);
              $clause[] = "$column $opkey ($opstringvalue)";
            }
            else{
              $clause[] = "$column $opkey '$opvalue'";
            }
          }
        }
        else{
          $clause[] = "$column = '$value'";
        }
      }
    }
    $whereClause = !empty($clause) ? implode(' OR ', $clause) : '(1)';
    $sql = "SELECT $wpdb->users.ID FROM $wpdb->users WHERE $whereClause";
    $wpUserIds = $wpdb->get_col($sql);
    foreach ($wpUserIds as $wpUserId) {
      $wpUserData = get_userdata($wpUserId);
      $result[$wpUserData->ID] = array('id'=>$wpUserData->ID, 'name'=> $wpUserData->user_login, 'email'=> $wpUserData->user_email );
    }
    
  }
  elseif ($config->userFramework == 'Joomla') {
  }
  
  return civicrm_api3_create_success($result, $params, 'userlist', 'get');
}

/**
 * Adjust Metadata for Get action.
 *
 * The metadata is used for setting defaults, documentation & validation.
 *
 * @param array $params
 *   Array of parameters determined by getfields.
 */
function _civicrm_api3_userlist_get_spec(&$params) {
  // At this stage contact-id is required - we may be able to loosen this.
  $params['contact_id'] = array(
    'title' => 'Contact ID',
    'type' => CRM_Utils_Type::T_INT,
  );
  $params['id'] = array(
    'title' => 'CMS User ID',
    'type' => CRM_Utils_Type::T_INT,
  );
  $params['name'] = array(
    'title' => 'Username',
    'type' => CRM_Utils_Type::T_STRING,
  );
  $params['email'] = array(
    'title' => 'Email',
    'type' => CRM_Utils_Type::T_STRING,
  );
}
