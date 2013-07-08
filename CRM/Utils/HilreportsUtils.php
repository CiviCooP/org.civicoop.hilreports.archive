<?php
/*
+--------------------------------------------------------------------+
| Project       :   CiviCRM Het Inter-lokaal HilReports              |
| Author        :   Erik Hommel (CiviCooP, erik.hommel@civicoop.org  |
| Date          :   5 july 2013                                      |
| Description   :   Class with HIL reports helper functions                  |
+--------------------------------------------------------------------+
*/

/**
*
* @package CRM
* @copyright CiviCRM LLC (c) 2004-2013
* $Id$
*
*/
class CRM_Utils_HilreportsUtils {
    /**
     * Static function to calculate age with a birth date
     * @author Erik Hommel (erik.hommel@civicoop.org)
     * @param birthDate string
     * @return Age integer
     */
    static function calculateAge($birthDate) {
        if (empty($birthDate)) {
            return 0;
        }
        $birthDay = new DateTime($birthDate);
        $toDay = new DateTime(date('Y-m-d'));
        $dateDiff = $toDay->diff($birthDay);
        $age = $dateDiff->y;
        return $age;
    }
    /**
     * Static function to retrieve custom value with entity id and field label
     * @author Erik Hommel (erik.hommel@civicoop.org)
     * @param $entityId, $customFieldLabel, $entityType
     * @return $result array
     */
    static function getSingleCustomValue( $entityId, $customFieldLabel, $entityType = "Contact") {
        $value = '';
        if (empty($entityId) || empty($customFieldLabel)) {
            return $value;
        }
        $apiParams = array(
            'version'       =>  3,
            'entity_id'     =>  $entityId,
            'entity_table'  =>  $entityType
        );
        $apiCustomValues = civicrm_api('CustomValue', 'Get', $apiParams);
        if (isset($apiCustomValues['is_error']) && $apiCustomValues['is_error'] == 0) {
            foreach ($apiCustomValues['values'] as $customId => $customValue) {
                if ($customId != 0) {
                    $apiParams = array(
                        'version'   =>  3,
                        'id'        =>  $customId
                    );
                    $apiCustomField = civicrm_api('CustomField', 'Getsingle', $apiParams);
                    if (!isset($apiCustomField['is_error']) || $apiCustomField['is_error'] == 0) {
                        if (isset($apiCustomField['label']) && $apiCustomField['label'] == $customFieldLabel) {
                            /*
                             * Further processing depending on data type
                             */
                            switch($apiCustomField['data_type']) {
                                case "Country":
                                    $value = $customValue['latest'];
                                    $apiParams = array(
                                        'version'   =>  3,
                                        'id'        =>  $customValue['latest']
                                    );
                                    $apiCountries = civicrm_api('Country','Get', $apiParams);
                                    if (isset($apiCountries['is_error']) && $apiCountries['is_error'] == 0) {
                                        foreach ($apiCountries['values'] as $countryId => $apiCountry) {
                                            if(isset($apiCountry['name'])) {
                                                $value = ts($apiCountry['name']);
                                            }
                                        }
                                    }
                                    break;
                                case "String":
                                    /*
                                     * Process depending on html_type
                                     */
                                    switch($apiCustomField['html_type']) {
                                        case "Select":
                                            $apiParams = array(
                                                'version'           =>  3,
                                                'option_group_id'   =>  $apiCustomField['option_group_id'],
                                                'value'             =>  $customValue['latest']
                                            );
                                            $apiOptionValues = civicrm_api('OptionValue', 'Getsingle', $apiParams);
                                            if (!isset($apiOptionValues['is_error']) || $apiOptionValues['is_error'] == 0 ) {
                                                if (isset($apiOptionValues['label'])) {
                                                    $value = $apiOptionValues['label'];
                                                } else {
                                                    $value = $customValue['latest'];
                                                }
                                            }
                                            break;
                                        case "CheckBox":
                                            $apiParams = array(
                                                'version'           =>  3,
                                                'option_group_id'   =>  $apiCustomField['option_group_id'],
                                                'value'             =>  $customValue['latest'][0]
                                            );
                                            $apiOptionValues = civicrm_api('OptionValue', 'Getsingle', $apiParams);
                                            if (!isset($apiOptionValues['is_error']) || $apiOptionValues['is_error'] == 0 ) {
                                                if (isset($apiOptionValues['label'])) {
                                                    $value = $apiOptionValues['label'];
                                                } else {
                                                    $value = $customValue['latest'][0];
                                                }
                                            }
                                            break;
                                        default:
                                            $value = $customValue['latest'];
                                    }
                                    break;
                                default:
                                    $value = $customValue['latest'];
                            }
                        }
                    }
                }
            }
        }
        return $value;
    }
    /**
     * Static function to retrieve activity type id for Enkelvoude Hulpvraag
     * @author Erik Hommel (erik.hommel@civicoop.org)
     * @param none
     * @return $actTypeId
     */
    static function getEnkelvoudigeHulpvraagTypeId() {
        $actTypeId = 0;
        $apiParams = array(
            'version'           =>  3,
            'option_group_id'   =>  2,
            'label'             =>  "Enkelvoudige Hulpvraag"
        );
        $actType = civicrm_api('OptionValue', 'Getsingle', $apiParams);
        if (isset($actType['is_error']) || $actType['is_error'] == 0) {
            $actTypeId = $actType['value'];
        }
        return $actTypeId;
    }
    /**
     * Static function to retrieve the first contact date for a customer
     * first contact date means the first date of any case or activity Enkelvoudige
     * Hulpvraag
     * @author Erik Hommel (erik.hommel@civicoop.org)
     * @param contactId
     * @return $firstContactDate
     */
    static function getContactFirstDate($contactId) {
        $firstContactDate = '';
        if (empty($contactId)) {
            return $firstContactDate;
        }
        /*
         * retrieve first date for contact for activities
         */
        $apiParams = array(
            'version'           =>  3,
            'activity_type_id'  =>  self::getEnkelvoudigeHulpvraagTypeId(),
            'contact_id'        =>  $contactId,
        );
        $apiActivities = civicrm_api('Activity', 'Get', $apiParams);
        $firstContactDate = new DateTime(date('Y-m-d'));
        foreach ($apiActivities['values'] as $actId => $apiActivity) {
            if (isset($apiActivity['activity_date_time'])) {
                $actDate = new DateTime($apiActivity['activity_date_time']);
                if ($actDate <= $firstContactDate) {
                    $firstContactDate = $actDate;
                }
            }
        }
        /*
         * check if there is an earlier date in cases for contact
         */
        $apiParams = array(
            'version'   =>  3,
            'client_id' =>  $contactId
        );
        $apiCases = civicrm_api('Case', 'Get', $apiParams);
        foreach($apiCases['values'] as $caseId => $apiCase) {
            if (isset($apiCase['start_date'])) {
                $startDate = new DateTime($apiCase['start_date']);
                if ($startDate <= $firstContactDate) {
                    $firstContactDate = $startDate;
                }
            }
        }
        return $firstContactDate->format('Y-m-d H:i:s');
    }
    /**
     * Static function to get number of Enkelvoudige Hulpvraag for contact
     * @author Erik Hommel (erik.hommel@civicoop.org)
     * @param $contactId
     * @return $countAct
     */
    static function getCountEnkelvoudigeHulpvraag($contactId) {
        $countAct = 0;
        $apiParams = array(
            'version'   =>  3,
            'contact_id'=>  $contactId
        );
        $actTypeId = self::getEnkelvoudigeHulpvraagTypeId();
        $apiActs = civicrm_api('Activity', 'Get', $apiParams);
        foreach ($apiActs['values'] as $actId => $apiAct) {
            if ($apiAct['activity_type_id'] == $actTypeId) {
                if (isset($apiAct['targets'])) {
                    if (key($apiAct['targets']) == $contactId) {
                        $countAct++;
                    }
                }
            }
        }
        return $countAct;
    }
    /**
     * Static function to get number of Cases for contact
     * @author Erik Hommel (erik.hommel@civicoop.org)
     * @param $contactId
     * @return $countCases
     */
    static function getCountCases($contactId) {
        $countCases = 0;
        $apiParams = array(
            'version'       =>  3,
            'client_id'     =>  $contactId
        );
        $apiCaseCount = civicrm_api('Case', 'Getcount', $apiParams);
        if (!isset($apiCaseCount['is_error'])) {
            $countCases = $apiCaseCount;
        }
        return $countCases;
    }
}