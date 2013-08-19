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
require_once 'CRM/Report/Form.php';

class CRM_Report_Form_PercentAnalyse extends CRM_Report_Form {

    protected $_aantalRijen = 0;

    protected $_aantalContacts = 0;

    protected $_customGroupId = 0;

    protected $_optionGroupId = 0;

    protected $_summary      = null;

    protected $_emailField   = false;

    protected $_phoneField   = false;

    protected $_optionValues = array();

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
        $this->_optionValues[0] = '- alle ';
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
                        $this->_optionValues[$apiValue['value']] = $apiValue['label'];
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
                                        'options'       =>  $this->_optionValues),),),);
        $this->_tagFilter = false;
        $this->_groupFilter = false;
        /*
         * create temporary table to hold selected contacts
         * (possibly one for every location depending on discussion with Miesjel)
         * if splitting locations is required, we probably also need a temp
         * table to hold the selected activities and cases with id, contact_id,
         * type (case or activity), locatie and date (start_date)
         */
        $createTempTable =
"CREATE TEMPORARY TABLE `selected_contacts` (
  `contact_id` int(11) NOT NULL,
  `naam` varchar(128) DEFAULT NULL,
  `adres` varchar(128) DEFAULT NULL,
  `plaats` varchar(45) DEFAULT NULL,
  `postcode` char(9) DEFAULT NULL,
  `geslacht` varchar(15) DEFAULT NULL,
  `leeftijd` int(11) DEFAULT NULL,
  `econ_status` varchar(75) DEFAULT NULL,
  `burg_staat` varchar(75) DEFAULT NULL,
  `land_herkomst` varchar(75) DEFAULT NULL,
  `cult_ethn` varchar(75) DEFAULT NULL,
  `nationaliteit` varchar(75) DEFAULT NULL,
  `datum_eerste` date DEFAULT NULL,
  `aantal_enkel` int(11) DEFAULT NULL,
  `aantal_dossier` int(11) DEFAULT NULL,
  PRIMARY KEY (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        CRM_Core_DAO::executeQuery($createTempTable);
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
            'label'              => array('title' => ''),
            'aantal'            => array('title' => 'Aantal'),
            'percentage'        => array('title' => 'Percentage'));
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
            $this->retrieveActivities($periodFrom, $periodTo, $locationValue);
            $this->retrieveCases($periodFrom, $periodTo, $locationValue);
        }
        $this->buildRows ($rows);
        $this->formatDisplay( $rows );
        $this->doTemplateAssignment( $rows );
        $this->endPostProcess( $rows );
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
                    // build datetime fields
                    $this->addDate( $fieldName.'_from','Van:', false, array( 'formatType' => 'searchDate' ) );
                    $count++;
                    $this->addDate( $fieldName.'_to','Tot:', false, array( 'formatType' => 'searchDate' ) );
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
        require_once 'CRM/Utils/HilreportsUtils.php';
        $this->modifyColumnHeaders( );
        $this->calculateAantalContacts();
        $this->setCustomGroupIdExtraGegevens();
        $rowNumber = 0;
        /*
         * eerste rij met totalen
         */
        $rows[$rowNumber]['label'] = "<strong>TOTAAL:</strong>";
        $rows[$rowNumber]['aantal'] = $this->_aantalContacts;
        $rows[$rowNumber]['percentage'] = "100%";
        $rowNumber++;
        $this->_aantalRijen++;
        /*
         * build rows for land van herkomst
         */
        $this->insertEmptyLine($rowNumber, $rows);
        $this->insertHeaderLine($rowNumber, $rows, "Land van herkomst:");
        $this->addRowsLandVanHerkomst($rows, $rowNumber);
        /*
         * build rows for economische status
         */
        $this->insertEmptyLine($rowNumber, $rows);
        $this->insertHeaderLine($rowNumber, $rows, "Economische status");
        $this->addRowsOptionValue($rows, $rowNumber, "Economische status", "econ_status");
        /*
         * build rows for burgerlijke staat
         */
        $this->insertEmptyLine($rowNumber, $rows);
        $this->insertHeaderLine($rowNumber, $rows, "Burgerlijke staat");
        $this->addRowsOptionValue($rows, $rowNumber, "Burgerlijke staat", "burg_staat");
        /*
         * build rows for ethnisch culturele achtergrond
         */
        $this->insertEmptyLine($rowNumber, $rows);
        $this->insertHeaderLine($rowNumber, $rows, "Ethnisch/culturele achtergrond");
        $this->addRowsOptionValue($rows, $rowNumber, "Ethnisch/culturele achtergrond", "cult_ethn");
        /*
         * build rows for nationaliteit
         */
        $this->insertEmptyLine($rowNumber, $rows);
        $this->insertHeaderLine($rowNumber, $rows, "Nationaliteit");
        $this->addRowsText($rows, $rowNumber, "nationaliteit");
        /*
         * build rows for geslacht
         */
        $this->_optionGroupId = 3;
        $this->insertEmptyLine($rowNumber, $rows);
        $this->insertHeaderLine($rowNumber, $rows, "Geslacht:");
        $this->addRowsOptionValue($rows, $rowNumber, "", "geslacht");
    }
    function retrieveActivities($periodFrom, $periodTo, $locationValue) {
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
        require_once 'CRM/Utils/HilreportsUtils.php';
        $caseQry = "SELECT id FROM civicrm_case";
        $caseDAO = CRM_Core_DAO::executeQuery($caseQry);
        while ($caseDAO->fetch()) {
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
                            /*
                             * check if activity in period for case
                             */
                            $activityInPeriod = CRM_Utils_HilreportsUtils::checkActivityInCase($caseDAO->id, $periodFrom, $periodTo);
                            if ($activityInPeriod) {
                                $this->addRowContact($caseDAO->id, "Case");
                            }
                        }
                    }
                }
            } else {
                /*
                 * check if activity in period for case
                 */
                $activityInPeriod = CRM_Utils_HilreportsUtils::checkActivityInCase($caseDAO->id, $periodFrom, $periodTo);
                if ($activityInPeriod) {
                    $this->addRowContact($caseDAO->id, "Case");
                }
            }
        }
    }
    function addRowContact($entityId, $entityType) {
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
                        /*
                         * first check if contact does not already exist
                         */
                        $contactExists = false;
                        $checkContactExists =
"SELECT COUNT(*) AS aantal FROM selected_contacts WHERE contact_id = $contactId";
                        $daoCheckContactExists = CRM_Core_DAO::executeQuery($checkContactExists);
                        if ($daoCheckContactExists->fetch()) {
                            if ($daoCheckContactExists->aantal > 0) {
                                $contactExists = true;
                            }
                        }
                        if (!$contactExists) {
                            $fieldsSelected = array();
                            $fieldsSelected[] = "contact_id = {$apiContact['id']}";
                            $naam = CRM_Core_DAO::escapeString($apiContact['display_name']);
                            $fieldsSelected[] = "naam = '$naam'";
                            $adres = CRM_Core_DAO::escapeString($apiContact['street_address']);
                            $fieldsSelected[] = "adres = '$adres'";
                            $plaats = CRM_Core_DAO::escapeString($apiContact['city']);
                            $fieldsSelected[] = "plaats = '$plaats'";
                            $postCode = trim($apiContact['postal_code']);
                            $fieldsSelected[] = "postcode = '$postCode'";
                            $geslacht = CRM_Core_DAO::escapeString($apiContact['gender']);
                            $fieldsSelected[] = "geslacht = '$geslacht'";

                            require_once 'CRM/Utils/HilreportsUtils.php';
                            if (isset($apiContact['birth_date']) && !empty($apiContact['birth_date'])) {
                                $leeftijd = CRM_Utils_HilreportsUtils::calculateAge( $apiContact['birth_date']);
                                $fieldsSelected[] = "leeftijd = $leeftijd";
                            }
                            $econStatus = CRM_Core_DAO::escapeString(CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Economische status'));
                            $fieldsSelected[] = "econ_status = '$econStatus'";
                            $burgStaat = CRM_Core_DAO::escapeString(CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Burgerlijke staat'));
                            $fieldsSelected[] = "burg_staat = '$burgStaat'";
                            $landHerkomst = CRM_Core_DAO::escapeString(CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Land van herkomst'));
                            $fieldsSelected[] = "land_herkomst = '$landHerkomst'";
                            $culEthn = CRM_Core_DAO::escapeString(CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Ethnisch/culturele achtergrond'));
                            $fieldsSelected[] = "cult_ethn = '$culEthn'";
                            $nationaliteit = CRM_Core_DAO::escapeString(CRM_Utils_HilreportsUtils::getSingleCustomValue($contactId, 'Nationaliteit'));
                            $fieldsSelected[] = "nationaliteit = '$nationaliteit'";
                            $datumEerste = CRM_Utils_HilreportsUtils::getContactFirstDate($contactId);
                            $fieldsSelected[] = "datum_eerste = '$datumEerste'";
                            $aantalEnkel = CRM_Utils_HilreportsUtils::getCountEnkelvoudigeHulpvraag($contactId);
                            $fieldsSelected[] = "aantal_enkel = $aantalEnkel";
                            $aantalDossier = CRM_Utils_HilreportsUtils::getCountCases($contactId);
                            $fieldsSelected[] = "aantal_dossier = $aantalDossier";
                            $insertContact = "INSERT INTO selected_contacts SET ".implode(", ", $fieldsSelected);
                            CRM_Core_DAO::executeQuery($insertContact);
                        }
                    }
                }
            }
        }
    }
    function calculateAantalLocation($location) {
        return 15;
    }
    function calculateAantalContacts() {
        $aantalQry = "SELECT COUNT(*) AS aantalContacts FROM selected_contacts";
        $daoAantalContacts = CRM_Core_DAO::executeQuery($aantalQry);
        if ($daoAantalContacts->fetch()) {
            $this->_aantalContacts = $daoAantalContacts->aantalContacts;
        }
    }
    function insertEmptyLine(&$rowNumber, &$rows) {
        $rows[$rowNumber]['label'] = "";
        $rows[$rowNumber]['aantal'] = "";
        $rows[$rowNumber]['percentage'] = "";
        $rowNumber++;
    }
    function insertHeaderLine(&$rowNumber, &$rows, $headerString) {
        $rows[$rowNumber]['label'] = "<strong>$headerString</strong>";
        $rows[$rowNumber]['aantal'] = "";
        $rows[$rowNumber]['percentage'] = "";
        $rowNumber++;
    }
    function calculateAantalInContacts($name, $customField) {
        $aantalValues = 0;
        if (!empty($name) && !empty($customField)) {
            switch ($name) {
                case "none":
                    $aantalQry =
"SELECT COUNT(*) AS aantal FROM selected_contacts WHERE $customField IS NULL OR $customField = ''";
                    break;
                default:
                    $name = CRM_Core_DAO::escapeString($name);
                    $aantalQry =
"SELECT COUNT(*) AS aantal FROM selected_contacts WHERE $customField = '$name'";
                    break;
            }
            $daoSelected = CRM_Core_DAO::executeQuery($aantalQry);
            if ($daoSelected->fetch()) {
                $aantalValues = $daoSelected->aantal;
            }
        }
        return $aantalValues;
    }
    function statistics(&$rows) {
        $statistics = array();
        $count = $this->_aantalRijen;
        if ($this->_rollup && ($this->_rollup != '') && $this->_grandFlag) {
            $count++;
        }
        $this->countStat($statistics, $count);
        $this->groupByStat($statistics);
        $this->filterStat($statistics);
        return $statistics;
    }
    function addRowsLandVanHerkomst(&$rows, &$rowNumber) {
        $apiParams = array(
          'version'   =>  3,
          'options'   =>  array('limit'=>9999)
        );
        $apiCountries = civicrm_api('Country', 'Get', $apiParams);
        if ($apiCountries['is_error'] == 0) {
            foreach($apiCountries['values'] as $countryId => $apiCountry) {
                /*
                 * Calculate number of contacts in country, only print if any
                 */
                $aantalInCountry = $this->calculateAantalInContacts($apiCountry['name'], 'land_herkomst');
                if ($aantalInCountry > 0) {
                    $rows[$rowNumber]['label'] = ts($apiCountry['name']);
                    $rows[$rowNumber]['aantal'] = $aantalInCountry;
                    $rows[$rowNumber]['percentage'] = CRM_Utils_HilreportsUtils::calculatePercentage($aantalInCountry, $this->_aantalContacts)."%";
                    $rowNumber++;
                    $this->_aantalRijen++;
                }
            }
            /*
             * last time for no country
             */
            $aantalInCountry = $this->calculateAantalInContacts('none', 'land_herkomst');
            if ($aantalInCountry > 0) {
                $rows[$rowNumber]['label'] = 'Onbekend';
                $rows[$rowNumber]['aantal'] = $aantalInCountry;
                $rows[$rowNumber]['percentage'] = CRM_Utils_HilreportsUtils::calculatePercentage($aantalInCountry, $this->_aantalContacts)."%";
                $rowNumber++;
                $this->_aantalRijen++;
            }
        }
    }
    function addRowsOptionValue(&$rows, &$rowNumber, $customLabel, $customField) {
        /*
         * use custom group (Extra gegevens), then custom field (Burgerlijke staat)
         * to get option_group_id, then retrieve option values. Skip if option
         * group id already set
         */
        if ($this->_optionGroupId == 0) {
            $apiParams = array(
                'version'         =>  3,
                'custom_group_id' =>  $this->_customGroupId,
                'label'           =>  $customLabel
            );
            $apiCustomField = civicrm_api('CustomField', 'Getsingle', $apiParams);
            if (!isset($apiCustomField['is_error']) || $apiCustomField['is_error'] == 0) {
                if (isset($apiCustomField['option_group_id'])) {
                    $this->_optionGroupId = $apiCustomField['option_group_id'];
                }
            }
        }
        $apiParams = array(
            'version'         =>  3,
            'option_group_id' =>  $this->_optionGroupId
        );
        $apiOptionValues = civicrm_api('OptionValue', 'Get', $apiParams);
        if ($apiOptionValues['is_error'] == 0) {
            foreach($apiOptionValues['values'] as $optionValueId => $apiOptionValue) {
                /*
                 * Calculate number of contacts in econ_status, only print if any
                 */
                $aantalIn = $this->calculateAantalInContacts($apiOptionValue['label'], $customField);
                if ($aantalIn > 0) {
                    $rows[$rowNumber]['label'] = ts($apiOptionValue['label']);
                    $rows[$rowNumber]['aantal'] = $aantalIn;
                    $rows[$rowNumber]['percentage'] = CRM_Utils_HilreportsUtils::calculatePercentage($aantalIn, $this->_aantalContacts)."%";
                    $rowNumber++;
                    $this->_aantalRijen++;
                }
            }
            /*
             * last time for none
             */
            $aantalIn = $this->calculateAantalInContacts('none', $customField);
            if ($aantalIn > 0) {
                $rows[$rowNumber]['label'] = 'Onbekend';
                $rows[$rowNumber]['aantal'] = $aantalIn;
                $rows[$rowNumber]['percentage'] = CRM_Utils_HilreportsUtils::calculatePercentage($aantalIn, $this->_aantalContacts)."%";
                $rowNumber++;
                $this->_aantalRijen++;
            }
        }
    }
    function setCustomGroupIdExtraGegevens() {
        $apiParams = array(
          'version'   =>  3,
          'title'     =>  'Extra gegevens'
        );
        $apiCustomGroup = civicrm_api('CustomGroup', 'Getsingle', $apiParams);
        if (!isset($apiCustomGroup['is_error']) || $apiCustomGroup['is_error'] == 0) {
            $this->_customGroupId = $apiCustomGroup['id'];
        }
    }
    function addRowsText(&$rows, &$rowNumber, $customField) {
        if (!empty($customField)) {
            $distinctValuesQry = "SELECT DISTINCT($customField) AS distinctValue FROM selected_contacts";
            $daoDistinctValues = CRM_Core_DAO::executeQuery($distinctValuesQry);
            while ($daoDistinctValues->fetch()) {
                $aantalIn = $this->calculateAantalInContacts($daoDistinctValues->distinctValue, $customField);
                if ($aantalIn > 0) {
                    $rows[$rowNumber]['label'] = $daoDistinctValues->distinctValue;
                    $rows[$rowNumber]['aantal'] = $aantalIn;
                    $rows[$rowNumber]['percentage'] = CRM_Utils_HilreportsUtils::calculatePercentage($aantalIn, $this->_aantalContacts)."%";
                    $rowNumber++;
                    $this->_aantalRijen++;
                }
            }
            /*
             * last time for none
             */
            $aantalIn = $this->calculateAantalInContacts('none', $customField);
            if ($aantalIn > 0) {
                $rows[$rowNumber]['label'] = 'Onbekend';
                $rows[$rowNumber]['aantal'] = $aantalIn;
                $rows[$rowNumber]['percentage'] = CRM_Utils_HilreportsUtils::calculatePercentage($aantalIn, $this->_aantalContacts)."%";
                $rowNumber++;
                $this->_aantalRijen++;
            }
        }
    }
}
