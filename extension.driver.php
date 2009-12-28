<?php
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');
	
	class Extension_XmlImporter extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Import Manager',
				'version'		=> '1.002',
				'release-date'	=> '2009-01-06',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
				),
				'description' => 'Import data from XML documents directly into Symphony.'
			);
		}
		
		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_xmlimporter_templates`");
			$this->_Parent->Database->query("DROP TABLE `tbl_xmlimporter_maps`");
			$this->_Parent->Database->query("DROP TABLE `tbl_xmlimporter_logs`");
		}
		
		public function install() {
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_xmlimporter_logs` (
					`id` int(11) NOT NULL auto_increment,
					`template_id` int(11) NOT NULL,
					`time` int(11) NOT NULL,
					`log` text default NULL,
					PRIMARY KEY (`id`),
					KEY `template_id` (`template_id`),
					KEY `time` (`time`)
				)
			");
			
			return true;
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