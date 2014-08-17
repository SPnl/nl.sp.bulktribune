<?php

class nl_sp_bulktribune extends CRM_Report_Form {

	protected $_addressField = FALSE;
	protected $_emailField = FALSE;
	protected $_summary = NULL;
	protected $_customGroupExtends = array('Membership');
	protected $_customGroupGroupBy = FALSE; 
	protected $_memberships;

	function __construct() {
		$this->_groupFilter = FALSE;
		$this->_tagFilter = FALSE;
		parent::__construct();
		$this->fetchCustom();
	}

	function fetchCustom() {
		try{
			$this->_memberships 						= new stdClass();
			$this->_memberships->proef 					= civicrm_api3('MembershipType', 'getsingle', array("name" => "Abonnee Blad-Tribune Proef"));
			$this->_memberships->gratis 				= civicrm_api3('MembershipType', 'getsingle', array("name" => "Abonnee Blad-Tribune Gratis"));
			$this->_memberships->betaald 				= civicrm_api3('MembershipType', 'getsingle', array("name" => "Abonnee Blad-Tribune Betaald"));
			$this->_location_type 						= new stdClass();
			$this->_location_type->tribune 				= civicrm_api3('LocationType', 'getsingle', array("name" => "Tribuneadres"));
			$this->_custom_fields 						= new stdClass();
			$this->_custom_fields->group 				= civicrm_api3('CustomGroup', 'getsingle', array("name" => "Bezorggebieden"));
			$this->_custom_fields->name 				= civicrm_api3('CustomField', 'getsingle', array("name" => "Bezorggebied_naam", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->start_cijfer_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "start_cijfer_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->eind_cijfer_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "eind_cijfer_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->start_letter_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "start_letter_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->eind_letter_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "eind_letter_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->per_post 			= civicrm_api3('CustomField', 'getsingle', array("name" => "Per_Post", "custom_group_id" => $this->_custom_fields->group['id']));
		} catch (Exception $e) {
			echo "<h1>Er gaat iets mis!</h1>";
			echo $e;
			die();
		}
	}

	function preProcess() {
		$this->assign('reportTitle', ts('Membership Detail Report'));
		parent::preProcess();
	}

	function postProcess() {
		$this->beginPostProcess();
		$this->_columnHeaders = array(
			'contact_id' => array("title" => 'Lidnummer'), 
			'display_name' => array("title" => 'Naam'), 
			'street' => array("title" => 'Straat'), 
			'zipcode' => array("title" => 'Postcode'), 
			'city' => array("title" => 'Stad'), 
			'organization_name' => array("title" => 'Afdeling'),
			'deliver_area_name' => array("title" => "Bezorggebied"),
			'deliver_per_post' => array("title" => "Per Post"),
			'deliver_area_range' => array("title" => "Postcode range")
		);
		$query = "
			SELECT
				`cm`.`contact_id` as `contact_id`,
				`cc`.`display_name` as `display_name`,
				`dpc`.`organization_name` as `organization_name`,
				`cbzg`.`".$this->_custom_fields->name['column_name']."` as `deliver_area_name`,
				`cbzg`.`entity_id` as `entity_id`,
				`cbzg`.`".$this->_custom_fields->per_post['column_name']."` as `deliver_per_post`,
				`ca`.`street_address` as `street`,
				`ca`.`postal_code` as `zipcode`,
				`ca`.`city` as `city`,
				CONCAT(`cbzg`.`".$this->_custom_fields->start_cijfer_range['column_name']."`,' ',`cbzg`.`".$this->_custom_fields->start_letter_range['column_name']."`,' - ',`cbzg`.`".$this->_custom_fields->eind_cijfer_range['column_name']."`,' ',`cbzg`.`".$this->_custom_fields->eind_letter_range['column_name']."`) as `deliver_area_range`
			FROM `civicrm_membership` as `cm`
			LEFT JOIN `civicrm_contact` as `cc` ON `cm`.`contact_id` = `cc`.`id`
			LEFT JOIN `civicrm_address` as `ca` ON `ca`.`contact_id` = `cc`.`id` AND `ca`.`is_primary` = 1
			LEFT JOIN `".$this->_custom_fields->group['table_name']."` as `cbzg` ON 
			( 
				(SUBSTR(REPLACE(`ca`.`postal_code`, ' ', ''), 1, 4) BETWEEN `cbzg`.`".$this->_custom_fields->start_cijfer_range['column_name']."` AND `cbzg`.`".$this->_custom_fields->eind_cijfer_range['column_name']."`)
					AND
				(SUBSTR(REPLACE(`ca`.`postal_code`, ' ', ''), -2) BETWEEN `cbzg`.`".$this->_custom_fields->start_letter_range['column_name']."` AND `cbzg`.`".$this->_custom_fields->eind_letter_range['column_name']."`)
			)
			LEFT JOIN `civicrm_contact` as `dpc` ON `cbzg`.`entity_id` = `dpc`.`id`
			WHERE (`cm`.`status_id` IN (1, 2)) AND (`cm`.`membership_type_id` IN ('".$this->_memberships->proef['id']."','".$this->_memberships->gratis['id']."','".$this->_memberships->betaald['id']."')) 
			ORDER BY `dpc`.`organization_name` DESC, `ca`.`city` ASC, `ca`.`postal_code` ASC, `cbzg`.`".$this->_custom_fields->start_cijfer_range['column_name']."`, `cbzg`.`".$this->_custom_fields->start_letter_range['column_name']."`, `cc`.`display_name`
			LIMIT 100
		";
		$rows = array();
		$this->buildRows($query, $rows);
		$this->doTemplateAssignment($rows);
		$this->endPostProcess($rows);
	}

	
}