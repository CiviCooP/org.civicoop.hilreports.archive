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
set_time_limit(0);
ini_set( 'display_errors', '1' );
require_once 'CRM/Report/Form.php';

class CRM_Report_Form_KlantAnalyse extends CRM_Report_Form {

    protected $_summary      = null;

    protected $_emailField   = false;

    protected $_phoneField   = false;

    function __construct() {
        $this->_autoIncludeIndexedFieldsAsOrderBys = false;
        $this->_add2groupSupported = false;
        /*
         * retrieve option values for locatie
         */
        $apiParams = array(
            'version'   =>  3,
            'title'     =>  'Locatie Act'
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
        global $rowContacts;

        $this->_columnHeaders = array(
            'klantnaam' 	=> array('title' => 'Klantnaam'),
            'adres'		=> array('title' => 'Adres'),
            'postcode'  	=> array('title' => 'Postcode'),
            'plaats'            => array('title' => 'Plaats'),
            'leeftijd'		=> array('title' => 'Lft'),
            'geslacht'          => array('title' => 'Gsl') ,
            'aantal_dossier'    => array('title' => 'Aant doss'),
            'aantal_enkel'      => array('title' => 'Aant EH'),
            'datum_eerste'      => array('title' => 'Dat 1e cont'),
            'econ_status'	=> array('title' => 'Econ status'),
            'burg_staat'	=> array('title' => 'Burg staat'),
            'land_herkomst'	=> array('title' => 'Land herkomst'),
            'cult_ethn'		=> array('title' => 'Cult ethn'),
            'nationaliteit'	=> array('title' => 'Nation')
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
                $periodTo = new DateTime($this->_submitValues['periode_to']);
            } else {
                $periodTo = new DateTime('2100-12-31');
            }
            if (isset($this->_submitValues['locatie_value'])) {
                $locationValue = $this->_submitValues['locatie_value'];
            } else {
                $locationValue = 0;
            }
            $rowContacts = array();
            $this->retrieveActivities($periodFrom, $periodTo, $locationValue);
            $this->retrieveCases($periodFrom, $periodTo, $locationValue);
        }
        $this->buildRows ($rows);
        $this->formatDisplay( $rows );
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );
    }

    function alterDisplay( &$rows ) {
        $entryFound = false;
        foreach ($rows as $rowNum => $row) {
            // make count columns point to detail report
            // convert display name to links
            if (array_key_exists('klantnaam', $row)) {
                $url = CRM_Utils_System::url( "civicrm/contact/view",
                    'reset=1&cid=' . $row['id'], $this->_absoluteUrl );
                $rows[$rowNum]['klantnaam_link' ] = $url;
                $rows[$rowNum]['klantnaam_hover'] = ts("View Contact details for this contact.");
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
                    $this->addDate( $fieldName.'_from','Van:', false, array( 'formatType' => $dateFormat ) );
                    $count++;
                    $this->addDate( $fieldName.'_to','Tot:', false, array( 'formatType' => $dateFormat ) );
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
    function buildRows(&$rows ) {
        // use this method to modify $this->_columnHeaders
        global $rowContacts;
        $this->modifyColumnHeaders( );
        /*
         * dedupe and level up $listContacts
         */
        $rows = array();
        if (!empty($rowContacts)) {
            foreach ($rowContacts as $contactId => $rowContact) {
                $rows[] = $rowContact;
            }
        }
    }
    function retrieveActivities($periodFrom, $periodTo, $locationValue) {
        global $rowContacts;
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
                                            $this->addRowContact($actId, "Activity");
                                        }
                                    }
                                }
                            } else {
                                $this->addRowContact($actId, "Activity");
                            }
                        }
                    }
                }
            }
        }
    }
    function retrieveCases($periodFrom, $periodTo, $locationValue) {
        global $rowContacts;
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
                                    $this->addRowContact($caseDAO->id, "Case");
                                }
                            }
                        }
                    } else {
                        $this->addRowContact($caseDAO->id, "Case");
                    }
                }
            }
        }
    }
    function addRowContact($entityId, $entityType) {
        global $rowContacts;
        if (!empty($entityId)) {
            if ($entityType == "Activity") {
                require_once 'CRM/Activity/BAO/ActivityTarget.php';
                $contactIds = CRM_Activity_BAO_ActivityTarget::retrieveTargetIdsByActivityId($entityId);
            }
            if ($entityType == "Case") {
                require_once 'CRM/Case/BAO/Case.php';
                $contactIds = CRM_Case_BAO_Case::retrieveContactIdsByCaseId($entityId);
            }
            foreach($contactIds as $contactId) {
                $apiParams = array(
                    'version'   =>  3,
                    'id'        =>  $contactId
                );
                $apiContact = civicrm_api('Contact', 'Getsingle', $apiParams);
                $processContact = false;
                if (isset($apiContact['contact_sub_type'])) {
                    if (is_array($apiContact['contact_sub_type'])) {
                        foreach ($apiContact['contact_sub_type'] as $contactSubType) {
                            if ($contactSubType == "Klant") {
                                $processContact = true;
                            }
                        }

                    } else {
                        if ($apiContact['contact_sub_type'] == "Klant") {
                            $processContact = true;
                        }
                    }
                }
                if ($processContact) {
                    if (!isset($apiContact['is_error']) || $apiContact['is_error'] == 0) {
                        if ( !key_exists($contactId, $rowContacts)) {
                            $rowContacts[$contactId]['id'] = $apiContact['id'];
                            $rowContacts[$contactId]['klantnaam'] = $apiContact['display_name'];
                            $rowContacts[$contactId]['adres'] = $apiContact['street_address'];
                            $rowContacts[$contactId]['plaats'] = $apiContact['city'];
                            $rowContacts[$contactId]['postcode'] = $apiContact['postal_code'];
                            $rowContacts[$contactId]['geslacht'] = $apiContact['gender'];
                            require_once 'CRM/Utils/HilreportsUtils.php';
                            if (isset($apiContact['birth_date']) && !empty($apiContact['birth_date'])) {
                                $rowContacts[$contactId]['leeftijd'] = CRM_Utils_HilreportsUtils::calculateAge( $apiContact['birth_date']);
                            }
                            $rowContacts[$contactId]['econ_status'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Economische status');
                            $rowContacts[$contactId]['burg_staat'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Burgerlijke staat');
                            $rowContacts[$contactId]['land_herkomst'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Land van herkomst');
                            $rowContacts[$contactId]['cult_ethn'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Ethnisch/culturele achtergrond');
                            $rowContacts[$contactId]['nationaliteit'] = CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Nationaliteit');
                            $rowContacts[$contactId]['datum_eerste'] = CRM_Utils_HilreportsUtils::getContactFirstDate($contactId);
                            $rowContacts[$contactId]['aantal_enkel'] = (int) CRM_Utils_HilreportsUtils::getCountEnkelvoudigeHulpvraag($contactId);
                            $rowContacts[$contactId]['aantal_dossier'] = (int) CRM_Utils_HilreportsUtils::getCountCases($contactId);
                        }
                    }
                }
            }
        }
    }
}
