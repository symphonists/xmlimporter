<?php
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.importer.php');
	
	class import!CLASS_NAME! extends Importer {
		
		public function __construct(&$parent) {
			parent::__construct($parent);
		}
		
		public function about() {
			return array(
				'name' => '!NAME!'
			);	
		}
		
		public function getSection() {
			return !SECTION!;
		}
		
		public function getRootExpression() {
			return '!ROOT_EXPRESSION!';
		}
		
		public function getUniqueField() {
			return !UNIQUE_FIELD!;
		}
		
		public function canUpdate() {
			return !CAN_UPDATE!;
		}
		
		public function getFieldMapping() {
			return !MAPPING!;
		}
		
		public function allowEditorToParse() {
			return true;
		}
		
	}
	
?>