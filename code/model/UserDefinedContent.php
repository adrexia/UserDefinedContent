<?php

/**
 * @package UserContent
 */

class UserDefinedContent extends Page {
	
	/**
	 * @var string
	 */
	private static $description = 'Adds a page with editable content sections.';

	/**
	 * @var string Required Identifier
	 */
	private static $required_identifier = null;
	
	/**
	 * @var array Fields on the user defined form page.
	 */
	private static $db = array(
	
	);
	
	/**
	 * @var array Default values of variables when this page is created
	 */ 
	private static $defaults = array(
		'Content' => '$UserDefinedContent'
	);

	/**
	 * @var array
	 */
	private static $has_many = array(
		"Fields" => "EditableContentField"
	);

	/**
	 * Temporary storage of field ids when the form is duplicated.
	 * Example layout: array('EditableCheckbox3' => 'EditableCheckbox14')
	 * @var array
	 */
	protected $fieldsFromTo = array();

	/**
	 * @return FieldList
	 */
	public function getCMSFields() {
		// call updateCMSFields after 
		SiteTree::disableCMSFieldsExtensions();
		$fields = parent::getCMSFields();
		SiteTree::enableCMSFieldsExtensions();
		// define tabs
		$fields->findOrMakeTab('Root.ContentBlocks', _t('UserDefinedContent.ContentBlocks', 'ContentBlocks'));

		// field editor
		$fields->addFieldToTab("Root.ContentBlocks", new ContentFieldEditor("Fields", 'Fields', "", $this ));


		// make sure a numeric not a empty string is checked against this int column for SQL server
		$parentID = (!empty($this->ID)) ? $this->ID : 0;

		$this->extend('updateCMSFields', $fields);
		
		return $fields;
	}
	
	
	/**
	 * When publishing copy the editable form fields to the live database
	 * Not going to version emails and submissions as they are likely to 
	 * persist over multiple versions.
	 *
	 * @return void
	 */
	public function doPublish() {
		$parentID = (!empty($this->ID)) ? $this->ID : 0;
		// remove fields on the live table which could have been orphaned.
		$live = Versioned::get_by_stage("EditableFormField", "Live", "\"EditableFormField\".\"ParentID\" = $parentID");

		if($live) {
			foreach($live as $field) {
				$field->doDeleteFromStage('Live');
			}
		}

		// publish the draft pages
		if($this->Fields()) {
			foreach($this->Fields() as $field) {
				$field->doPublish('Stage', 'Live');
			}
		}

		parent::doPublish();
	}
	
	/**
	 * When un-publishing the page it has to remove all the fields from the 
	 * live database table.
	 *
	 * @return void
	 */
	public function doUnpublish() {
		if($this->Fields()) {
			foreach($this->Fields() as $field) {
				$field->doDeleteFromStage('Live');
			}
		}
		
		parent::doUnpublish();
	}
	
	/**
	 * Roll back a form to a previous version.
	 *
	 * @param string|int Version to roll back to
	 */
	public function doRollbackTo($version) {
		parent::doRollbackTo($version);
		
		/*
			Not implemented yet 
	
		// get the older version
		$reverted = Versioned::get_version($this->ClassName, $this->ID, $version);
		
		if($reverted) {
			
			// using the lastedited date of the reverted object we can work out which
			// form fields to revert back to
			if($this->Fields()) {
				foreach($this->Fields() as $field) {
					// query to see when the version of the page was pumped
					$editedDate = DB::query("
						SELECT LastEdited
						FROM \"SiteTree_versions\"
						WHERE \"RecordID\" = '$this->ID' AND \"Version\" = $version
					")->value(); 
					

					// find a the latest version which has been edited
					$versionToGet = DB::query("
						SELECT *
						FROM \"EditableFormField_versions\" 
						WHERE \"RecordID\" = '$field->ID' AND \"LastEdited\" <= '$editedDate'
						ORDER BY Version DESC
						LIMIT 1
					")->record();

					if($versionToGet) {
						Debug::show('publishing field'. $field->Name);
						Debug::show($versionToGet);
						$field->publish($versionToGet, "Stage", true);
						$field->writeWithoutVersion();
					}
					else {
						Debug::show('deleting field'. $field->Name);
						$this->Fields()->remove($field);
						
						$field->delete();
						$field->destroy();
					}
				}
			}
			
			// @todo Emails
		}
		*/
	}
	
	/**
	 * Revert the draft site to the current live site
	 *
	 * @return void
	 */
	public function doRevertToLive() {
		if($this->Fields()) {
			foreach($this->Fields() as $field) {
				$field->publish("Live", "Stage", false);
				$field->writeWithoutVersion();
			}
		}
		
		parent::doRevertToLive();
	}



	/**
	 * Store new and old ids of duplicated fields.
	 * This method also serves as a hook for descendant classes.
	 */
	protected function afterDuplicateField($page, $fromField, $toField) {
		$this->fieldsFromTo[$fromField->ClassName . $fromField->ID] = $toField->ClassName . $toField->ID;
	}


