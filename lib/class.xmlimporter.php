<?php

	require_once(TOOLKIT . '/class.gateway.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	// Attempt to load XMLImporter Helper functions from the workspace rather
	// than the extension. If that file doesn't exist, then just load what
	// is provided.
	// @see https://github.com/symphonists/xmlimporter/issues/16
	if(@file_exists(WORKSPACE . '/xml-importers/class.xmlimporterhelpers.php') === true) {
		require_once(WORKSPACE . '/xml-importers/class.xmlimporterhelpers.php');
	}
	else if(@file_exists(EXTENSIONS . '/xmlimporter/lib/class.xmlimporterhelpers.php') === true) {
		require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporterhelpers.php');
	}

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

			set_time_limit(900);
			set_error_handler('handleXMLError');

			$self = $this; // Fucking PHP...
			$options = $this->options();
			$passed = true;

			if ($remote) {
				if (!is_null($source)) {
					$options['source'] = $source;
				}

				// Support {$root}
				$options['source'] = str_replace('{$root}', URL, $options['source']);

				// Parse timeout, default is 60
				$timeout = isset($options['timeout']) ? (int)$options['timeout'] : 60;

				// Fetch document:
				$gateway = new Gateway();
				$gateway->init();
				$gateway->setopt('URL', $options['source']);
				$gateway->setopt('TIMEOUT', $timeout);
				$data = $gateway->exec();

				$info = $gateway->getInfoLast();
				if (empty($data) || $info['http_code'] >= 400) {
					$this->_errors[] = __('No data to import. URL returned HTTP code %d', array($info['http_code']));
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

			if(!is_array($options['fields'])) {
				$this->_errors[] = __('No field mappings have been set for this XML Importer.');
				$passed = false;
			}

			if (!$passed) return self::__ERROR_PREPARING__;

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

				$field = FieldManager::fetch($mapping['field']);

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
				$entry = EntryManager::create();
				$entry->set('section_id', $options['section']);
				$entry->set('author_id', is_null(Symphony::Engine()->Author) ? '1' : Symphony::Engine()->Author->get('id'));

				$values = array();

				// Map values:
				foreach ($current['values'] as $field_id => $value) {
					$field = FieldManager::fetch($field_id);

					if(is_array($value)) {
						if(count($value) === 1) {
							$value = current($value);
						}
						if(count($value) === 0) {
							$value = '';
						}
					}

					// Adjust value?
					if (method_exists($field, 'prepareImportValue') && method_exists($field, 'getImportModes')) {
						$modes = $field->getImportModes();

						if(is_array($modes) && !empty($modes)) {
							$mode = current($modes);
						}

						$value = $field->prepareImportValue($value, $mode, $entry->get('id'));
					}

					// Handle different field types
					else {
						$type = $field->get('type');

						if ($type == 'author') {
							if ($field->get('allow_multiple_selection') == 'no') {
								if(is_array($value)){
								        $value = array(implode('', $value));
								}
							}
						}

						else if ($type == 'datetime') {
							$value = $value[0];
						}

						else if (is_array($value)) {
							$value = implode('', $value);
						}
					}

					$values[$field->get('element_name')] = $value;
				}

				// Validate:
				if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($values, $current['errors'])) {
					$passed = false;
				}

				else if (__ENTRY_OK__ != $entry->setDataFromPost($values, $current['errors'], true, true)) {
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

			$section = SectionManager::fetch($options['section']);

			if ((integer)$options['unique-field'] > 0) {
				$field = FieldManager::fetch($options['unique-field']);

				if (!empty($field)) foreach ($this->_entries as $index => $current) {
					$entry = $current['entry'];

					$data = $entry->getData($options['unique-field']);
					$where = $joins = $group = null;

					$field->buildDSRetrivalSQL($data, $joins, $where);

					$group = $field->requiresSQLGrouping();
					$entries = EntryManager::fetch(null, $options['section'], 1, null, $where, $joins, $group, false, null, false);

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
				$date = DateTimeObj::get('Y-m-d H:i:s');
				$dateGMT = DateTimeObj::getGMT('Y-m-d H:i:s');

				$exists = !empty($existing[$index]);
				$skip = ($options['can-update'] !== 'yes');

				// Skip entry
				if ($exists && $skip) {
					$entry->set('importer_status', 'skipped');

					###
					# Delegate: XMLImporterEntryPostSkip
					# Description: Skipping an entry. Entry object is provided.
					Symphony::ExtensionManager()->notifyMembers(
						'XMLImporterEntryPostSkip', '/xmlimporter/importers/run/',
						array(
							'section'	=> $section,
							'entry'		=> $entry,
							'fields'	=> $values
						)
					);
				}

				// Edit entry
				elseif ($exists) {
					$entry->set('id', $existing[$index]);
					$entry->set('modification_date', $date);
					$entry->set('modification_date_gmt', $dateGMT);

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

					EntryManager::edit($entry);
					$entry->set('importer_status', 'updated');

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

				// Create entry
				else {
					$entry->set('creation_date', $date);
					$entry->set('creation_date_gmt', $dateGMT);
					$entry->set('modification_date', $date);
					$entry->set('modification_date_gmt', $dateGMT);

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

					EntryManager::add($entry);
					$entry->set('importer_status', 'created');

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
