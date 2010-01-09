<?php
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');
	
	class XMLImporter%s extends XMLImporter {
		public function __construct(&$parent) {
			parent::__construct($parent);
		}
		
		public function about() {
			return array(
				'name'			=> %s,
				'author'		=> array(
					'name'			=> %s,
					'website'		=> %s,
					'email'			=> %s
				),
				'description'	=> %s,
				'file'			=> __FILE__,
				'created'		=> %s,
				'updated'		=> %s
			);	
		}
		
		public function getSection() {
			return %s;
		}
		
		public function getRootExpression() {
			return %s;
		}
		
		public function getUniqueField() {
			return %s;
		}
		
		public function canUpdate() {
			return %s;
		}
		
		public function getFieldMapping() {
			return %s;
		}
		
		public function allowEditorToParse() {
			return true;
		}
	}
	
?>
