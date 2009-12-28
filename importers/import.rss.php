<?php
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.importer.php');
	
	class importrss extends Importer {
		
		public function __construct(&$parent) {
			parent::__construct($parent);
		}
		
		public function about() {
			return array(
				'name' => 'RSS'
			);	
		}
		
		public function getSection() {
			return 16;
		}
		
		public function getRootExpression() {
			return '//item';
		}
		
		public function getUniqueField() {
			return 81;
		}
		
		public function canUpdate() {
			return true;
		}
		
		public function getFieldMapping() {
			return array(
array(
 'field' => 81, 
 'xpath' => 'title/text()', 
 'php' => ''
),
array(
 'field' => 82, 
 'xpath' => 'description/text()', 
 'php' => 'FormattingHelpers::markdownify'
),
array(
 'field' => 83, 
 'xpath' => 'pubDate/text()', 
 'php' => 'date(\"d M Y\", strtotime($value));'
),
);
		}
		
		public function allowEditorToParse() {
			return true;
		}
		
	}
	
?>