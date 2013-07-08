<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */
ini_set( 'display_errors', '1' );
require_once 'CRM/Report/Form.php';

class CRM_Report_Form_KlantAnalyse extends CRM_Report_Form {

    protected $_summary      = null;

    protected $_emailField   = false;

    protected $_phoneField   = false;

    function __construct() {
        $this->_autoIncludeIndexedFieldsAsOrderBys = false;
        /*
         * retrieve option values for locatie
         */
        $apiParams = array(
            'version'   =>  3,
            'title'     =>  'Locatie'
        );
        $optionValues = array();
        $optionValues[0] = '- alle ';
        $optionGroupApi = civicrm_api('OptionGroup', 'Getsingle', $apiParams);
        if (!isset($optionGroupApi['is_error']) || $optionGroupApi['is_error'] == 0) {
            $apiParams = array(
                'version'           =>  3,
                'option_group_id'   =>  $optionGroupApi['id']
            );
            $apiValues = civicrm_api('OptionValue', 'Get', $apiParams);
            if (!isset($apiValues['is_error']) || $apiValues['is_error'] == 0) {
                if (isset($apiValues['values'])) {
                    foreach ($apiValues['values'] as $apiValue) {
                        $optionValues[$apiValue['value']] = $apiValue['label'];
                    }
                }
            }
        }
        $this->_columns =
                array('civicrm_contact' =>
                    array( 'dao'       => 'CRM_Contact_DAO_Contact',
                           'fields'    =>
                          array('display_name' =>
                                 array('no_display'=> true),
                                 'id'           =>
                                 array('no_display'=> true),
                                 'birth_date'   =>
                                 array('no_display'=>true),),
                          'filters'   =>
                          array('periode'    =>
                                 array('title'          =>  'Periode',
                                        'operatorType'  =>  CRM_Report_Form::OP_DATE),
                                 'locatie'  =>
                                 array('title'          => 'Locatie',
                                        'operatorType'  =>  CRM_Report_Form::OP_SELECT,
                                        'options'       =>  $optionValues),),),);
        $this->_tagFilter = false;
        $this->_groupFilter = false;
        parent::__construct();
    }

    function preProcess() {
        parent::preProcess();
    }

    static function formRule($fields, $files, $self) {
        $errors = $grouping = array();
        return $errors;
    }

