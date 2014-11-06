<?php
// $Id$

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
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
 | Customized and enhanced for Het Inter-lokaal                       |
 | CiviCooP <http://www.civicoop.org>                                 |
 | Erik Hommel <erik.hommel@civicoop.org>                             |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class CRM_Hilreports_Form_Report_HilFinDienst extends CRM_Report_Form {

  protected $_summary = NULL;
  protected $_relField = FALSE;
  /*
   * specific for Het Inter-lokaal (hil)
   */
  protected $_hilCaseType = NULL;
  protected $_hilCaseTypeId = NULL;
  protected $_hilExtraGegevensName = NULL;
  protected $_hilExtraGegevensGroupId = NULL;
  protected $_hilExtraGegevensTable = NULL;
  protected $_hilExtraGegevensColumns = array();
  protected $_hilCheckInkomenGroupId = NULL;
  protected $_hilCheckInkomenName = NULL;
  protected $_hilCheckInkomenTable = NULL;
  protected $_hilCheckInkomenColumns = array();
  protected $_hilAangemeldCaseStatusId = NULL;
  protected $_hilLopendCaseStatusId = NULL;
  protected $_hilOpenCaseActivityTypeId = NULL;
  protected $_hilChangeStatusSubject = NULL;
  protected $_dossierManagerRelationId = 0;

  function __construct() {
    $this->_limit = 50;
    $this->_add2groupSupported = FALSE;
    $this->setHilConfigDefaults();
    $this->case_statuses = CRM_Case_PseudoConstant::caseStatus();
    $rels                = CRM_Core_PseudoConstant::relationshipType();
    foreach ($rels as $relid => $v) {
      if ($v['label_b_a'] == 'Dossiermanager') {
        $this->_dossierManagerRelationId = $relid;
      }
    }

    $this->deleted_labels = array('' => ts('- select -'), 0 => ts('No'), 1 => ts('Yes'));

    $this->_columns = array(
      'civicrm_c2' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' =>
        array(
          'client_name' =>
          array(
            'name' => 'sort_name',
            'title' => ts('Client'),
            'required' => TRUE,
          ),
          'id' =>
          array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'gender_id' =>
          array('name' => 'gender_id', 'title' => ts('Gender'), 'required' => TRUE)
        ),
      ),
      'civicrm_case' =>
      array(
        'dao' => 'CRM_Case_DAO_Case',
        'fields' =>
        array(
          'id' =>
          array('title' => ts('Case ID'),
            'required' => TRUE,
          ),
          'subject' => array(
            'title' => ts('Case Subject'), 'default' => TRUE,
          ),
          'status_id' => array(
            'title' => 'Dossierstatus', 'default' => TRUE,
          ),
          'start_date' => array(
            'title' => ts('Start Date'), 'default' => TRUE,
          ),
          'end_date' => array(
            'title' => ts('End Date'), 'default' => TRUE,
          ),
          'duration' => array(
            'title' => ts('Duration (Days)'), 'default' => FALSE,
          ),
          'is_deleted' => array(
            'title' => ts('Deleted?'), 'default' => FALSE, 'type' => CRM_Utils_Type::T_INT,
          ),
        ),
        'filters' =>
        array('start_date' => array('title' => ts('Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'end_date' => array('title' => ts('End Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'status_id' => array('title' => 'Dossiestatus',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->case_statuses,
          ),
          'is_deleted' => array('title' => ts('Deleted?'),
            'type' => CRM_Report_Form::OP_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->deleted_labels,
            'default' => 0,
          ),
        ),
      ),
      'civicrm_contact' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' =>
        array(
          'sort_name' =>
          array('title' => ts('Staff Member'),
            'default' => TRUE,
          ),
        ),
        'filters' =>
        array('sort_name' => array('title' => ts('Staff Member'),
          ),
        ),
      ),
      'civicrm_relationship' =>
      array(
        'dao' => 'CRM_Contact_DAO_Relationship',
        'filters' =>
        array('relationship_type_id' => array('title' => ts('Staff Relationship'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'no_display' => TRUE,
            'options' => $this->rel_types,
          ),
        ),
      ),
      'civicrm_relationship_type' =>
      array(
        'dao' => 'CRM_Contact_DAO_RelationshipType',
        'fields' =>
        array(
          'label_b_a' =>
          array(
            'title' => ts('Relationship'), 'default' => TRUE,
          ),
        ),
      ),
      'civicrm_case_contact' =>
      array(
        'dao' => 'CRM_Case_DAO_CaseContact',
      ),
    );

    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }

  function select() {
    $select = array();
    $this->_columnHeaders = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {

            if ($tableName == 'civicrm_relationship_type') {
              $this->_relField = TRUE;
            }

            if ($fieldName == 'duration') {
              $select[] = "IF({$table['fields']['end_date']['dbAlias']} Is Null, '', DATEDIFF({$table['fields']['end_date']['dbAlias']}, {$table['fields']['start_date']['dbAlias']})) as {$tableName}_{$fieldName}";
            }
            else {
              $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            }
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {

    $cc  = $this->_aliases['civicrm_case'];
    $c   = $this->_aliases['civicrm_contact'];
    $c2  = $this->_aliases['civicrm_c2'];
    $cr  = $this->_aliases['civicrm_relationship'];
    $crt = $this->_aliases['civicrm_relationship_type'];
    $ccc = $this->_aliases['civicrm_case_contact'];
    if ($this->_relField) {
      $this->_from = "
            FROM civicrm_contact $c 
inner join civicrm_relationship $cr on {$c}.id = ${cr}.contact_id_b
inner join civicrm_case $cc on ${cc}.id = ${cr}.case_id
inner join civicrm_relationship_type $crt on ${crt}.id=${cr}.relationship_type_id AND ${crt}.id=$this->_dossierManagerRelationId
inner join civicrm_case_contact $ccc on ${ccc}.case_id = ${cc}.id
inner join civicrm_contact $c2 on ${c2}.id=${ccc}.contact_id
";
    }
    else {
      $this->_from = "
            FROM civicrm_case $cc
inner join civicrm_case_contact $ccc on ${ccc}.case_id = ${cc}.id
inner join civicrm_contact $c2 on ${c2}.id=${ccc}.contact_id
";
    }
  }

  function where() {
    $clauses = array();
    $this->_having = '';
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value("operatorType", $field) & CRM_Report_Form::OP_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['dbAlias'], $relative, $from, $to,
              CRM_Utils_Array::value('type', $field)
            );
          }
          else {

            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);

            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
            $clauses[] = "(case_civireport.case_type_id LIKE CONCAT ('%".
              CRM_Core_DAO::VALUE_SEPARATOR."',".$this->_hilCaseTypeId.",'".
              CRM_Core_DAO::VALUE_SEPARATOR ."%'))";
          }
        }
      }
    }

    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }
  }

  function groupBy() {
    $this->_groupBy = "";
  }

  function postProcess() {

    $this->beginPostProcess();

    $sql = $this->buildQuery(TRUE);

    $rows = $graphRows = array();
    $this->buildRows($sql, $rows);
    $this->formatDisplay($rows);
    
    $this->hilEnhanceRows($rows);

    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_c2_gender_id', $row)) {
        $rows[$rowNum]['civicrm_c2_gender_id'] = $this->getGender($row['civicrm_c2_gender_id']);
      }
      
      if (array_key_exists('civicrm_case_status_id', $row)) {
        if ($value = $row['civicrm_case_status_id']) {
          $rows[$rowNum]['civicrm_case_status_id'] = $this->case_statuses[$value];
          $entryFound = TRUE;
        }
      }

      // convert Case ID and Subject to links to Manage Case
      if (array_key_exists('civicrm_case_id', $row) &&
        CRM_Utils_Array::value('civicrm_c2_id', $rows[$rowNum])
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view/case",
          'reset=1&action=view&cid=' . $row['civicrm_c2_id'] . '&id=' . $row['civicrm_case_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_case_id_link'] = $url;
        $rows[$rowNum]['civicrm_case_id_hover'] = ts("Manage Case");
        $entryFound = TRUE;
      }
      if (array_key_exists('civicrm_case_subject', $row) &&
        CRM_Utils_Array::value('civicrm_c2_id', $rows[$rowNum])
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view/case",
          'reset=1&action=view&cid=' . $row['civicrm_c2_id'] . '&id=' . $row['civicrm_case_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_case_subject_link'] = $url;
        $rows[$rowNum]['civicrm_case_subject_hover'] = ts("Manage Case");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_case_is_deleted', $row)) {
        $value = $row['civicrm_case_is_deleted'];
        $rows[$rowNum]['civicrm_case_is_deleted'] = $this->deleted_labels[$value];
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }
  /**
   * Function to set default config options for Het Inter-lokaal
   * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
   * @access private
   */
  private function setHilConfigDefaults() {
    $this->_hilCaseType = 'Financien';
    $this->_hilCaseTypeId = $this->setHilCaseTypeId($this->_hilCaseType);
    $this->_hilExtraGegevensName = 'Extra_gegevens';
    $this->_hilExtraGegevensGroupId = $this->getCustomGroupId($this->_hilExtraGegevensName);
    $this->_hilExtraGegevensTable = $this->getCustomTableName($this->_hilExtraGegevensGroupId);
    $this->setHilExtraGegevensColumns();
    $this->_hilCheckInkomenName = 'Check_inkomensrechten';
    $this->_hilCheckInkomenGroupId = $this->getCustomGroupId($this->_hilCheckInkomenName);
    $this->_hilCheckInkomenTable = $this->getCustomTableName($this->_hilCheckInkomenGroupId);
    $this->setHilCheckInkomenColumns();
    $this->setHilCaseStatusIds();
    $this->_hilChangeStatusSubject = 'De dossierstatus is gewijzigd van Aanmelding naar Lopend.';
    $this->setHilOpenCaseActivityTypeId();
  }
  private function setHilOpenCaseActivityTypeId() {
    $optionGroupParams = array(
      'name' => 'activity_type',
      'return' => 'id');
    try {
      $activityTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $optionGroupParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option group with name activity_type, '
        . 'error from API OptionGroup GetValue: '.$ex->getMessage());
    }
    $optionValueParams = array(
      'option_group_id' => $activityTypeOptionGroupId,
      'name' => 'Open Case',
      'return' => 'value');
    try {
      $this->_hilOpenCaseActivityTypeId = civicrm_api3('OptionValue', 'Getvalue', $optionValueParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option value for activity_type Open Case, '
        . 'error from API OptionValue GetValue: '.$ex->getMessage());      
    }
  }
  /**
   * Function to set case status ids for aangemeld and lopend
   * 
   * @throws Exception when no option group for case_status found
   */
  private function setHilCaseStatusIds() {
    $optionGroupParams = array(
      'name' => 'case_status',
      'return' => 'id');
    try {
      $caseStatusOptionGroudId = civicrm_api3('OptionGroup', 'Getvalue', $optionGroupParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option group with name case_status, '
        . 'error from API OptionGroup GetValue: '.$ex->getMessage());
    }
    $optionValueParams = array('option_group_id' => $caseStatusOptionGroudId);
    $optionValues = civicrm_api3('OptionValue', 'Get', $optionValueParams);
    foreach ($optionValues['values'] as $optionValue) {
      switch ($optionValue['name']) {
        case 'Aangemeld':
          $this->_hilAangemeldCaseStatusId = $optionValue['value'];
          break;
        case 'Lopend':
          $this->_hilLopendCaseStatusId = $optionValue['value'];
          break;
      }
    }
  }
  /**
   * Function to get relevant column names from Extra Gegevens
   */
  private function setHilExtraGegevensColumns() {
    $fields = civicrm_api3('CustomField', 'Get', array('custom_group_id' => $this->_hilExtraGegevensGroupId));
    foreach ($fields['values'] as $field) {
      switch($field['name']) {
        case 'Burgerlijke_staat':
          $this->_hilExtraGegevensColumns['burgerlijke_staat'] = $field['column_name'];
          break;
        case 'Land_van_herkomst':
          $this->_hilExtraGegevensColumns['land_van_herkomst'] = $field['column_name'];
          break;
        case 'Economische_status2':
          $this->_hilExtraGegevensColumns['economische_status'] = $field['column_name'];
          break;
      }
    }
  }
  /**
   * Function to get relevant column names from Check Inkomensrechten
   */
  private function setHilCheckInkomenColumns() {
    $fields = civicrm_api3('CustomField', 'Get', array('custom_group_id' => $this->_hilCheckInkomenGroupId));
    foreach ($fields['values'] as $field) {
      switch($field['name']) {
        case 'Soort_inkomensrechten_':
          $this->_hilCheckInkomenColumns['soort_inkomen'] = $field['column_name'];
          break;
        case 'Status_Inkomensrechten_':
          $this->_hilCheckInkomenColumns['status_inkomen'] = $field['column_name'];
          break;
        case 'Opbrengst_check_inkomen_':
          $this->_hilCheckInkomenColumns['opbrengst_inkomen'] = $field['column_name'];
          break;
        case 'Opbrengst_check_belastingen_':
          $this->_hilCheckInkomenColumns['opbrengst_belastingen'] = $field['column_name'];
          break;
        case 'Opbrengst_check_voorzieningen_':
          $this->_hilCheckInkomenColumns['opbrengst_voorzieningen'] = $field['column_name'];
          break;
        case 'Opbrengst_Volledig_Check':
          $this->_hilCheckInkomenColumns['opbrengst_volledig'] = $field['column_name'];
          break;
      }
    }
  }
  /**
   * Function to retrieve table_name for custom_group
   * 
   * @param int $customGroupId
   * @return string $customTableName
   */
  private function getCustomTableName($customGroupId) {
    $params = array('id' => $customGroupId, 'return' => 'table_name');
    try {
      $customTableName = civicrm_api3('CustomGroup', 'Getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not get table name for custom group '.$customGroupId.
        ', error from API CustomGroup Getvalue: '.$ex->getMessage());
    }
    return $customTableName;
  }
  /**
   * Function to set custom group id for incoming name
   * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
   * @param string $customGroupName
   * @return int $customGroupId
   * @throws Exception when no Custom Group for name found
   * @access private
   */
  private function getCustomGroupId($customGroupName) {
    if (empty($customGroupName)) {
      $customGroupId = 0;
    } else {
      $customGroupParams = array('name' => $customGroupName, 'return' => 'id');
      try {
        $customGroupId = civicrm_api3('CustomGroup', 'Getvalue', $customGroupParams);
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not find a custom group with name '.$customGroupName.
          ', error from API CustomGroup Getvalue: '.$ex->getMessage());
      }
    }
    return $customGroupId;
  }
  /**
   * Function to set case type id for incoming name
   * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
   * @param string $caseTypeName
   * @return int $caseTypeId
   * @access private
   * @throws Exception when no Option Group case_type found
   * @throws Exception when no Case Type Id for name found
   */
  private function setHilCaseTypeId($caseTypeName) {
    if (empty($caseTypeName)) {
      $caseTypeId = 0;
    } else {
      $optionGroupParams = array('name' => 'case_type', 'is_active' => 1, 'return' => 'id');
      try {
        $caseTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $optionGroupParams);
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not find an option group with name case_type, '
          . 'error from API OptionGroup Getvalue : '.$ex->getMessage());
      }
      $optionValueParams = array(
        'option_group_id' => $caseTypeOptionGroupId, 
        'name' => $caseTypeName,
        'is_active' => 1,
        'return' => 'value');
      try {
        $caseTypeId = civicrm_api3('OptionValue', 'Getvalue', $optionValueParams);
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not find a valid case type with name '.
          $caseTypeName.', error from API OptionValue Getvalue : '.$ex->getMessage());
      }      
    }
    return $caseTypeId;
  }
  /*
   * Function to add column headers 
   */
  function modifyColumnHeaders() {
    $this->_columnHeaders['leeftijd'] = array('title' => ts('Age'), 'type' => 2);
    $this->_columnHeaders['burgerlijke_staat'] = array('title' => ts('Burg. Staat'), 'type' => 2);
    $this->_columnHeaders['land_van_herkomst'] = array('title' => ts('Land van Herkomst'), 'type' => 2);
    $this->_columnHeaders['economische_status'] = array('title' => ts('Economische Status'), 'type' => 2);
    $this->_columnHeaders['soort_inkomen'] = array('title' => ts('Soort Inkomensrechten'), 'type' => 2);
    $this->_columnHeaders['status_inkomen'] = array('title' => ts('Status Inkomensrechten'), 'type' => 2);
    $this->_columnHeaders['opbrengst_inkomen'] = array('title' => ts('Opbrengst check inkomen'), 'type' => 2);
    $this->_columnHeaders['opbrengst_belastingen'] = array('title' => ts('Opbrengst check belastingen'), 'type' => 2);
    $this->_columnHeaders['opbrengst_voorzieningen'] = array('title' => ts('Opbrengst check voorzieningen'), 'type' => 2);
    $this->_columnHeaders['opbrengst_volledig'] = array('title' => ts('Opbrengst check volledig'), 'type' => 2);
    $this->_columnHeaders['wachttijd'] = array('title' => ts('Wachttijd'), 'type' => 2);
  }
  /**
   * Function to enhance the rows selected with the specific Inter-Lokaal data
   * 
   * @param array $rows
   */
  private function hilEnhanceRows(&$rows) {
    foreach ($rows as $rowNum => $row) {
      $rows[$rowNum]['leeftijd'] = $this->getLeeftijd($row['civicrm_c2_id']);
      $extraGegevens = $this->getExtraGegevens($row['civicrm_c2_id']);
      $rows[$rowNum]['burgerlijke_staat'] = $extraGegevens['burgerlijke_staat'];
      $rows[$rowNum]['land_van_herkomst'] = $extraGegevens['land_van_herkomst'];
      $rows[$rowNum]['economische_status'] = $extraGegevens['economische_status'];
      $checkInkomen = $this->getCheckInkomen($row['civicrm_case_id']);
      $rows[$rowNum]['soort_inkomen'] = $checkInkomen['soort_inkomen'];
      $rows[$rowNum]['status_inkomen'] = $checkInkomen['status_inkomen'];
      $rows[$rowNum]['opbrengst_inkomen'] = $checkInkomen['opbrengst_inkomen'];
      $rows[$rowNum]['opbrengst_belastingen'] = $checkInkomen['opbrengst_belastingen'];
      $rows[$rowNum]['opbrengst_voorzieningen'] = $checkInkomen['opbrengst_voorzieningen'];
      $rows[$rowNum]['opbrengst_volledig'] = $checkInkomen['opbrengst_volledig'];
      $rows[$rowNum]['wachttijd'] = $this->calculateWachttijd($row['civicrm_case_id']);
    }
  }
  /**
   * Function to get the open case date time for a case
   * 
   * @param int $caseId
   * @return date
   */
  private function getOpenCase($caseId) {
    $query = 
      'SELECT b.activity_date_time
        FROM civicrm_case_activity a
        JOIN civicrm_activity b ON a.activity_id = b.id
        WHERE case_id = %1 AND b.is_current_revision = %2 
        AND b.activity_type_id = %3';
    $params = array(
      1 => array($caseId, 'Positive'),
      2 => array(1, 'Positive'),
      3 => array($this->_hilOpenCaseActivityTypeId, 'Positive'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      if (!empty($dao->activity_date_time)) {
        return $dao->activity_date_time;
      } else {
        return null;
      }
    }
  }
  /**
   * Function to get the change case status from aangemeld to lopend date time for a case
   * 
   * @param int $caseId
   * @return date
   */
  private function getChangeAangemeldToLopend($caseId) {
    $retrievedDate = null;
    $query = 
      'SELECT b.activity_date_time
        FROM civicrm_case_activity a
        JOIN civicrm_activity b ON a.activity_id = b.id
        WHERE case_id = %1 AND b.is_current_revision = %2 
        AND b.subject = %3';
    $params = array(
      1 => array($caseId, 'Positive'),
      2 => array(1, 'Positive'),
      3 => array($this->_hilChangeStatusSubject, 'String'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      if (!empty($dao->activity_date_time)) {
        $retrievedDate = $dao->activity_date_time;
      } else {
        $retrievedDate = null;
      }
    }
    return $retrievedDate;
  }
  /**
   * Function to calculate time path between Open Case and Change Case Status from
   * aangemeld to lopend
   * 
   * @param int $caseId
   * @return int $wachttijd
   */
  private function calculateWachttijd($caseId) {
    $wachtTijd = '';
    $openCaseDateTime = $this->getOpenCase($caseId);
    if (!empty($openCaseDateTime)) {
      $openDate = new DateTime($openCaseDateTime);
      $changeCaseStatusDateTime = $this->getChangeAangemeldToLopend($caseId);
      if (!empty($changeCaseStatusDateTime)) {
        $changeDate = new DateTime($changeCaseStatusDateTime);
        $interval = $openDate->diff($changeDate);
        $wachtTijd = (string) $interval->days;
      } else {
        $wachtTijd = '';
      }
    return $wachtTijd;
    }
  }
  /**
   * Function to get custom fields from Check Inkomen
   * 
   * @param int $entityId
   * @return array $checkInkomen
   */
  private function getCheckInkomen($entityId) {
    $checkInkomen = array();
    $query = 'SELECT '.implode(', ', $this->_hilCheckInkomenColumns).' FROM '.
      $this->_hilCheckInkomenTable.' WHERE entity_id = %1';
    $params = array(1 => array($entityId, 'Positive'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      $checkInkomen['soort_inkomen'] = $dao->{$this->_hilCheckInkomenColumns['soort_inkomen']};
      $checkInkomen['status_inkomen'] = $dao->{$this->_hilCheckInkomenColumns['status_inkomen']};
      $checkInkomen['opbrengst_inkomen'] = CRM_Utils_Money::format($dao->{$this->_hilCheckInkomenColumns['opbrengst_inkomen']});
      $checkInkomen['opbrengst_belastingen'] = CRM_Utils_Money::format($dao->{$this->_hilCheckInkomenColumns['opbrengst_belastingen']});
      $checkInkomen['opbrengst_voorzieningen'] = CRM_Utils_Money::format($dao->{$this->_hilCheckInkomenColumns['opbrengst_voorzieningen']});
      $checkInkomen['opbrengst_volledig'] = CRM_Utils_Money::format($dao->{$this->_hilCheckInkomenColumns['opbrengst_volledig']});
    }
    return $checkInkomen;
  }
  /**
   * Function to get custom fields from Extra Gegevens
   * 
   * @param int $entityId
   * @return array $extraGegevens
   */
  private function getExtraGegevens($entityId) {
    $extraGegevens = array();
    $query = 'SELECT '.implode(', ', $this->_hilExtraGegevensColumns).' FROM '.
      $this->_hilExtraGegevensTable.' WHERE entity_id = %1';
    $params = array(1 => array($entityId, 'Positive'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      $extraGegevens['burgerlijke_staat'] = $dao->{$this->_hilExtraGegevensColumns['burgerlijke_staat']};
      $extraGegevens['land_van_herkomst'] = $this->getCountryName($dao->{$this->_hilExtraGegevensColumns['land_van_herkomst']});
      $extraGegevens['economische_status'] = $dao->{$this->_hilExtraGegevensColumns['economische_status']};
    }
    return $extraGegevens;
  }
 /**
   * Function to get the name of a country
   * 
   * @param int $countryId
   * @return string $countryName
   */
  private function getCountryName($countryId) {
    $params = array(
      'id' => $countryId,
      'return' => 'name');
    try {
      $countryName = civicrm_api3('Country', 'Getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {
      $countryName = '';
    }
    return ts($countryName);
  }
  /**
   * Function to calcute age
   * @param type $contactId
   * @return type
   */
  private function getLeeftijd($contactId) {
    $params = array(
      'id' => $contactId,
      'return' => 'birth_date');
    try {
      $birthDate = civicrm_api3('Contact', 'Getvalue', $params);
      if (!empty($birthDate)) {
        $leeftijd = CRM_Utils_Date::calculateAge($birthDate);
      } else {
        $leeftijd = NULL;
      }
    } catch (CiviCRM_API3_Exception $ex) {
      $leeftijd = NULL;
    }
    return $leeftijd['years'];
  }
  private function getGender($genderId) {
    $groupParams = array('name' => 'gender', 'return' => 'id');
    try {
      $genderGroupId = civicrm_api3('OptionGroup', 'Getvalue', $groupParams);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find an option group with name gender, error '
        . 'from API OptionGroup Getvalue: '.$ex->getMessage());
    }
    $valueParams = array(
      'option_group_id' => $genderGroupId, 
      'value' => $genderId, 
      'return' => 'label');
    try {
      $genderLabel = civicrm_api3('OptionValue', 'Getvalue', $valueParams);
    } catch (CiviCRM_API3_Exception $ex) {
      $genderLabel = '';
    }
    return $genderLabel;
  }
}

