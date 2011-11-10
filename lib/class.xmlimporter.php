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

		public $_entries = array();
		public $_errors = array();

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

		public function validate($source = null, $remote = true) {
			if (!function_exists('handleXMLError')) {
				function handleXMLError($errno, $errstr, $errfile, $errline, $context) {
					$context['self']->_errors[] = $errstr;
				}
			}

			$entryManager = new EntryManager(Symphony::Engine());
			$fieldManager = $entryManager->fieldManager;

			set_time_limit(900);
			set_error_handler('handleXMLError');

			$self = $this; // Fucking PHP...
			$options = $this->options();

			if ($remote) {
				if (!is_null($source)) {
					$options['source'] = $source;
				}

				// Fetch document:
				$gateway = new Gateway();
				$gateway->init();
				$gateway->setopt('URL', $options['source']);
				$gateway->setopt('TIMEOUT', 60);
				$data = $gateway->exec();

				if (empty($data)) {
					$this->_errors[] = __('No data to import.');
					$passed = false;
				}
			}

			else if (!is_null($source)) {
				$data = $source;
			}

			else {
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
				if ($xpath->evaluate(stripslashes($mapping['xpath'])) !== false) continue;

				$field = $fieldManager->fetch($mapping['field']);

				$this->_errors[] = __(
					'\'%s\' expression <code>%s</code> is invalid.', array(
						$field->get('label'),
						General::sanitize($mapping['xpath'])
					)
				);
				$passed = false;
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
				$entry->set('author_id', is_null(Symphony::Engine()->Author) ? '1' : Symphony::Engine()->Author->get('id'));
				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));

				$values = array();

				// Map values:
				foreach ($current['values'] as $field_id => $value) {
					$field = $fieldManager->fetch($field_id);

					// Adjust value?
					if (method_exists($field, 'prepareImportValue')) {
						$value = $field->prepareImportValue($value, $entry->get('id'));
					}

					// Handle different field types
					// TODO: this should be done by the fields with the above function
					else {
						$type = $field->get('type');

						if ($type == 'taglist') {
							$value = implode(', ', $value);
						}

						else if ($type == 'select' || $type == 'selectbox_link' || $type == 'author') {
							if ($field->get('allow_multiple_selection') == 'no') {
								$value = array(implode('', $value));
							}
						}

						else if ($type == 'datetime') {
							$value = $value[0];
						}

						else {
							$value = implode('', $value);
						}
					}

					$values[$field->get('element_name')] = $value;
				}

				// Validate:
				if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($values, $current['errors'])) {
					$passed = false;
				}

				else if (__ENTRY_OK__ != $entry->setDataFromPost($values, $error, true, true)) {
					$passed = false;
				}

				$current['entry'] = $entry;
				$current['values'] = $values;
			}

			if (!$passed) return self::__ERROR_VALIDATING__;

			return self::__OK__;
		}

		public function commit() {
			$entryManager = new EntryManager(Symphony::Engine());
			$options = $this->options();
			$existing = array();

			$sectionManager = $entryManager->sectionManager;
			$section = $sectionManager->fetch($options['section']);

			if ((integer)$options['unique-field'] > 0) {
				$fieldManager = $entryManager->fieldManager;
				$field = $fieldManager->fetch($options['unique-field']);

				if (!empty($field)) foreach ($this->_entries as $index => $current) {
					$entry = $current['entry'];

					$data = $entry->getData($options['unique-field']);
					$where = $joins = $group = null;

					$field->buildDSRetrivalSQL($data, $joins, $where);

					$group = $field->requiresSQLGrouping();
					$entries = $entryManager->fetch(null, $options['section'], 1, null, $where, $joins, $group, false, null, false);

					if (is_array($entries) && !empty($entries)) {
						$existing[$index] = $entries[0]['id'];
					}

					else {
						$existing[$index] = null;
					}
				}
			}

			foreach ($this->_entries as $index => $current) {
				$entry = $current['entry'];
				$values = $current['values'];

				$edit = !empty($existing[$index]);

				// Matches an existing entry
				if ($edit) {
					// Update
					if ($options['can-update'] == 'yes') {
						$entry->set('id', $existing[$index]);
						$entry->set('importer_status', 'updated');
					}

					// Skip
					else {
						$entry->set('importer_status', 'skipped');
						continue;
					}

					###
					# Delegate: XMLImporterEntryPreEdit
					# Description: Just prior to editing of an Entry.
					Symphony::ExtensionManager()->notifyMembers(
						'XMLImporterEntryPreEdit', '/xmlimporter/importers/run/',
						array(
							'section'	=> $section,
							'fields'	=> &$values,
							'entry'		=> &$entry
						)
					);

					$entryManager->edit($entry);
				}

				// Create a new entry
				else {
					###
					# Delegate: XMLImporterEntryPreCreate
					# Description: Just prior to creation of an Entry. Entry object provided
					Symphony::ExtensionManager()->notifyMembers(
						'XMLImporterEntryPreCreate', '/xmlimporter/importers/run/',
						array(
							'section'	=> $section,
							'fields'	=> &$values,
							'entry'		=> &$entry
						)
					);

					$entryManager->add($entry);
				}

				$status = $entry->get('importer_status');

				if (!$status) $entry->set('importer_status', 'created');

				if ($edit) {
					###
					# Delegate: XMLImporterEntryPostEdit
					# Description: Editing an entry. Entry object is provided.
					Symphony::ExtensionManager()->notifyMembers(
						'XMLImporterEntryPostEdit', '/xmlimporter/importers/run/',
						array(
							'section'	=> $section,
							'entry'		=> $entry,
							'fields'	=> $values
						)
					);
				}

				else {
					###
					# Delegate: XMLImporterEntryPostCreate
					# Description: Creation of an Entry. New Entry object is provided.
					Symphony::ExtensionManager()->notifyMembers(
						'XMLImporterEntryPostCreate', '/xmlimporter/importers/run/',
						array(
							'section'	=> $section,
							'entry'		=> $entry,
							'fields'	=> $values
						)
					);
				}
			}
		}
	}

?>
