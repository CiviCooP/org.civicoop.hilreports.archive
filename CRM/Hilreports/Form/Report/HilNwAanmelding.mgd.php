<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Hilreports_Form_Report_HilNwAanmelding',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'Nieuwe Aanmelding',
      'description' => 'Rapport en dashlet Nieuwe Aanmelding',
      'class_name' => 'CRM_Hilreports_Form_Report_HilNwAanmelding',
      'report_url' => 'civicrm/report/nw_aanmelding',
      'component' => 'CiviCase',
    ),
  ),
);