	/**
	 * Duplicate this UserDefinedContent page, and its form fields.
	 * Submissions, on the other hand, won't be duplicated.
	 *
	 * @return Page
	 */
	public function duplicate($doWrite = true) {
		$page = parent::duplicate($doWrite);
		
		// the form fields
		if($this->Fields()) {
			foreach($this->Fields() as $field) {
				$newField = $field->duplicate();
				$newField->ParentID = $page->ID;
				$newField->write();
				$this->afterDuplicateField($page, $field, $newField);
			}
		}
		
		
		// Rewrite CustomRules
		if($page->Fields()) {
			foreach($page->Fields() as $field) {
				// Rewrite name to make the CustomRules-rewrite below work.
				$field->Name = $field->ClassName . $field->ID;
				$rules = unserialize($field->CustomRules);

				if (count($rules) && isset($rules[0]['ConditionField'])) {
					$from = $rules[0]['ConditionField'];

					if (array_key_exists($from, $this->fieldsFromTo)) {
						$rules[0]['ConditionField'] = $this->fieldsFromTo[$from];
						$field->CustomRules = serialize($rules);
					}
				}

				$field->Write();
			}
		}

		return $page;
	}
	
	/**
	 * Return if this form has been modified on the stage site and not published.
	 * this is used on the workflow module and for a couple highlighting things
	 *
	 * @return boolean
	 */
	public function getIsModifiedOnStage() {
		// new unsaved pages could be never be published
		if($this->isNew()) {
			return false;
		}

		$stageVersion = Versioned::get_versionnumber_by_stage('UserDefinedContent', 'Stage', $this->ID);
		$liveVersion =	Versioned::get_versionnumber_by_stage('UserDefinedContent', 'Live', $this->ID);

		$isModified = ($stageVersion && $stageVersion != $liveVersion);

		if(!$isModified) {
			if($this->Fields()) {
				foreach($this->Fields() as $field) {
					if($field->getIsModifiedOnStage()) {
						$isModified = true;
						break;
					}
				}
			}
		}
		return $isModified;
	}
}

/**
 * Controller for the {@link UserDefinedContent} page type.
 *
 * @package UserContent
 */

class UserDefinedContent_Controller extends Page_Controller {
	
	private static $allowed_actions = array(
		'index',
		'ping',
		'finished'
	);

	public function init() {
		parent::init();
		
		// load the jquery
		$lang = i18n::get_lang_from_locale(i18n::get_locale());
		Requirements::javascript(FRAMEWORK_DIR .'/thirdparty/jquery/jquery.js');
		Requirements::javascript(USERCONTENT_DIR . '/thirdparty/jquery-validate/jquery.validate.min.js');
		Requirements::add_i18n_javascript(USERCONTENT_DIR . '/javascript/lang');
		Requirements::javascript(USERCONTENT_DIR . '/javascript/UserContent_frontend.js');

	}
	
	/**
	 * Using $UserDefinedContent in the Content area of the page shows
	 * where the content should be rendered into. 
	 *
	 * @return array
	 */
	public function index() {
		if($this->Content && $form = $this->UserContentBlocks()) {
			$hasLocation = stristr($this->Content, '$UserDefinedContent');
			if($hasLocation) {
				$content = str_ireplace('$UserDefinedContent', $form->forTemplate(), $this->Content);
				return array(
					'Content' => DBField::create_field('HTMLText', $content)
				);
			}
		}
	}

		/**
	 * Get the form for the page. Form can be modified by calling {@link updateForm()}
	 * on a UserDefinedForm extension.
	 *
	 * @return Form|false
	 */
	public function UserContentBlocks() {
		$fields = $this->getFormFields();
		return $fields;
	}

	/**
	 * Keep the session alive for the user.
	 *
	 * @return int
	 */
	public function ping() {
		return 1;
	}

	/**
	 * Get the form fields for the form on this page. Can modify this FieldSet
	 * by using {@link updateFormFields()} on an {@link Extension} subclass which
	 * is applied to this controller.
	 *
	 * @return FieldList
	 */
	public function getFormFields() {
		$fields = new FieldList();
				
		if($this->Fields()) {
			foreach($this->Fields() as $editableField) {
				// get the raw form field from the editable version
				$field = $editableField->getFormField();
				if(!$field) break;

				$fields->push($field);
			}
		}
		$this->extend('updateFormFields', $fields);

		return $fields;
	}
	
	/**
	 * Convert a PHP array to a JSON string. We cannot use {@link Convert::array2json}
	 * as it escapes our values with "" which appears to break the validate plugin
	 *
	 * @param Array array to convert
	 * @return JSON 
	 */
	public function array2json($array) {
		foreach($array as $key => $value) {
			if(is_array( $value )) {
				$result[] = "$key:" . $this->array2json($value);
			} else {
				$value = (is_bool($value)) ? $value : "\"$value\"";
				$result[] = "$key:$value";
			}
		}

		return (isset($result)) ? "{\n".implode( ', ', $result ) ."\n}\n": '{}';
	}
	

	/**
	 * This action handles rendering the "finished" message, which is 
	 * customizable by editing the ReceivedFormSubmission template.
	 *
	 * @return ViewableData
	 */
	public function finished() {
		$referrer = isset($_GET['referrer']) ? urldecode($_GET['referrer']) : null;
		
		$formProcessed = Session::get('FormProcessed');
		if (!isset($formProcessed)) {
			return $this->redirect($this->Link() . $referrer);
		} else {
			$securityID = Session::get('SecurityID');
			// make sure the session matches the SecurityID and is not left over from another form
			if ($formProcessed != $securityID) {
				// they may have disabled tokens on the form
				$securityID = md5(Session::get('FormProcessedNum'));
				if ($formProcessed != $securityID) {
					return $this->redirect($this->Link() . $referrer);
				}
			}
		}
		// remove the session variable as we do not want it to be re-used
		Session::clear('FormProcessed');

		return $this->customise(array(
			'Content' => $this->customise(
				array(
					'Link' => $referrer
				))->renderWith('ReceivedFormSubmission'),
			'Form' => '',
		));
	}
}
