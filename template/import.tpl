<?php
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.importer.php');
	
	class import/* CLASS_NAME */ extends Importer {
		
		public function __construct(&$parent, $env = null, $process_params = true) {
			parent::__construct($parent, $env, $process_params);
		}
		
		public function getSource() {
			return '/* SOURCE */';
		}
		
		public function allowEditorToParse() {
			return true;
		}
		
		public function import() {
			$result = new XMLElement($this->dsParamROOTELEMENT);
			
			try{
				/* GRAB */
				
			} catch (Exception $e) {
				$result->appendChild(new XMLElement('error', $e->getMessage()));
				
				return $result;
			}	
			
			return $result;
		}
	}
	
?>