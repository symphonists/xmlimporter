<?php
	
	require_once(TOOLKIT . '/class.gateway.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporterhelpers.php');
	
	class XMLImporter {
		const __OK__ = 100;
		const __ERROR_PREPARING__ = 200;
		const __ERROR_VALIDATING__ = 210;
		const __ERROR_CREATING__ = 220;
		
		public $_Parent = null;
		public $_entries = array();
		public $_errors = array();
		
		public function __construct($parent) {
			$this->_Parent = $parent;
		}
		
		public function about() {
			return array();
		}
		
		public function options() {
			return array();
		}
		
		public function getEntries() {
			return $this->_entries;
		}
		
		public function getErrors() {
			return $this->_errors;
		}
		
		protected function getExpressionValue($xml, $entry, $xpath, $expression) {
			$matches = $xpath->evaluate($expression, $entry);

			if ($matches instanceof DOMNodeList) {
				$values = array();
				
				foreach ($matches as $match) {
					if ($match instanceof DOMAttr or $match instanceof DOMText) {
						$values[] = $match->nodeValue;
					}
					
					else {
						$values[] = $xml->saveXML($match);
					}
				}
				
				return $values;
			}
			
			else if (!is_null($matches)) {
				return array(strval($matches));
			}
			
			return null;
		}
		
		public function validate($source = null) {
			if (!function_exists('handleXMLError')) {
				function handleXMLError($errno, $errstr, $errfile, $errline, $context) {
					$context['self']->_errors[] = $errstr;
				}
			}
			
			$entryManager = new EntryManager($this->_Parent);
			$fieldManager = new FieldManager($this->_Parent);
			
			set_time_limit(900);
			set_error_handler('handleXMLError');
			
			$self = $this; // Fucking PHP...
			$options = $this->options();
			
			if (!is_null($source)) {
				$options['source'] = $source;
			}
			
			// Fetch document:
			$gateway = new Gateway();
			$gateway->init();
			$gateway->setopt('URL', $options['source']);
			$gateway->setopt('TIMEOUT', 6);
			$data = $gateway->exec();
			
			if (empty($data)) {
				$this->_errors[] = __('No data to import.');
				$passed = false;
			}
			
			// Load document:
			$xml = new DOMDocument();
			$xml->loadXML($data);
			
			restore_error_handler();
			
			$xpath = new DOMXPath($xml);
			$passed = true;
			
			// Register namespaces:
			if (is_array($options['namespaces'])) {
				foreach ($options['namespaces'] as $namespace) {
					$xpath->registerNamespace($namespace['name'], $namespace['uri']);
				}
			}
			
			// Invalid Markup:
			if (empty($xml)) {
				$passed = false;
			}
			
			// Invalid Expression:
			else if (($entries = $xpath->query($options['included-elements'])) === false) {
				$this->_errors[] = __(
					'Root expression <code>%s</code> is invalid.', array(
						General::sanitize($options['included-elements'])
					)
				);
				$passed = false;
			}
			
			// No Entries:
			else if (is_null($entries) or $entries->length == 0) {
				$this->_errors[] = __('No entries to import.');
				$passed = false;
			}
			
			// Test expressions:
			else foreach ($options['fields'] as $mapping) {
				if ($xpath->evaluate(stripslashes($mapping['xpath'])) === false) {
					$field = $fieldManager->fetch($mapping['field']);
					
					$this->_errors[] = __(
						'\'%s\' expression <code>%s</code> is invalid.', array(
							$field->get('label'),
							General::sanitize($mapping['xpath'])
						)
					);
					$passed = false;
				}
			}
			
			if (!$passed) return self::__ERROR_PREPARING__;
			
			// Gather data:
			foreach ($entries as $index => $entry) {
				$this->_entries[$index] = array(
					'element'	=> $entry,
					'values'	=> array(),
					'errors'	=> array(),
					'entry'		=> null
				);
				
				foreach ($options['fields'] as $mapping) {
					$values = $this->getExpressionValue($xml, $entry, $xpath, $mapping['xpath'], $debug);
					
					if (isset($mapping['php']) && $mapping['php'] != '') {
						$php = stripslashes($mapping['php']);
						
						// static helper
						if (preg_match('/::/', $php)) {
							foreach($values as $id => $value) {
								$values[$id] = call_user_func_array($php, array($value));
							}
						}
						
						// basic function
						else {
							foreach($values as $id => $value) {
								$function = preg_replace('/\$value/', "'" . $value . "'", $php);			
								if (!preg_match('/^return/', $function)) $function = 'return ' . $function;
								if (!preg_match('/;$/', $function)) $function .= ';';
								$values[$id] = @eval($function);
							}
						}
					}
					
					$this->_entries[$index]['values'][$mapping['field']] = $values;					
				}
			}
			
			// Validate:
			$passed = true;
			
			foreach ($this->_entries as &$current) {
				$entry = $entryManager->create();
				$entry->set('section_id', $options['section']);
				$entry->set('author_id', $this->_Parent->Author->get('id'));
				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
				
				$values = array();
				
				// Map values:
				foreach ($current['values'] as $field_id => $value) {					
					$field = $fieldManager->fetch($field_id);
					
					// Handle different field types
					$type = $field->get('type');
					if($type == 'taglist') {
						$value = implode(', ', $value);
					}
					elseif($type == 'select' || $type == 'selectbox_link' || $type == 'author') {
						if($field->get('allow_multiple_selection') == 'no') {
							$value = array(implode('', $value));
						}
					}
					else {
						$value = implode('', $value);
					}
					
					// Adjust value?
					if (method_exists($field, 'prepareImportValue')) {
						$value = $field->prepareImportValue($value);
					}
					
					$values[$field->get('element_name')] = $value;
				}
				
				// Validate:
				if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($values, $current['errors'])) {
					$passed = false;
				}
				
				else if (__ENTRY_OK__ != $entry->setDataFromPost($values, $error, true)) {
					$passed = false;
				}
				
				$current['entry'] = $entry;
				$current['values'] = $values;
			}
			
			if (!$passed) return self::__ERROR_VALIDATING__;
			
			return self::__OK__;
		}
		
		public function commit() {
			$options = $this->options();
			$existing = array();
			
			if ((integer)$options['unique-field'] > 0) {
				$entryManager = new EntryManager($this->_Parent);
				$fieldManager = new FieldManager($this->_Parent);
				$field = $fieldManager->fetch($options['unique-field']);
				
				if (!empty($field)) foreach ($this->_entries as $index => $current) {
					$entry = $current['entry'];
					
					$data = $entry->getData($options['unique-field']);
					$where = $joins = $group = null;
					
					$field->buildDSRetrivalSQL($data, $joins, $where);
					
					$group = $field->requiresSQLGrouping();
					$entries = $entryManager->fetch(null, $options['section'], 1, null, $where, $joins, false, true);
					
					if (is_array($entries) && count($entries) > 0) {
						$existing[$index] = $entries[0]->get('id');
					}
					
					else {
						$existing[$index] = null;
					}
				}
			}
			
			foreach ($this->_entries as $index => $current) {
				$entry = $current['entry'];
				$values = $current['values'];
				
				// Matches an existing entry
				if (!empty($existing[$index])) {
					// update
					if ($options['can-update'] == 'yes') {
						$entry->set('id', $existing[$index]);
						$entry->set('importer_status', 'updated');
					}
					
					// skip
					else {
						$entry->set('importer_status', 'skipped');
						continue;
					}
				}
				
				// Add data again, without simulation:
				//$entry->setDataFromPost($values, $error, false);
				$entry->commit();
				
				$status = $entry->get('importer_status');
				
				if (!$status) $entry->set('importer_status', 'created');
			}
		}
	}
	
?>
