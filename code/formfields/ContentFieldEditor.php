<?php
/**
 * A form field allowing a user to customize and create form fields.
 * for saving into a {@link UserDefinedForm}
 *
 * @package userforms
 */

class ContentFieldEditor extends FormField {

	private static $url_handlers = array(
		'$Action!/$ID' => '$Action'
	);

	private static $allowed_actions = array(
		'addfield',
		'handleField'
	);
	
	/**
	 * @param array $properties
	 *
	 * @return HTML
	 */
	public function FieldHolder($properties = array()) {
		$add = Controller::join_links($this->Link('addfield'));

		$this->setAttribute('data-add-url', '\''. $add.'\'');

		return $this->renderWith("ContentFieldEditor");
	}
	
	/**
	 * Returns whether a user can edit the form.
	 *
	 * @param Member $member
	 *
	 * @return boolean
	 */
	public function canEdit($member = null) {
		if($this->readonly) {
			return false;
		}
		
		return $this->form->getRecord()->canEdit();
	}
	
	/**
	 * Returns whether a user delete a field in the form. The
	 * {@link EditableContentField} instances check if they can delete themselves
	 * but this counts as an {@link self::canEdit()} function rather than a
	 * delete.
	 *
	 * @param Member $member
	 * @return boolean
	 */
	public function canDelete($member = null) {
		if($this->readonly) return false;
		
		return $this->form->getRecord()->canEdit();
	}

	/** @todo: needed ?
	 * Transform this form field to a readonly version.
	 *
	 * @return ViewableData_Customised
	 */
	public function performReadonlyTransformation() {
		$clone = clone $this;
		$clone->readonly = true;
		$fields = $clone->Fields();

		if($fields) {
			foreach($fields as $field) {
				$field->setReadonly();
			}
		}
		
		return $clone->customise(array(
			'Fields' => $fields
		));
	}
	
	/**
	 * Return the fields.
	 * 
	 * @return RelationList
	 */
	public function Fields() {
		if($this->form && $this->form->getRecord() && $this->name) {
			$relationName = $this->name;
			$fields = $this->form->getRecord()->getComponents($relationName);
		
			if($fields) {
				foreach($fields as $field) {
					if(!$this->canEdit() && is_a($field, 'FormField')) {
						$fields->remove($field);
						$fields->push($field->performReadonlyTransformation());
					}
				}
			}
			
			return $fields;
		}
	}
	
	/**
	 * Return a {@link ArrayList} of all the addable fields to populate the add
	 * field menu.
	 * 
	 * @return ArrayList
	 */
	public function CreatableFields() {
		$fields = ClassInfo::subclassesFor('EditableContentField');

		if($fields) {
			array_shift($fields); // get rid of subclass 0
			asort($fields); // get in order

			$output = new ArrayList();

			foreach($fields as $field => $title) {
				// get the nice title and strip out field
				$niceTitle = _t(
					$field.'.SINGULARNAME', 
					$title
				);

				if($niceTitle) {
					$output->push(new ArrayData(array(
						'ClassName' => $field,
						'Title' => "$niceTitle"
					)));
				}
			}

			return $output;
		}

		return false;
	}

	/**
	 * Handles saving the page. Needs to keep an eye on fields and options which
	 * have been removed / added
	 *
	 * @param DataObject $record
	 */
	public function saveInto(DataObjectInterface $record) {
		$name = $this->name;
		$fieldSet = $record->$name();
		
		// store the field IDs and delete the missing fields
		// alternatively, we could delete all the fields and re add them
		$missingFields = array();

		foreach($fieldSet as $existingField) {
			$missingFields[$existingField->ID] = $existingField;
		}

		if(isset($_REQUEST[$name]) && is_array($_REQUEST[$name])) {
			foreach($_REQUEST[$name] as $newEditableID => $newEditableData) {
				if(!is_numeric($newEditableID)) continue;
				
				// get it from the db
				$editable = DataObject::get_by_id('EditableContentField', $newEditableID); 

				// if it exists in the db update it
				if($editable) {
			
					// remove it from the removed fields list
					if(isset($missingFields[$editable->ID]) && isset($newEditableData) && is_array($newEditableData)) {
						unset($missingFields[$editable->ID]);
					}

					// set form id
					if($editable->ParentID == 0) {
						$editable->ParentID = $record->ID;
					}
					
					// save data
					$editable->populateFromPostData($newEditableData);
				}
			}
		}

		// remove the fields not saved
		if($this->canEdit()) {
			foreach($missingFields as $removedField) {
				if(is_numeric($removedField->ID)) {
					// check we can edit this
					$removedField->delete();
				}
			}
		}
	}
	
	/**
	 * Add a field to the field editor. Called via a ajax get.
	 *
	 * @return bool|html
	 */
	public function addfield() {
		if(!SecurityToken::inst()->checkRequest($this->request)) {
			return $this->httpError(400);
		}

		// get the last field in this form editor
		$parentID = $this->form->getRecord()->ID;
		
		if($parentID) {
			$parentID = (int)$parentID;
			
			$sqlQuery = new SQLQuery();
			$sqlQuery = $sqlQuery
				->setSelect("MAX(\"Sort\")")
				->setFrom("\"EditableContentField\"")
				->setWhere("\"ParentID\" = $parentID");

			$sort = $sqlQuery->execute()->value() + 1;

			$className = (isset($_REQUEST['Type'])) ? $_REQUEST['Type'] : '';
			
			if(!$className) {
				user_error('Please select a field type to created', E_USER_WARNING);
			}

			if(is_subclass_of($className, "EditableContentField")) {
				$field = new $className();
				$field->ParentID = $this->form->getRecord()->ID;
				$field->Name = $field->class . $field->ID;
				$field->Sort = $sort;
				$field->write();
			
				return $field->EditSegment();
			}
		}

		return false;
	}

	/**
	 * Pass sub {@link FormField} requests through the editor. For example,
	 * option fields need to be able to call themselves.
	 *
	 * @param SS_HTTPRequest
	 */
	public function handleField(SS_HTTPRequest $request) {
		if(!SecurityToken::inst()->checkRequest($this->request)) {
			return $this->httpError(400);
		}

		$fields = $this->Fields();

		// extract the ID and option field name
		preg_match(
			'/Fields\[(?P<ID>\d+)\]\[CustomSettings\]\[(?P<Option>\w+)\]/',
			$request->param('ID'), $matches
		);

		if(isset($matches['ID']) && isset($matches['Option'])) {
			foreach($fields as $field) {
				$formField = $field->getFormField();

				if($matches['ID'] == $field->ID) {
					if($field->canEdit()) {
						// find the option to handle
						$options = $field->getFieldConfiguration();
						$optionField = $options->fieldByName($request->param('ID'));

						if($optionField) {
							return $optionField->handleRequest($request, $optionField);
						} else {
							return $this->httpError(404);
						}
					}
				}
			}
		}

		return $this->httpError(403);
	}
}
