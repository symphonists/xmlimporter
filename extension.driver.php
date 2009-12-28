<?php
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');
	
	class Extension_XmlImporter extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'XML Importer',
				'version'		=> '0.1',
				'release-date'	=> '2009-12-28',
				'author'		=> array(
					'name'			=> 'Nick Dunn, Rowan Lewis'
				),
				'description' => 'Import data from XML documents directly into Symphony.'
			);
		}
				
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'InitaliseAdminPageHead',
					'callback'	=> 'initializeAdmin'
				)
			);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 200,
					'name'		=> 'Importers',
					'link'		=> '/importers/'
				),
				array(
					'location'	=> 300,
					'name'		=> 'Import',
					'link'		=> '/import/'
				)
			);
		}
		
		public function initializeAdmin($context) {
			$page = $context['parent']->Page;
			
			//$page->addStylesheetToHead(URL . '/extensions/issuemanager/assets/form.css', 'screen');
		}
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		public function truncateValue($value) {
			$max_length = $this->_Parent->Configuration->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);
			
			$value = General::sanitize($value);
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');
			
			return $value;
		}
	}
	
?>