    function postProcess() {

        $this->_columnHeaders = array(
            'klantnaam' 	=> array('title' => 'Klantnaam'),
            'adres'		=> array('title' => 'Adres'),
            'postcode'  	=> array('title' => 'Postcode'),
            'plaats'            => array('title' => 'Plaats'),
            'leeftijd'		=> array('title' => 'Leeftijd'),
            'geslacht'          => array('title' => 'Geslacht') ,
            'aantal_dossier'    => array('title' => 'Aant dossiers'),
            'aantal_enkel'      => array('title' => 'Aant. EH'),
            'datum_eerste'      => array('title' => 'Datum 1e contact'),
            'econ_status'	=> array('title' => 'Econ. status'),
            'burg_staat'	=> array('title' => 'Burg. staat'),
            'land_herkomst'	=> array('title' => 'Land herkomst'),
            'cult_ethn'		=> array('title' => 'Cult. ethn.'),
            'nationaliteit'	=> array('title' => 'Nationaliteit')
           );
        $this->beginPostProcess();

        // get the acl clauses built before we assemble the query
        $this->buildACLClause($this->_aliases['civicrm_contact']);
        /*
         * retrieve contactIds for activities 'Enkelvoudige hulpvraag' in entered
         * period for selected locations
         */
        if (isset($this->_submitValues) && !empty($this->_submitValues)) {
            if (isset($this->_submitValues['periode_from']) && !empty($this->_submitValues['periode_from'])) {
                $periodFrom = new DateTime($this->_submitValues['periode_from']);
            } else {
                $periodFrom = new DateTime('1900-01-01');
            }
            if (isset($this->_submitValues['periode_to']) && !empty($this->_submitValues['periode_to'])) {
                $periodTo = newDateTime($this->_submitValues['period_to']);
            } else {
                $periodTo = new DateTime('2100-12-31');
            }
            if (isset($this->_submitValues['locatie_value'])) {
                $locationValue = $this->_submitValues['locatie_value'];
            } else {
                $locationValue = 0;
            }
            $contactIds = array();
            $actContacts = $this->retrieveActivities($periodFrom, $periodTo, $locationValue);
            $caseContacts = $this->retrieveCases($periodFrom, $periodTo, $locationValue);
            $mergedContacts = array_merge($actContacts, $caseContacts);
        }
        $this->buildRows ($rows, $mergedContacts );
        $this->formatDisplay( $rows );
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );
    }

    function alterDisplay( &$rows ) {
        // custom code to alter rows
        $entryFound = false;
        foreach ( $rows as $rowNum => $row ) {
            // make count columns point to detail report
            // convert sort name to links
            if ( array_key_exists('civicrm_contact_sort_name', $row) &&
                 array_key_exists('civicrm_contact_id', $row) ) {
                $url = CRM_Report_Utils_Report::getNextUrl( 'contact/detail',
                                              'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_id'],
                                              $this->_absoluteUrl, $this->_id );
                $rows[$rowNum]['civicrm_contact_sort_name_link' ] = $url;
                $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Constituent Detail Report for this contact.");
                $entryFound = true;
            }

            if ( array_key_exists('civicrm_address_state_province_id', $row) ) {
                if ( $value = $row['civicrm_address_state_province_id'] ) {
                    $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince( $value, false );
                }
                $entryFound = true;
            }


            // skip looking further in rows, if first row itself doesn't
            // have the column we need
            if ( !$entryFound ) {
                break;
            }
        }
    }
    function buildQuickForm( ) {
        $this->addColumns( );

        $this->addFilters( );

        $this->addOptions( );

        $this->buildInstanceAndButtons( );

        //add form rule for report
        if ( is_callable( array( $this, 'formRule' ) ) ) {
            $this->addFormRule( array( get_class($this), 'formRule' ), $this );
        }
    }
    function addColumns( ) {
        $options = array();
        $colGroups = null;
        $this->assign( 'colGroups', $colGroups );
    }
    function setDefaultValues( $freeze = true ) {

        if ( $this->_formValues ) {
            $this->_defaults = array_merge( $this->_defaults, $this->_formValues );
        }

        if ( $this->_instanceValues ) {
            $this->_defaults = array_merge( $this->_defaults, $this->_instanceValues );
        }

        require_once 'CRM/Report/Form/Instance.php';
        CRM_Report_Form_Instance::setDefaultValues( $this, $this->_defaults );

        return $this->_defaults;
    }
    function addFilters( ) {
        require_once 'CRM/Utils/Date.php';
        require_once 'CRM/Core/Form/Date.php';
        $options = $filters = array();
        $count = 1;
        foreach ( $this->_filters as $table => $attributes ) {
            foreach ( $attributes as $fieldName => $field ) {
                // get ready with option value pair
                $operations = $this->getOperationPair( CRM_Utils_Array::value( 'operatorType', $field ),
                                                       $fieldName );

                $filters[$table][$fieldName] = $field;

                switch ( CRM_Utils_Array::value( 'operatorType', $field )) {
                case CRM_Report_FORM::OP_SELECT :
                    // assume a select field
                    $this->addElement('select', $fieldName."_op", ts( 'Operator:' ), $operations);
                    $this->addElement('select', $fieldName."_value", null, $field['options']);
                    break;

                case CRM_Report_FORM::OP_DATE :
                    // build datetime fields
                    $this->addDate( $fieldName.'_from','Van:', $required,array( 'formatType' => $dateFormat ) );
                    $count++;
                    $this->addDate( $fieldName.'_to','Tot:', $required,array( 'formatType' => $dateFormat ) );
                    $count++;
                    break;

                default:
                    // default type is string
                    $this->addElement('select', "{$fieldName}_op", ts( 'Operator:' ), $operations,
                                      array('onchange' =>"return showHideMaxMinVal( '$fieldName', this.value );"));
                    // we need text box for value input
                    $this->add( 'text', "{$fieldName}_value", null );
                    break;
                }
            }
        }
        $this->assign( 'filters', $filters );
    }
    function buildRows(&$rows, $listContacts ) {
        // use this method to modify $this->_columnHeaders
        $this->modifyColumnHeaders( );
        /*
         * dedupe and level up $listContacts
         */
        $rows = array();
        foreach ($listContacts as $key1 => $rowContacts) {
            foreach ($rowContacts as $contactId => $rowContact) {
                $row = $rowContact;
                if ( !isset($rows[$contactId])) {
                    $rows[$contactId] = $row;
                }
            }
        }
    }
    function retrieveActivities($periodFrom, $periodTo, $locationValue) {
        $actSelectedContacts = array();
        require_once 'CRM/Utils/HilreportsUtils.php';
        $actTypeId = CRM_Utils_HilreportsUtils::getEnkelvoudigeHulpvraagTypeId();
        /*
         * first retrieve all activities that have the activity_type_id of Enkelvoudige
         * Hulpvraag and only save them if they are in selected time slot and with
         * selected location
         */
        $apiParams = array(
            'version'           =>  3,
            'activity_type_id'  =>  $actTypeId
        );
        $apiActivities = civicrm_api( 'Activity', 'Get', $apiParams );
        if (isset($apiActivities['is_error']) && $apiActivities['is_error'] == 0) {
            foreach($apiActivities['values'] as $actId => $hulpVraag) {
                /*
                 * ignore cancelled activities
                 */
                if ($hulpVraag['status_id'] != 3)  {
                    $hulpVraagDate = new DateTime($hulpVraag['activity_date_time']);
                    if ($hulpVraagDate >= $periodFrom) {
                        if (empty($periodTo) || $hulpVraagDate <= $periodTo) {
                            /*
                             * if location was selected, retrieve custom value for activity
                             */
                            if ($locationValue != 0) {
                                $apiParams = array(
                                    'version'       =>  3,
                                    'entity_table'  =>  'Activity',
                                    'entity_id'     =>  $actId
                                );
                                $apiCustomValues = civicrm_api('CustomValue', 'Get', $apiParams);
                                if (isset($apiCustomValues['is_error']) && $apiCustomValues['is_error'] == 0) {
                                    foreach ($apiCustomValues['values'] as $customId => $apiCustomValue) {
                                        if (isset($apiCustomValue['latest']) && $apiCustomValue['latest'] == $locationValue) {
                                            $actSelectedContacts[] = $this->addActivityContact($actId);
                                        }
                                    }
                                }
                            } else {
                                $actSelectedContacts[] = $this->addActivityContact($actId);
                            }
                        }
                    }
                }
            }
        }
        return $actSelectedContacts;
    }
    function retrieveCases($periodFrom, $periodTo, $locationValue) {
        $caseSelectedContacts = array();
        $caseQry = "SELECT id, start_date FROM civicrm_case";
        $caseDAO = CRM_Core_DAO::executeQuery($caseQry);
        while ($caseDAO->fetch()) {
            $caseDate = new DateTime($caseDAO->start_date);
            if ($caseDate >= $periodFrom) {
                if (empty($periodTo) || $caseDate <= $periodTo) {
                    /*
                     * if location was selected, retrieve custom value for case
                     */
                    if ($locationValue != 0) {
                        $apiParams = array(
                            'version'       =>  3,
                            'entity_table'  =>  'Case',
                            'entity_id'     =>  $caseDAO->id
                        );
                        $apiCustomValues = civicrm_api('CustomValue', 'Get', $apiParams);
                        if (isset($apiCustomValues['is_error']) && $apiCustomValues['is_error'] == 0) {
                            foreach ($apiCustomValues['values'] as $customId => $apiCustomValue) {
                                if (isset($apiCustomValue['latest']) && $apiCustomValue['latest'] == $locationValue) {
                                    $caseSelectedContacts[] = $this->addCaseContact($caseDAO->id);
                                }
                            }
                        }
                    } else {
                        $caseSelectedContacts[] = $this->addCaseContact($caseDAO->id);
                    }
                }
            }
        }
        return $caseSelectedContacts;
    }
    function addActivityContact($actId) {
        $actContacts = array();
        if (empty($actId)) {
            return $actContacts;
        }
        /*
         * check if there is a target for the activity
         */
        require_once 'CRM/Activity/BAO/ActivityTarget.php';
        $contactIds = CRM_Activity_BAO_ActivityTarget::retrieveTargetIdsByActivityId($actId);
        foreach($contactIds as $contactId) {
            $actContacts[$contactId] = $this->addContactDetails($contactId);
        }
        return $actContacts;
    }
    function addCaseContact($caseId) {
        $caseContacts = array();
        if (empty($caseId)) {
            return $caseContacts;
        }
        /*
         * check if there is a client for the case
         */
        require_once 'CRM/Case/BAO/Case.php';
        $caseContactIds = CRM_Case_BAO_Case::retrieveContactIdsByCaseId($caseId);
        foreach($caseContactIds as $contactId) {
            $caseContacts[$contactId] = $this->addContactDetails($contactId);
        }
        return $caseContacts;
    }
    function addContactDetails($contactId) {
        $addedContact = array();
        $apiParams = array(
            'version'   =>  3,
            'id'        =>  $contactId
        );
        $apiContact = civicrm_api('Contact', 'Getsingle', $apiParams);
        if (!isset($apiContact['is_error']) || $apiContact['is_error'] == 0) {
            $addedContact['id'] = $apiContact['id'];
            $addedContact['display_name'] = $apiContact['display_name'];
            $addedContact['street_address'] = $apiContact['street_address'];
            $addedContact['city'] = $apiContact['city'];
            $addedContact['postal_code'] = $apiContact['postal_code'];
            $addedContact['gender'] = $apiContact['gender'];
            require_once 'CRM/Utils/HilreportsUtils.php';
            if (isset($apiContact['birth_date']) && !empty($apiContact['birth_date'])) {
                $addedContact['age'] = CRM_Utils_HilreportsUtils::calculateAge( $apiContact['birth_date']);
            }
            $addedContact['econ_status'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Economische status');
            $addedContact['burg_staat'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Burgerlijke staat');
            $addedContact['land_herkomst'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Land van herkomst');
            $addedContact['cult_ethn'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Ethnisch/culturele achtergrond');
            $addedContact['nationaliteit'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Nationaliteit');
            $addedContact['eerste_contact'] = CRM_Utils_HilreportsUtils::getContactFirstDate($contactId);
            $addedContact['aantal_enkel'] = CRM_Utils_HilreportsUtils::getCountEnkelvoudigeHulpvraag($contactId);
            $addedContact['aantal_dossier'] = CRM_Utils_HilreportsUtils::getCountCases($contactId);
        }
        return $addedContact;
    }
}
