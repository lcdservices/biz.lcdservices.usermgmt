<?php

use CRM_Usermgmt_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Usermgmt_Form_UserManagement extends CRM_Core_Form {
  /**
   * The contact id, used when editing the form
   *
   * @var int
   */
   
  public $_contactId;
  /**
   * Contact.display_name of contact for whom we are adding user
   *
   * @var int
   */
  public $_displayName;
  
  /**
   * Build all the data structures needed to build the form.
   */
  public function preProcess() {
    $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    $this->set('contactId', $this->_contactId);
    $this->assign('contactId', $this->_contactId);
    
    $params = $defaults = $ids = array();
    $params['id'] = $params['contact_id'] = $this->_contactId;
    $contact = CRM_Contact_BAO_Contact::retrieve($params, $defaults, $ids);
    $this->_displayName = $contact->display_name;
  }
  
   /**
   * Build the form object.
   */
  public function buildQuickForm() {
    // add form elements
    $element = $this->add('text', 'ContactID', ts('Contact ID'), array('class' => 'huge'));
    $element->freeze();
    
    $element = $this->add('text', 'name', ts('Contact Name'), array('class' => 'huge'));
    $element->freeze();
    //$this->add('hidden', 'ContactID', ts(''));
    $this->addEntityRef('user_lists', ts('Select User'), array( 'entity' => 'userlist', 'placeholder' => ts('- Select User -'), ));
    
    $cid = $this->_contactId;
    $uf_id = CRM_Core_BAO_UFMatch::getUFId($cid);
    if($uf_id){
      $element = $this->add('text', 'UserID', ts('User ID'), array('class' => 'huge'));
      $element->freeze();
      
      $element = $this->add('text', 'uname', ts('User Name'), array('class' => 'huge'));
      $element->freeze();
      
      $element = $this->add('text', 'email', ts('Email'), array('class' => 'huge'));
      $element->freeze();
    }
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }
  
 /**
   * Do the set default related to location type id, primary location,  default country.
   *
   * @param array $defaults
   */
  public function setDefaultValues() {
    $defaults = array();
    $defaults['name'] = $this->_displayName;
    
    $cid = $this->_contactId;
    $defaults['ContactID'] = $cid;
    $uf_id = CRM_Core_BAO_UFMatch::getUFId($cid);
    $defaults['user_lists'] = $uf_id;
    if($uf_id){
      $result = civicrm_api3('User', 'get', array('sequential' => 1, 'id' => $uf_id, ));
      foreach( $result['values'] as $values){
        $defaults['UserID'] = $uf_id;
        $defaults['uname'] = CRM_Utils_Array::value('name', $values);
        $defaults['email'] = CRM_Utils_Array::value('email', $values);
      }
      
    }
    
    return $defaults;
  }
  
   /* add the rules (mainly global rules) for form.
   * All local rules are added near the element
   *
   * @see valid_date
   */
  public function addRules() {
    $this->addFormRule(array('CRM_Usermgmt_Form_UserManagement', 'formRule'), $this);
  }
  
  /**
   * Global validation rules for the form.
   *
   * @param array $fields
   *   Posted values of the form.
   * @param array $errors
   *   List of errors to be posted back to the form.
   * @param int $contactId
   *   Contact id if doing update.
   *
   * @return bool
   *   email/openId
   */
  public static function formRule($fields) {
     $errors = array();
    $ContactID = CRM_Utils_Array::value('ContactID', $fields);
    $contact_list = CRM_Utils_Array::value('user_lists', $fields);
    if( !empty($contact_list) ){
      try{
        $result = civicrm_api3('UFMatch', 'get', array('uf_id' => $contact_list,));
      }
      catch (CiviCRM_API3_Exception $e) {
        // Handle error here.
        $errorMessage = $e->getMessage();
        $errorCode = $e->getErrorCode();
        $errorData = $e->getExtraParams();
        return array(
          'is_error' => 1,
          'error_message' => $errorMessage,
          'error_code' => $errorCode,
          'error_data' => $errorData,
        );
      }
      
      $count = CRM_Utils_Array::value('count', $result);
      $id = CRM_Utils_Array::value('id', $result);
     
      if( $count == 1 ){
        foreach( $result['values'] as $values){
          $uf_contactID = CRM_Utils_Array::value('contact_id', $result);
        }
        if( $ContactID != $uf_contactID ){
          $errors['contact_list'] = ts('This contact is already connected with another user');
        }
      }
    }
    return empty($errors) ? TRUE : $errors;
  }

   /**
   * Process the form when submitted.
   */
  public function postProcess() {
    $values = $this->exportValues();
        
    $ContactID = CRM_Utils_Array::value('ContactID', $values);
    $contact_list = CRM_Utils_Array::value('user_lists', $values);
    
    
    if( !empty($contact_list) ){
      //get uf_name
      $uf_name = '';
      $result = civicrm_api3('Contact', 'get', array(
        'sequential' => 1,
        'return' => array("email"),
        'id' => $ContactID,
      ));
      
      if( !empty($result['values']) ){
        foreach( $result['values'] as $values){
          $uf_name = CRM_Utils_Array::value('email', $values);
        }
      }
      //set uf_match for the contact      
      $params = array(
        'uf_id' => $contact_list,
        'uf_name' => $uf_name,
        'contact_id' => $ContactID,
      );    
      try{
        $result = civicrm_api3('UFMatch', 'Create', $params);
      }
      catch (CiviCRM_API3_Exception $e) {
        // Handle error here.
        $errorMessage = $e->getMessage();
        $errorCode = $e->getErrorCode();
        $errorData = $e->getExtraParams();
        return array(
          'is_error' => 1,
          'error_message' => $errorMessage,
          'error_code' => $errorCode,
          'error_data' => $errorData,
        );
      }
      $status = ts('User Connection Updated for the contact');
    }
    //delete uf_match if unselected
    else{
      try{
        $result = civicrm_api3('UFMatch', 'get', array('contact_id' => $ContactID,));
      }
      catch (CiviCRM_API3_Exception $e) {
        // Handle error here.
        $errorMessage = $e->getMessage();
        $errorCode = $e->getErrorCode();
        $errorData = $e->getExtraParams();
        return array(
          'is_error' => 1,
          'error_message' => $errorMessage,
          'error_code' => $errorCode,
          'error_data' => $errorData,
        );
      }
      $uf_id = CRM_Utils_Array::value('id', $result);
      if( !empty($uf_id) ){
        $result = civicrm_api3('UFMatch', 'delete', array('id' =>  $uf_id,));
        $status = ts('User Connection deleted for the contact');
      } 
    }
    CRM_Core_Session::setStatus($status, ts('User Connection'), 'success');
    CRM_Core_Session::singleton()->pushUserContext($this->refreshURL);
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
