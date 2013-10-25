<?php

	require_once(TOOLKIT . '/class.gateway.php');
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');
	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimportermanager.php');

	class contentExtensionXmlImporterImporters extends AdministrationPage {
		protected $_handle = '';
		protected $_action = '';
		protected $_driver = null;
		protected $_editing = false;
		protected $_errors = array();
		protected $_fields = array();
		protected $_status = '';
		protected $_runs = array();
		protected $_importers = array();
		protected $_uri = null;
		protected $_valid = true;
		protected $_pagination = null;
		protected $_table_column = 'name';
		protected $_table_columns = array();
		protected $_table_direction = 'asc';

		public function __construct(){
			parent::__construct();

			$this->_uri = SYMPHONY_URL . '/extension/xmlimporter';
			$this->_driver = Symphony::ExtensionManager()->create('xmlimporter');
		}

		public function build($context) {
			if (isset($context[0])) {
				if ($context[0] == 'edit' || $context[0] == 'new') {
					$this->__prepareEdit($context);
				}

				else if ($context[0] == 'run') {
					$this->__prepareRun($context);
				}
			}

			else {
				$this->__prepareIndex();
			}

			parent::build($context);
		}

	/*-------------------------------------------------------------------------
		Run
	-------------------------------------------------------------------------*/

		public function __prepareRun($context) {
			$importManager = new XmlImporterManager();
			$html_errors = ini_get('html_errors');
			$source = null;

			if (isset($_GET['source'])) {
				$source = $_GET['source'];
			}

			array_shift($context);

			ini_set('html_errors', false);

			foreach ($context as $handle) {
				$importer = $importManager->create($handle);
				if($importer === false) {
					Symphony::Log()->writeToLog(__('The XMLImporter %s could not be found.', array($handle)), E_USER_ERROR, true);
					continue;
				}
				else {
					$status = $importer->validate($source);
				}

				if ($status == XMLImporter::__OK__) {
					$importer->commit();
				}

				$this->_runs[] = array(
					'importer'	=> $importer,
					'status'	=> $status
				);
			}

			ini_set('html_errors', $html_errors);
		}

		public function __viewRun() {
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.css', 'screen', 103);
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.js', 104);

			$this->setPageType('form');
			$this->setTitle(__(
				'%1$s &ndash; %2$s &ndash; %3$s',
				array(
					__('Symphony'),
					__('XML Importers'),
					__('Run XML Importer')
				)
			));

			$button = Widget::Anchor(
				__('Edit XML Importer'),
				$this->_uri . '/importers/edit/' . $this->_context[1] . '/',
				__('Edit XML Importer'),
				'button'
			);

			$this->appendSubheading(__('Run XML Importer'), $button);

			if(empty($this->_runs)) {
				$this->pageAlert(__('The XMLImporter %s could not be found.', array('<code>' . $this->_context[1] . '</code>')), Alert::ERROR);
				return false;
			}

			foreach ($this->_runs as $run) {
				$importer = $run['importer'];
				$status = $run['status'];
				$entries = $importer->getEntries();
				$about = $importer->about();

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings xml-importer-run');
				$fieldset->appendChild(new XMLElement('legend', $about['name']));

				// Markup invalid:
				if ($status == XMLImporter::__ERROR_PREPARING__) {
					$fieldset->appendChild(new XMLElement(
						'h3', __('Import Failed')
					));

					$list = new XMLElement('ol');

					foreach ($importer->getErrors() as $error) {
						$list->appendChild(new XMLElement('li', $error));
					}

					$fieldset->appendChild($list);
				}

				// Invalid entry:
				else if ($status == XMLImporter::__ERROR_VALIDATING__) {
					$fieldset->appendChild(new XMLElement(
						'h3', __('Import Failed')
					));

					// Gather statistics:
					$failed = array();

					foreach ($entries as $index => $current) if (!empty($current['errors'])) {
						$current['position'] = $index + 1;
						$failed[] = $current;
					}

					$fieldset->appendChild(new XMLElement(
						'p', __('Import failed because %d entries did not validate, a total of %d entries passed.', array(
							count($failed), count($entries) - count($failed)
						))
					));

					foreach ($failed as $index => $current) {
						$fieldset->appendChild(new XMLElement(
							'h3', __('Import entry #%d', array($current['position']))
						));

					// Errors -------------------------------------------------

						$list = new XMLElement('ol');

						foreach ($current['errors'] as $error) {
							$list->appendChild(new XMLElement('li', $error));
						}

						$fieldset->appendChild($list);
						
						###
						# Delegate: XMLImporterImportPostRunErrors
						# Description: Notify Delegate for Errors
						Symphony::ExtensionManager()->notifyMembers(
							'XMLImporterImportPostRunErrors', '/xmlimporter/importers/run/',
							array(
								$current['errors']
							)
						);
						

					// Source -------------------------------------------------

						$entry = $current['element'];
						$xml = new DOMDocument();
						$xml->preserveWhiteSpace = false;
						$xml->formatOutput = true;

						if(is_null($entry->ownerDocument)) {
							$xml->loadXML($entry->saveXML());
						}
						else {
							$xml->loadXML($entry->ownerDocument->saveXML($entry));
						}

						$source = htmlentities($xml->saveXML($xml->documentElement), ENT_COMPAT, 'UTF-8');

						$fieldset->appendChild(new XMLElement(
							'pre', "<code>{$source}</code>"
						));

						foreach ($current['values'] as $field => $value) {
							if(is_array($value)) $value = implode(',', $value);
							$values[$field] = htmlentities($value);
						}

						$fieldset->appendChild(new XMLElement(
							'pre',
							"<code>" . var_export($values, true) . "</code>"
						));
					}
				}

				// Passed:
				else {
					$fieldset->appendChild(new XMLElement(
						'h3', __('Import Complete')
					));

					$importer_result = array(
						'created' => 0,
						'updated' => 0,
						'skipped' => 0
					);

					foreach ($entries as $entry) {
						$importer_result[$entry['entry']->get('importer_status')]++;
					}

					$fieldset->appendChild(new XMLElement(
						'p', __(
							'Import completed successfully: %d new entries were created, %d updated, and %d skipped.', array(
							$importer_result['created'],
							$importer_result['updated'],
							$importer_result['skipped']
						))
					));
					
				}

				$this->Form->appendChild($fieldset);
				
				###
				# Delegate: XMLImporterImportPostRun
				# Description: All Importers run successfully
				Symphony::ExtensionManager()->notifyMembers(
					'XMLImporterImportPostRun', '/xmlimporter/importers/run/',
					array(
						$importer_result['created'],
						$importer_result['updated'],
						$importer_result['skipped']
					)
				);
			}
		}

	/*-------------------------------------------------------------------------
		Edit
	-------------------------------------------------------------------------*/

		public function __prepareEdit($context) {
			if ($this->_editing = $context[0] == 'edit') {
				$this->_fields = $this->_driver->getXMLImporter($context[1]);
			}

			$this->_handle = $context[1];
			$this->_status = $context[2];
		}

		public function __actionNew() {
			$this->__actionEdit();
		}

		public function __actionEdit() {
			if (isset($_POST['action']) && array_key_exists('delete', $_POST['action'])) {
				$this->__actionEditDelete();
			}

			else {
				$this->__actionEditNormal();
			}
		}

		public function __actionEditDelete() {
			General::deleteFile($this->_fields['about']['file']);

			redirect("{$this->_uri}/importers/");
		}

		public function __actionEditNormal() {
		// Validate -----------------------------------------------------------

			$fields = $_POST['fields'];

			// Name:
			if (!isset($fields['about']['name']) || trim($fields['about']['name']) == '') {
				$this->_errors['name'] = __('Name must not be empty.');
			}

			// Source:
			if (!isset($fields['source']) || trim($fields['source']) == '') {
				$this->_errors['source'] = __('Source must not be empty.');
			}

			else {
				// Support {$root}
				$evaluated_source = str_replace('{$root}', URL, $fields['source']);
				if(!filter_var($evaluated_source, FILTER_VALIDATE_URL)) {
					$this->_errors['source'] = __('Source is not a valid URL.');
				}
			}

		// Namespaces ---------------------------------------------------------

			if (
				isset($fields['discover-namespaces'])
				&& $fields['discover-namespaces'] == 'yes'
				&& !isset($this->_errors['source'])
			) {
				$gateway = new Gateway();
				$gateway->init();
				$gateway->setopt('URL', $evaluated_source);
				$gateway->setopt('TIMEOUT', (int)$fields['timeout']);
				$data = $gateway->exec();

				if ($data === false) {
					$this->_errors['discover-namespaces'] = __('Error loading data from URL, make sure it is valid and that it returns data within 60 seconds.');
				}

				else {
					preg_match_all('/xmlns:([a-z][a-z-0-9\-]*)="([^\"]+)"/i', $data, $matches);

					if (isset($matches[2][0])) {
						$namespaces = array();

						if (!is_array($fields['namespaces'])) {
							$fields['namespaces'] = array();
						}

						foreach ($fields['namespaces'] as $namespace) {
							$namespaces[] = $namespace['name'];
							$namespaces[] = $namespace['uri'];
						}

						foreach ($matches[2] as $index => $uri) {
							$name = $matches[1][$index];

							if (in_array($name, $namespaces) or in_array($uri, $namespaces)) continue;

							$namespaces[] = $name;
							$namespaces[] = $uri;

							$fields['namespaces'][] = array(
								'name'	=> $name,
								'uri'	=> $uri
							);
						}
					}
				}
			}

			// Included elements:
			if (!isset($fields['included-elements']) || trim($fields['included-elements']) == '') {
				$this->_errors['included-elements'] = __('Included Elements must not be empty.');
			}

			else {
				try {
					$this->_driver->validateXPath($fields['included-elements'], $fields['namespaces']);
				}

				catch (Exception $e) {
					$this->_errors['included-elements'] = $e->getMessage();
				}
			}

			// Fields:
			if (isset($fields['fields']) && is_array($fields['fields'])) {
				foreach ($fields['fields'] as $index => $field) {
					try {
						$this->_driver->validateXPath($field['xpath'], $fields['namespaces']);
					}

					catch (Exception $e) {
						if (!isset($this->_errors['fields'])) {
							$this->_errors['fields'] = array();
						}

						$this->_errors['fields'][$index] = $e->getMessage();
					}
				}
			}

			$fields['about']['file'] = (
				isset($this->_fields['about']['file'])
					? $this->_fields['about']['file']
					: null
			);
			$fields['about']['created'] = (
				isset($this->_fields['about']['created'])
					? $this->_fields['about']['created']
					: null
			);
			$fields['about']['updated'] = (
				isset($this->_fields['about']['updated'])
					? $this->_fields['about']['updated']
					: null
			);
			$fields['can-update'] = (
				isset($fields['can-update']) && $fields['can-update'] == 'yes'
					? 'yes'
					: 'no'
			);

			$this->_fields = $fields;

			if (!empty($this->_errors)) {
				$this->_valid = false;

				return;
			}

		// Save ---------------------------------------------------------------

			$name = $this->_handle;

			if (!$this->_driver->setXMLImporter($name, $error, $this->_fields)) {
				$this->_valid = false;
				$this->_errors['other'] = $error;

				return;
			}

			if ($this->_editing) {
				redirect("{$this->_uri}/importers/edit/{$name}/saved/");
			}

			else {
				redirect("{$this->_uri}/importers/edit/{$name}/created/");
			}
		}

		public function __viewNew() {
			$this->__viewEdit();
		}

		public function __viewEdit() {
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.css', 'screen', 103);
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.js', 104);

		// Status: ------------------------------------------------------------

			if (!$this->_valid) {
				$message = __('An error occurred while processing this form.');

				if ($this->_errors['other']) {
					$message = $this->_errors['other'];
				}

				$this->pageAlert($message, Alert::ERROR);
			}

			// Status message:
			if ($this->_status) {
				$action = null;

				switch ($this->_status) {
					case 'saved': $action = '%1$s updated at %2$s. <a href="%3$s">Create another?</a> <a href="%4$s">View all %5$s</a>'; break;
					case 'created': $action = '%1$s created at %2$s. <a href="%3$s">Create another?</a> <a href="%4$s">View all %5$s</a>'; break;
				}

				if ($action) $this->pageAlert(
					__(
						$action, array(
							__('XML Importer'),
							DateTimeObj::get(__SYM_TIME_FORMAT__),
							SYMPHONY_URL . '/extension/xmlimporter/importers/new/',
							SYMPHONY_URL . '/extension/xmlimporter/importers/',
							__('XML Importers')
						)
					),
					Alert::SUCCESS
				);
			}

		// Header: ------------------------------------------------------------

			$title = (
				isset($this->_fields['about']['name']) && $this->_fields['about']['name']
					? $this->_fields['about']['name']
					: null
			);
			$header = __(
				$title
					? $title
					: __('Untitled')
			);
			$button = null;

			if ($this->_editing) {
				$button = Widget::Anchor(
					__('Run XML Importer'),
					$this->_uri . '/importers/run/' . $this->_context[1] . '/',
					__('Run XML Importer'),
					'button'
				);

				if($this->_fields === false) {
					$this->pageAlert(__('The XMLImporter %s could not be found.', array('<code>' . $this->_context[1] . '</code>')), Alert::ERROR);
				}
			}

			$this->setPageType('form');
			$this->setTitle(__(
				(
					$title
						? '%1$s &ndash; %2$s &ndash; %3$s'
						: '%1$s &ndash; %2$s'
				),
				array(
					__('Symphony'),
					__('XML Importers'),
					$title
				)
			));
			$this->appendSubheading($header, $button);
			$this->insertBreadcrumbs(array(
				Widget::Anchor(__('XML Importers'), $this->_uri . '/importers/'),
			));

		// About --------------------------------------------------------------

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));

			$group = new XMLElement('div');
			$group->setAttribute('class', 'two columns');

			$label = Widget::Label(__('Name'));
			$label->setAttribute('class', 'column');
			$label->appendChild(Widget::Input(
				'fields[about][name]',
				General::sanitize(
					isset($this->_fields['about']['name'])
						? $this->_fields['about']['name']
						: null
				)
			));

			if (isset($this->_errors['name'])) {
				$label = Widget::Error($label, $this->_errors['name']);
			}

			$group->appendChild($label);

			$label = Widget::Label(__('Description <i>Optional</i>'));
			$label->setAttribute('class', 'column');
			$label->appendChild(Widget::Input(
				'fields[about][description]',
				General::sanitize(
					isset($this->_fields['about']['description'])
						? $this->_fields['about']['description']
						: null
				)
			));

			$group->appendChild($label);
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);

		// Source -------------------------------------------------------------

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Source')));

			$label = Widget::Label(__('URL'));
			$label->appendChild(Widget::Input(
				'fields[source]', General::sanitize(
					isset($this->_fields['source'])
						? $this->_fields['source']
						: null
				)
			));

			if (isset($this->_errors['source'])) {
				$label = Widget::Error($label, $this->_errors['source']);
			}

			$fieldset->appendChild($label);

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Enter the URL of the XML document you want to process.'));
			$fieldset->appendChild($help);

			$label = new XMLElement('p', __('Namespace Declarations'));
			$label->setAttribute('class', 'label');
			$fieldset->appendChild($label);

		// Namespaces ---------------------------------------------------------

			$namespaces = new XMLElement('ol');
			$namespaces->setAttribute('class', 'namespaces-duplicator');
			$namespaces->setAttribute('data-add', __('Add namespace'));
			$namespaces->setAttribute('data-remove', __('Remove namespace'));

			if (isset($this->_fields['namespaces']) and is_array($this->_fields['namespaces'])) {
				foreach ($this->_fields['namespaces'] as $index => $data) {
					$name = "fields[namespaces][{$index}]";

					$li = new XMLElement('li');
					$li->appendChild(new XMLElement('header', '<h4>' . __('Namespace') . '</h4>'));

					$group = new XMLElement('div');
					$group->setAttribute('class', 'two columns');

					$label = Widget::Label(__('Name'));
					$label->setAttribute('class', 'column');
					$input = Widget::Input(
						"{$name}[name]",
						General::sanitize(
							isset($data['name'])
								? $data['name']
								: null
						)
					);
					$label->appendChild($input);
					$group->appendChild($label);

					$label = Widget::Label(__('URI'));
					$label->setAttribute('class', 'column');
					$input = Widget::Input(
						"{$name}[uri]",
						General::sanitize(
							isset($data['uri'])
								? $data['uri']
								: null
						)
					);
					$label->appendChild($input);
					$group->appendChild($label);

					$li->appendChild($group);
					$namespaces->appendChild($li);
				}
			}

			$name = "fields[namespaces][-1]";

			$li = new XMLElement('li');
			$li->appendChild(new XMLElement('header', '<h4>' . __('Namespace') . '</h4>'));
			$li->setAttribute('class', 'template');

			$input = Widget::Input("{$name}[field]", $field_id, 'hidden');
			$li->appendChild($input);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'two columns');

			$label = Widget::Label(__('Name'));
			$label->setAttribute('class', 'column');
			$input = Widget::Input("{$name}[name]");
			$label->appendChild($input);
			$group->appendChild($label);

			$label = Widget::Label(__('URI'));
			$label->setAttribute('class', 'column');
			$input = Widget::Input("{$name}[uri]");
			$label->appendChild($input);
			$group->appendChild($label);

			$li->appendChild($group);
			$namespaces->appendChild($li);

			$fieldset->appendChild($namespaces);

		// Discover Namespaces ------------------------------------------------

			$label = Widget::Label();
			$input = Widget::Input('fields[discover-namespaces]', 'yes', 'checkbox');

			if (!$this->_editing) {
				$input->setAttribute('checked', 'checked');
			}

			$label->setValue(__('%s Automatically discover namespaces', array(
				$input->generate(false)
			)));

			if (isset($this->_errors['discover-namespaces'])) {
				$label = Widget::Error($label, $this->_errors['discover-namespaces']);
			}

			$fieldset->appendChild($label);

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Search the source document for namespaces, any that it finds will be added to the declarations above.'));
			$fieldset->appendChild($help);

		// Included Elements --------------------------------------------------

			$label = Widget::Label(__('Included Elements'));
			$label->appendChild(Widget::Input(
				'fields[included-elements]', General::sanitize(
					isset($this->_fields['included-elements'])
						? $this->_fields['included-elements']
						: null
				)
			));

			if (isset($this->_errors['included-elements'])) {
				$label = Widget::Error($label, $this->_errors['included-elements']);
			}

			$fieldset->appendChild($label);

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Use an XPath expression to select which elements from the source XML to include.'));
			$fieldset->appendChild($help);

			$this->Form->appendChild($fieldset);

		// Section ------------------------------------------------------------

			$sections = SectionManager::fetch(null, 'ASC', 'name');
			$options = array();

			if (is_array($sections)) {
				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Destination')));

				if (is_array($sections)) foreach ($sections as $section) {
					if ($section->fetchFields() === false) continue;

					$selected = (
						isset($this->_fields['section'])
						&& $this->_fields['section'] == $section->get('id')
					);
					$options[] = array(
						$section->get('id'),
						$selected,
						$section->get('name')
					);
				}

				$label = Widget::Label(__('Section'));
				$label->appendChild(Widget::Select(
					'fields[section]', $options
				));

				$fieldset->appendChild($label);

				$label = new XMLElement('p', __('Fields'));
				$label->setAttribute('class', 'label');
				$fieldset->appendChild($label);

				foreach ($sections as $section) {
					$section_duplicator = new XMLElement('div');
					$section_duplicator->setAttribute('class', 'frame section-fields');
					$section_duplicator->setAttribute('id', 'section-' . $section->get('id'));
					$section_fields = new XMLElement('ol');
					$fields = $section->fetchFields();

					if ($fields === false) continue;

					// Templates
					foreach ($fields as $index => $field) {
						$field_id = $field->get('id');
						$field_name = "fields[fields][-1]";

						$li = new XMLElement('li');
						$li->setAttribute('class', 'unique template');
						$li->setAttribute('data-type', $field->get('element_name'));
						$li->appendChild(new XMLElement('header', '<h4>' . $field->get('label') . '</h4>'));

						$input = Widget::Input("{$field_name}[field]", $field_id, 'hidden');
						$li->appendChild($input);

						$group = new XMLElement('div');
						$group->setAttribute('class', 'two columns');

						$label = Widget::Label(__('XPath Expression'));
						$label->setAttribute('class', 'column');
						$input = Widget::Input("{$field_name}[xpath]");
						$label->appendChild($input);
						$group->appendChild($label);

						$label = Widget::Label(__('PHP Function'));
						$label->appendChild(new XMLElement('i', __('Optional')));
						$label->setAttribute('class', 'column');
						$input = Widget::Input("{$field_name}[php]");
						$label->appendChild($input);
						$group->appendChild($label);

						$li->appendChild($group);

						$label = Widget::Label();
						$label->setAttribute('class', 'meta');
						$input = Widget::Input("fields[unique-field]", $field_id, 'radio');

						$label->setValue($input->generate(false) . ' ' . __('Is unique'));
						$li->appendChild($label);
						$section_fields->appendChild($li);
					}

					// Actual instances
					foreach ($fields as $index => $field) {
						$field_id = $field->get('id');
						$field_name = "fields[fields][{$index}]";
						$field_data = null;

						if (isset($this->_fields['fields'])) {
							foreach ($this->_fields['fields'] as $temp_data) {
								if ($temp_data['field'] != $field_id) continue;

								$field_data = $temp_data;
							}
						}

						if (is_null($field_data)) continue;

						$li = new XMLElement('li');
						$li->setAttribute('class', 'unique');
						$li->setAttribute('data-type', $field->get('element_name'));
						$li->appendChild(new XMLElement('header', '<h4>' . $field->get('label') . '</h4>'));

						$input = Widget::Input("{$field_name}[field]", $field_id, 'hidden');
						$li->appendChild($input);

						$group = new XMLElement('div');
						$group->setAttribute('class', 'two columns');

						$label = Widget::Label(__('XPath Expression'));
						$label->setAttribute('class', 'column');
						$input = Widget::Input(
							"{$field_name}[xpath]",
							General::sanitize(
								isset($field_data['xpath'])
									? $field_data['xpath']
									: null
							)
						);
						$label->appendChild($input);

						if (isset($this->_errors['fields'][$index])) {
							$label = Widget::Error($label, $this->_errors['fields'][$index]);
						}

						$group->appendChild($label);

						$label = Widget::Label(__('PHP Function'));
						$label->appendChild(new XMLElement('i', __('Optional')));
						$label->setAttribute('class', 'column');
						$input = Widget::Input(
							"{$field_name}[php]",
							General::sanitize(
								isset($field_data['php'])
									? $field_data['php']
									: null
							)
						);
						$label->appendChild($input);
						$group->appendChild($label);

						$li->appendChild($group);

						$label = Widget::Label();
						$label->setAttribute('class', 'meta');
						$input = Widget::Input("fields[unique-field]", $field_id, 'radio');

						if (isset($this->_fields['unique-field']) && $this->_fields['unique-field'] == $field_id) {
							$input->setAttribute('checked', 'checked');
						}

						$label->setValue($input->generate(false) . ' ' . __('Is unique'));
						$li->appendChild($label);
						$section_fields->appendChild($li);
					}

					$section_duplicator->appendChild($section_fields);
					$fieldset->appendChild($section_duplicator);
				}

				$label = Widget::Label();
				$label->setAttribute('class', 'meta');
				$input = Widget::Input("fields[unique-field]", '0', 'radio');

				if (isset($this->_fields['unique-field']) && !$this->_fields['unique-field']) {
					$input->setAttribute('checked', 'checked');
				}

				$label->setValue(__(
					'%s No field is unique', array(
						$input->generate(false)
					)
				));
				$fieldset->appendChild($label);

				$help = new XMLElement('p');
				$help->setAttribute('class', 'help');
				$help->setValue(__('If a field is flagged as unique, its value will be used to prevent duplicate entries from being created.'));
				$fieldset->appendChild($help);

				$label = Widget::Label();
				$input = Widget::Input('fields[can-update]', 'yes', 'checkbox');

				if (isset($this->_fields['can-update']) && $this->_fields['can-update'] == 'yes') {
					$input->setAttribute('checked', 'checked');
				}

				$label->setValue($input->generate(false) . ' ' . __('Can update existing entries'));
				$fieldset->appendChild($label);

				$help = new XMLElement('p');
				$help->setAttribute('class', 'help');
				$help->setValue(__('Allow entries to be updated from the source, only works when a unique field is chosen.'));
				$fieldset->appendChild($help);

				$this->Form->appendChild($fieldset);
			}

		// Footer -------------------------------------------------------------

			$timeout = isset($this->_fields['timeout']) ? $this->_fields['timeout'] : 60;
			$this->Form->appendChild(
				Widget::Input('fields[timeout]', (string)$timeout, 'hidden')
			);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					($this->_editing ? __('Save Changes') : __('Create XML Importer')),
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);

			if ($this->_editing) {
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array(
					'name'		=> 'action[delete]',
					'class'		=> 'button confirm delete',
					'title'		=> __('Delete this XML Importer')
				));
				$div->appendChild($button);
			}

			$this->Form->appendChild($div);
		}

	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/

		public function generateLink($values) {
			$values = array_merge(array(
				'pg'	=> $this->_pagination->page,
				'sort'	=> $this->_table_column,
				'order'	=> $this->_table_direction
			), $values);

			$count = 0;
			$link = Symphony::Engine()->getCurrentPageURL();

			foreach ($values as $key => $value) {
				if ($count++ == 0) {
					$link .= '?';
				}

				else {
					$link .= '&amp;';
				}

				$link .= "{$key}={$value}";
			}

			return $link;
		}

		public function __prepareIndex() {
			$this->_table_columns = array(
				'name'			=> array(__('Name'), true),
				'url'			=> array(__('URL'), true),
				'elements'		=> array(__('Included Elements'), true),
				'modified'		=> array(__('Modified'), true),
				'author'		=> array(__('Author'), true)
			);

			if (isset($_GET['sort']) && $_GET['sort'] && $this->_table_columns[$_GET['sort']][1]) {
				$this->_table_column = $_GET['sort'];
			}

			if (isset($_GET['order']) && $_GET['order'] == 'desc') {
				$this->_table_direction = 'desc';
			}

			$this->_pagination = (object)array(
				'page' => (
					isset($_GET['pg']) && $_GET['pg'] > 1
						? $_GET['pg']
						: 1
				),
				'length' => Symphony::Configuration()->get('pagination_maximum_rows', 'symphony')
			);

			$this->_importers = $this->_driver->getXMLImporters(
				$this->_table_column,
				$this->_table_direction,
				$this->_pagination->page,
				$this->_pagination->length
			);

			// Calculate pagination:
			$this->_pagination->start = max(1, (($page - 1) * 17));
			$this->_pagination->end = (
				$this->_pagination->start == 1
				? $this->_pagination->length
				: $start + count($this->_importers)
			);
			$this->_pagination->total = $this->_driver->countXMLImporters();
			$this->_pagination->pages = ceil(
				$this->_pagination->total / $this->_pagination->length
			);
		}

		public function __actionIndex() {
			$checked = (
				(isset($_POST['items']) && is_array($_POST['items']))
					? array_keys($_POST['items'])
					: null
			);

			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $name) {
							$data = $this->_driver->getXMLImporter($name);

							General::deleteFile($data['about']['file']);
						}

						redirect("{$this->_uri}/importers/");
						break;

					case 'run':
						$url = '';

						foreach ($checked as $name) {
							$url .= "/{$name}";
						}

						redirect("{$this->_uri}/importers/run{$url}/");
						break;
				}
			}
		}

		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('XML Importers'));

			$this->appendSubheading(__('XML Importers'), Widget::Anchor(
				__('Create New'), "{$this->_uri}/importers/new/",
				__('Create a new XML Importer'), 'create button'
			));

			$tableHead = array();
			$tableBody = array();

			// Columns, with sorting:
			foreach ($this->_table_columns as $column => $values) {
				if ($values[1]) {
					if ($column == $this->_table_column) {
						if ($this->_table_direction == 'desc') {
							$direction = 'asc';
							$label = 'ascending';
						}

						else {
							$direction = 'desc';
							$label = 'descending';
						}
					}

					else {
						$direction = 'asc';
						$label = 'ascending';
					}

					$link = $this->generateLink(array(
						'sort'	=> $column,
						'order'	=> $direction
					));

					$anchor = Widget::Anchor(
						$values[0], $link,
						__("Sort by {$label} " . strtolower($values[0]))
					);

					if ($column == $this->_table_column) {
						$anchor->setAttribute('class', 'active');
					}

					$tableHead[] = array($anchor, 'col');
				}

				else {
					$tableHead[] = array($values[0], 'col');
				}
			}

			if (!is_array($this->_importers) or empty($this->_importers)) {
				$tableBody = array(
					Widget::TableRow(array(
						Widget::TableData(
							__('None Found.'), 'inactive', null, count($tableHead)
						)
					))
				);
			}

			else foreach ($this->_importers as $importer) {
				$col_name = Widget::TableData(Widget::Anchor(
					$importer['about']['name'],
					"{$this->_uri}/importers/edit/{$importer['about']['handle']}/"
				));
				$col_name->appendChild(Widget::Input(
					"items[{$importer['about']['handle']}]",
					null, 'checkbox'
				));

				$col_date = Widget::TableData(DateTimeObj::get(
					__SYM_DATETIME_FORMAT__, strtotime($importer['about']['updated'])
				));

				if (!empty($importer['source'])) {
					$col_url = Widget::TableData(
						General::sanitize($importer['source'])
					);
				}

				else {
					$col_url = Widget::TableData(__('None'), 'inactive');
				}

				if (!empty($importer['included-elements'])) {
					$col_elements = Widget::TableData(
						General::sanitize($importer['included-elements'])
					);
				}

				else {
					$col_elements = Widget::TableData(__('None'), 'inactive');
				}

				if (isset($importer['about']['email'])) {
					$col_author = Widget::TableData(Widget::Anchor(
						$importer['about']['author']['name'],
						'mailto:' . $importer['about']['author']['email']
					));
				}

				else if (isset($importer['about']['author']['name'])) {
					$col_author = Widget::TableData($importer['about']['author']['name']);
				}

				else {
					$col_author = Widget::TableData(__('None'), 'inactive');
				}

				$tableBody[] = Widget::TableRow(
					array(
						$col_name, $col_url, $col_elements,
						$col_date, $col_author
					),
					null
				);
			}

			$table = Widget::Table(
				Widget::TableHead($tableHead), null,
				Widget::TableBody($tableBody)
			);
			$table->setAttribute('class', 'selectable');

			$this->Form->appendChild($table);

			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');

			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'), 'confirm'),
				array('run', false, __('Run'))
			);

			$actions->appendChild(Widget::Apply($options));

			$this->Form->appendChild($actions);

			// Pagination:
			if ($this->_pagination->pages > 1) {
				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');

				// First:
				$li = new XMLElement('li');
				$li->setValue(__('First'));

				if ($this->_pagination->page > 1) {
					$li->setValue(
						Widget::Anchor(__('First'), $this->generateLink(array(
							'pg' => 1
						)))->generate()
					);
				}

				$ul->appendChild($li);

				// Previous:
				$li = new XMLElement('li');
				$li->setValue(__('&larr; Previous'));

				if ($this->_pagination->page > 1) {
					$li->setValue(
						Widget::Anchor(__('&larr; Previous'), $this->generateLink(array(
							'pg' => $this->_pagination->page - 1
						)))->generate()
					);
				}

				$ul->appendChild($li);

				// Summary:
				$li = new XMLElement('li', __('Page %s of %s', array(
					$this->_pagination->page,
					max($this->_pagination->page, $this->_pagination->pages)
				)));
				$li->setAttribute('title', __('Viewing %s - %s of %s entries', array(
					$this->_pagination->start,
					$this->_pagination->end,
					$this->_pagination->total
				)));
				$ul->appendChild($li);

				// Next:
				$li = new XMLElement('li');
				$li->setValue(__('Next &rarr;'));

				if ($this->_pagination->page < $this->_pagination->pages) {
					$li->setValue(
						Widget::Anchor(__('Next &rarr;'), $this->generateLink(array(
							'pg' => $this->_pagination->page + 1
						)))->generate()
					);
				}

				$ul->appendChild($li);

				// Last:
				$li = new XMLElement('li');
				$li->setValue(__('Last'));

				if ($this->_pagination->page < $this->_pagination->pages) {
					$li->setValue(
						Widget::Anchor(__('Last'), $this->generateLink(array(
							'pg' => $this->_pagination->pages
						)))->generate()
					);
				}

				$ul->appendChild($li);
				$this->Form->appendChild($ul);
			}
		}
	}
