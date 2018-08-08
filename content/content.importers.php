<?php

	require_once(TOOLKIT . '/class.gateway.php');
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.frontendpage.php');
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
		protected $_summaries = array();
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

		public function build(array $context = array()) {
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
			$remote = false;

			if (isset($_GET['source'])) {
				$source = $_GET['source'];
				$remote = true;
			}

			array_shift($context);

			ini_set('html_errors', false);

			foreach ($context as $handle) {
				$importer = $importManager->create($handle);
				$importer->setContext($this->getContext());

				###
				# Delegate: XMLImporterPreRun
				# Description: Before running an importer. Current importer object is provided.
				Symphony::ExtensionManager()->notifyMembers(
					'XMLImporterPreRun', '/xmlimporter/importers/run/',
					array(
						'importer'	=> &$importer
					)
				);

				if($importer === false) {
					Symphony::Log()->writeToLog(__('The XMLImporter %s could not be found.', array($handle)), E_USER_ERROR, true);
					continue;
				}
				else {
					$status = $importer->validate($source, $remote);
				}

				if ($_GET['force'] == 'yes') {
					$status = XMLImporter::__PARTIAL_OK__;
				}

				if (in_array($status, array(XMLImporter::__OK__, XMLImporter::__PARTIAL_OK__))) {
					$importer->commit($status);
				}

				###
				# Delegate: XMLImporterPostRun
				# Description: After running an importer. Current importer object and status are provided.
				Symphony::ExtensionManager()->notifyMembers(
					'XMLImporterPostRun', '/xmlimporter/importers/run/',
					array(
						'importer'	=> &$importer,
						'status'	=> &$status
					)
				);

				$this->_runs[] = array(
					'importer'	=> $importer,
					'status'	=> $status
				);
			}

			ini_set('html_errors', $html_errors);
		}

		public function __viewRun() {
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/prism.css', 'screen', 101);
			$this->addElementToHead(
				new XMLElement('script', null, array(
					'src' => URL . '/extensions/xmlimporter/assets/prism.js',
					'data-manual' => 'true'
				)),
				102
			);
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.css', 'screen', 103);
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.js', 104);

			$this->setPageType('form');
			$this->setTitle(__(
				'%1$s &ndash; %2$s &ndash; %3$s',
				array(
					__('Symphony'),
					__('XML Importers'),
					__('Status Report')
				)
			));

			$this->appendSubheading(__('Status Report'), $button);
			$this->insertBreadcrumbs(array(
				Widget::Anchor(__('XML Importers'), $this->_uri . '/importers/'),
			));

			if(empty($this->_runs)) {
				$this->pageAlert(__('The XMLImporter %s could not be found.', array('<code>' . $this->_context[1] . '</code>')), Alert::ERROR);
				return false;
			}

			// Summary
			$this->prepareSummary();

			// Table header
			$tableHead = array(
				array(__('Importer'), 'col'),
				array(__('Status'), 'col'),
				array(__('Created'), 'col'),
				array(__('Updated'), 'col'),
				array(__('Skipped'), 'col'),
				array(__('Failed'), 'col'),
				array(__('Action'), 'col')
			);

			// Table body
			$tableBody = array();
			foreach ($this->_summaries as $summary) {
				if (Symphony::Author()->get('user_type') === 'developer') {
					$importer = Widget::Anchor($summary['name'], $this->_uri . '/importers/edit/' . $summary['handle']);
				} else {
					$importer = $summary['name'];
				}

				$tableBody[] = Widget::TableRow(
					array(
						Widget::TableData($importer),
						Widget::TableData(
							Widget::Anchor($summary['status'], '#report-' . $summary['handle'])
						),
						Widget::TableData($summary['created']),
						Widget::TableData($summary['updated']),
						Widget::TableData($summary['skipped']),
						Widget::TableData($summary['failed']),
						Widget::TableData($summary['action'], ($summary['action'] === __('None') ? 'inactive' : ''))
					),
					$summary['class']
				);
			}

			// Summary table
			$table = Widget::Table(
				Widget::TableHead($tableHead),
				null,
				Widget::TableBody($tableBody),
				null,
				null,
				array(
					'role' => 'directory',
					'aria-labelledby' => 'symphony-subheading',
					'data-interactive' => 'data-interactive'
				)
			);
			$this->Form->appendChild($table);

			// Errors
			$this->buildErrorSummary();

			###
			# Delegate: XMLImporterImportPostRun
			# Description: All Importers run successfully
			Symphony::ExtensionManager()->notifyMembers(
				'XMLImporterImportPostRun', '/xmlimporter/importers/run/',
				array(
					'created' => $importer_result['created'],
					'updated' => $importer_result['updated'],
					'skipped' => $importer_result['skipped'],
					'failed'  => $importer_result['failed'],
					'entries' => $entries
				)
			);
		}

		private function prepareSummary() {
			foreach ($this->_runs as $run) {
				$importer = $run['importer'];
				$status = $run['status'];
				$entries = $importer->getEntries();
				$about = $importer->about();

				$failed = array();
				$summary = array(
					'name' => $about['name'],
					'handle' => Lang::createHandle($about['name']),
					'class' => '',
					'status' => '',
					'action' => __('None'),
					'created' => 0,
					'updated' => 0,
					'skipped' => 0,
					'failed'  => 0,
					'errors' => array()
				);

				// Markup invalid:
				if ($status == XMLImporter::__ERROR_PREPARING__) {
					$summary['class'] = 'status-error';
					$summary['status'] = __('Invalid');
					$summary['failed'] = 'all';
					foreach ($importer->getErrors() as $error) {
						$summary['errors'][] = $error;
					}

					$summary['action'] = Widget::Anchor(__('Check report'),	'#report-' . $summary['handle']);
				}

				// Invalid entry:
				else if ($status == XMLImporter::__ERROR_VALIDATING__) {
					$summary['class'] = 'status-error';
					$summary['status'] = __('Invalid entries');

					// Gather statistics:
					foreach ($entries as $index => $current) {
						if (!empty($current['errors'])) {
							$current['position'] = $index + 1;
							$summary['errors'][] = $current;
						}
					}

					$summary['failed'] = count($summary['errors']);
					$summary['skipped'] = count($entries) - $summary['failed'];

					// Import valid anyway
					if ($summary['skipped'] > 0) {
						$button = Widget::Anchor(
							__('Import valid entries'),
							$this->_uri . '/importers/run/' . $this->_context[1] . '/?force=yes',
							__('Import valid entries'),
							'button'
						);

						$summary['action'] = $button;
					}
					else {
						$summary['action'] = Widget::Anchor(__('Check report'),	'#report-' . $summary['handle']);
					}

					###
					# Delegate: XMLImporterImportPostRunErrors
					# Description: Notify Delegate for Errors
					Symphony::ExtensionManager()->notifyMembers(
						'XMLImporterImportPostRunErrors', '/xmlimporter/importers/run/',
						array(
							'errors' => $current['errors']
						)
					);
				}

				// Invalid entry:
				else if ($status == XMLImporter::__PARTIAL_OK__) {
					$summary['class'] = 'status-error';
					$summary['status'] = __('Partially complete');

					// Gather statistics:
					foreach ($entries as $index => $current) {
						if (!empty($current['errors'])) {
							$current['position'] = $index + 1;
							$summary['errors'][] = $current;
						}

						$summary[$current['entry']->get('importer_status')]++;
					}

					$summary['action'] = Widget::Anchor(__('Check report'),	'#report-' . $summary['handle']);
				}

				// Passed:
				else {
					$summary['class'] = 'status-ok';
					$summary['status'] = __('Complete');
					foreach ($entries as $entry) {
						$summary[$entry['entry']->get('importer_status')]++;
					}
				}

				$this->_summaries[] = $summary;
			}
		}

		private function buildErrorSummary() {
			foreach ($this->_summaries as $summary) {
				$errors = $summary['errors'];

				$wrapper = new XMLElement('div', null, array('class' => 'xml-importer-reports'));
				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('id', 'report-' . $summary['handle']);
				$fieldset->setAttribute('class', 'settings xml-importer-run');
				$fieldset->appendChild(new XMLElement('legend', __('Report for %s', array($summary['name']))));

				// No errors
				if (empty($errors)) {
					$message = new XMLElement('p', __('The import has run successfully without any errors.'));
					$remove = new XMLElement('a', __('Hide report'), array('href' => '#wrapper', 'class' => 'xml-importer-report-hide'));
					$message->appendChild($remove);
					$fieldset->appendChild($message);
				}

				// Display global errors
				else if($summary['status'] === __('Invalid')) {
					$message = new XMLElement('p', __('The import failed due to the following errors:'));
					$remove = new XMLElement('a', __('Hide report'), array('href' => '#wrapper', 'class' => 'xml-importer-report-hide'));
					$message->appendChild($remove);
					$fieldset->appendChild($message);

					$list = new XMLElement('ul');
					foreach ($summary['errors'] as $error) {
						$item = new XMLElement('li', $error);
						$list->appendChild($item);
					}
					$fieldset->appendChild($list);
				}

				// Display entry errors
				else {
					$message = new XMLElement('p', __('The import failed due to the following errors:'));
					$remove = new XMLElement('a', __('Hide report'), array('href' => '#wrapper', 'class' => 'xml-importer-report-hide'));
					$message->appendChild($remove);
					$fieldset->appendChild($message);

					$list = new XMLElement('ol', null, array('class' => 'xml-importer-errors'));
					foreach ($summary['errors'] as $entry) {
						$item = new XMLElement('li');
						$title = new XMLElement('h2', __('Entry #%d', array($entry['position'])));

						$details = new XMLElement('ul');
						foreach($entry['errors'] as $error) {
							$details->appendChild(new XMLElement('li', $error));
						}

						$item->appendChild($title);
						$item->appendChild($details);
						$list->appendChild($item);

						// Source
						$duplicator = new XMLElement('div', null, array('class' => 'frame'));
						$sources = new XMLElement('ol');
						$duplicator->appendChild($sources);
						$item->appendChild($duplicator);

						// Input
						$source = new XMLElement('li');
						$header = new XMLElement('header', null, array('class' => 'frame-header'));
						$input = new XMLElement('h4', '<strong>' . __('Source Data') . '</strong><span class="type">' . __('Datasource XML') . '</span>');
						$header->appendChild($input);
						$source->appendChild($header);

						$xml = new DOMDocument();
						$xml->preserveWhiteSpace = false;
						$xml->formatOutput = true;

						if(is_null($entry['element']->ownerDocument)) {
							$xml->loadXML($entry['element']->saveXML());
						}
						else {
							$xml->loadXML($entry['element']->ownerDocument->saveXML($entry['element']));
						}

						$code = htmlentities($xml->saveXML($xml->documentElement), ENT_COMPAT, 'UTF-8');
						$source->appendChild(new XMLElement(
							'pre',
							"<code>{$code}</code>",
							array('class' => 'language-markup', '')
						));
						$sources->appendChild($source);

						// Output
						$source = new XMLElement('li');
						$header = new XMLElement('header', null, array('class' => 'frame-header'));
						$output = new XMLElement('h4','<strong>' . __('Import Data') . '</strong><span class="type">' . __('Symphony Entry') . '</span>');
						$header->appendChild($output);
						$source->appendChild($header);

						$values = $entry['values'];
						array_walk_recursive($values, function (&$value) {
						    $value = htmlentities($value);
						});

						$source->appendChild(new XMLElement(
							'pre',
							"<code>" . preg_replace('/"(.+)":/', '$1:', json_encode($values, JSON_PRETTY_PRINT)) . "</code>",
							array('class' => 'language-javascript')
						));
						$sources->appendChild($source);
					}

					$fieldset->appendChild($list);
				}

				$wrapper->appendChild($fieldset);
				$this->Form->appendChild($wrapper);
			}
		}

		public function addFailedEntries(XMLElement &$fieldset, array $failed_entries) {
			foreach ($failed_entries as $index => $current) {
				$fieldset->appendChild(new XMLElement(
					'h3', __('Import entry #%d', array($current['position']))
				));

			// Errors -------------------------------------------------

				$list = new XMLElement('ol');

				foreach ($current['errors'] as $error) {
					$list->appendChild(new XMLElement('li', $error));
				}

				$fieldset->appendChild($list);

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

				$values = $current['values'];
				array_walk_recursive($values, function (&$value) {
				    $value = htmlentities($value);
				});

				$fieldset->appendChild(new XMLElement(
					'pre',
					"<code>" . json_encode($values, JSON_PRETTY_PRINT) . "</code>"
				));
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
				// Support {$root} and {$workspace}
				$evaluated_source = str_replace('{$root}', URL, $fields['source']);
				$evaluated_source = str_replace('{$workspace}', WORKSPACE, $evaluated_source);

				$ds = DatasourceManager::create($fields['source'], $param_pool, true);

				// Not a DataSource (legacy)
				if(!($ds instanceof Datasource)) {
					if(!filter_var($evaluated_source, FILTER_VALIDATE_URL)) {
						$this->_errors['source'] = __('Source is not a valid URL.');
					}
				}
				// DataSource output
				else {
					$xml = $ds->execute($param_pool);

					if(isset($ds->dsParamNAMESPACES)) {
						foreach($ds->dsParamNAMESPACES as $name => $uri) {
							$fields['namespaces'][] = array(
								'name' => $name,
								'uri' => $uri
							);
						}
					}

					if($xml->getAttribute('valid') == 'false') {
						$this->_errors['source'] = __('Failed to retrieve data from source: %s', array($xml->generate()));
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
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/prism.css', 'screen', 101);
			$this->addElementToHead(
				new XMLElement('script', null, array(
					'src' => URL . '/extensions/xmlimporter/assets/prism.js',
					'data-manual' => 'true'
				)),
				102
			);
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

			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Choose a DataSource that contains the data you wish to import. Use an XPath expression to select which elements from the source XML to include.'));
			$fieldset->appendChild($help);

			$options = array();
			$options[] = array();

			$datasources = DatasourceManager::listAll();
			foreach($datasources as $index => $ds) {
				$options[] = array($ds['handle'], $ds['handle'] == $this->_fields['source'], $ds['name']);
			}

			$label = Widget::Label(__('Data Source'));
			$label->appendChild(Widget::Select('fields[source]', $options, array('id' => 'ds-context')));

			if (isset($this->_errors['source'])) {
				$label = Widget::Error($label, $this->_errors['source']);
			}

			$fieldset->appendChild($label);

			$label = Widget::Label(__('Preview'));
			$pre = new XMLElement('pre', null, array(
				'id' => 'xml-importer-preview',
				'class' => 'language-markup'
			));
			$code = new XMLElement('code', htmlspecialchars("<data>\n\t<error>" . __('No DataSource selected.') . "</error>\n</data>"), array(
				'class' => 'language-markup'
			));
			$pre->appendChild($code);
			$label->appendChild($pre);
			$fieldset->appendChild($label);

		// Included Elements --------------------------------------------------

			$label = Widget::Label(__('Included Elements'));
			$input = Widget::Input(
				'fields[included-elements]',
				General::sanitize(
					isset($this->_fields['included-elements'])
						? $this->_fields['included-elements']
						: null
				)
			);
			$input->setAttribute('placeholder', '/data');
			$label->appendChild($input);

			if (isset($this->_errors['included-elements'])) {
				$label = Widget::Error($label, $this->_errors['included-elements']);
			}

			$fieldset->appendChild($label);
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

					//entry ID markup
						$field_id = 'entry-id';
						// $field_name = "fields[fields][-1]";
						$field_name = "fields[fields][id]"; //not sure that is correct but should avoid conflicts with other fields
						$field_data = null;
						$template_index = null;
						if (isset($this->_fields['fields'])) {
							foreach ($this->_fields['fields'] as $i => $temp_data) {
								if ($temp_data['field'] != $field_id) continue;
								$field_data = $temp_data;
								$template_index = $i;
								// always force the unique to entry id if exists
								$this->_fields['unique-field'] = "entry-id";
							}
						}
						$li = new XMLElement('li',"<h4>Entry ID</h4>",array('class'=>'unique template','data-type'=>'entry-id'));
						$li->appendChild(new XMLElement('header', '<h4>Entry ID</h4>'));

						$input = Widget::Input("{$field_name}[field]", $field_id, 'hidden');
						$li->appendChild($input);

						$group = new XMLElement('div');
						$group->setAttribute('class', 'two columns');

						$label = Widget::Label(__('XPath Expression'));
						$label->setAttribute('class', 'column');
						$input = Widget::Input(
							"{$field_name}[xpath]",
							General::sanitize(
								( isset($field_data) && isset($field_data['xpath']) )
									? $field_data['xpath']
									: null
							)
						);
						$label->appendChild($input);
						$group->appendChild($label);

						$label = Widget::Label(__('PHP Function'));
						$label->appendChild(new XMLElement('i', __('Optional')));
						$label->setAttribute('class', 'column');
						$input = Widget::Input(
							"{$field_name}[php]",
							General::sanitize(
								( isset($field_data) && isset($field_data['php']) )
									? $field_data['php']
									: null
							)
						);
						$label->appendChild($input);
						$group->appendChild($label);

						$li->appendChild($group);

						$label = Widget::Label();
						$label->setAttribute('class', 'meta');					

						$label->setValue(__('Entry ID is used to determine uniqeness.'));

						$li->appendChild($label);
						$section_fields->appendChild($li);

						if (!is_null($field_data)){
							//clone to avoid re-setting all the variables
							$newLi = clone $li;
							$newLi->setAttribute('class', 'unique');

							//an entry ID must be unique - check only when showing in selected view
							$input = Widget::Input("fields[unique-field]", $field_id, 'radio');
							$input->setAttribute("checked","checked");
							$input->setAttribute("style","display:none");
							$newLi->appendChild($input);

							//append item to the field list
							$section_fields->appendChild($newLi);
						}

					//end entry id

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
						$template_index = null;

						if (isset($this->_fields['fields'])) {
							foreach ($this->_fields['fields'] as $i => $temp_data) {
								if ($temp_data['field'] != $field_id) continue;

								$field_data = $temp_data;
								$template_index = $i;
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

						if (isset($this->_errors['fields'][$template_index])) {
							$label = Widget::Error($label, $this->_errors['fields'][$template_index]);
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
				'order' => $this->_table_direction
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
				'source'		=> array(__('Source'), true),
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
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.css', 'screen', 103);
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.js', 104);

			// Actions
			$actions = array();
			$importers = array();

			foreach ($this->_importers as $importer) {
				$importers[] = $importer['about']['handle'];
			}

			$actions[] = Widget::Anchor(
				__('Run all'), $this->_uri . '/importers/run/' . implode('/', $importers) . '/',
				__('Run all XML Importers'), 'button'
			);

			if (Symphony::Author()->get('user_type') === 'developer') {
				$actions[] = Widget::Anchor(
					__('Create New'), $this->_uri . '/importers/new/',
					__('Create a new XML Importer'), 'create button'
				);
			}

			// Heading
			$this->appendSubheading(__('XML Importers'), $actions);

			// Table
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
						'order' => $direction
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
				// Was this importer generated by Version 3, or is it using the legacy method?
				if (isset($importer['about']['version'])) {
					if (Symphony::Author()->get('user_type') === 'developer') {
						$col_name = Widget::TableData(Widget::Anchor(
							$importer['about']['name'],
							"{$this->_uri}/importers/edit/{$importer['about']['handle']}/"
						));
					} else {
						$col_name = Widget::TableData($importer['about']['name']);
					}
					$class = '';
				} else {
					$col_name = Widget::TableData($importer['about']['name']);
					$class = 'status-notice';
				}

				$col_name->appendChild(Widget::Input(
					"items[{$importer['about']['handle']}]",
					null, 'checkbox'
				));

				$col_date = Widget::TableData(DateTimeObj::get(
					__SYM_DATETIME_FORMAT__, strtotime($importer['about']['updated'])
				));

				if (!empty($importer['source'])) {
					$handle = General::sanitize($importer['source']);
					$datasources = DatasourceManager::listAll();

					if(!empty($datasources[$handle]['name'])) {
						$source = $datasources[$handle]['name'];
					}
					else {
						$source = $handle;
					}

					$col_url = Widget::TableData($source);
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
					$class
				);
			}

			$table = Widget::Table(
				Widget::TableHead($tableHead),
				null,
				Widget::TableBody($tableBody),
				'selectable',
				null,
				array('role' => 'directory', 'aria-labelledby' => 'symphony-subheading', 'data-interactive' => 'data-interactive')
			);

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

		/**
		 * Given the REQUEST, parse out all the rubbish and emulate what the Frontend
		 * would do. This ensures any datasources that rely on URL parameters can continue
		 * to use them.
		 *
		 * @return array
		 */
		public function getContext()
		{
			$context = array();

			if (isset($_REQUEST) && is_array($_REQUEST)) {
				foreach ($_REQUEST as $key => $val) {
					if (in_array($key, array('symphony-page', 'mode'))) {
						continue;
					}

					// If the browser sends encoded entities for &, ie. a=1&amp;b=2
					// this causes the $_GET to output they key as amp;b, which results in
					// $url-amp;b. This pattern will remove amp; allow the correct param
					// to be used, $url-b
					$key = preg_replace('/(^amp;|\/)/', null, $key);

					// If the key gets replaced out then it will break the XML so prevent
					// the parameter being set.
					$key = General::createHandle($key);
					if (!$key) {
						continue;
					}

					// Handle ?foo[bar]=hi as well as straight ?foo=hi RE: #1348
					if (is_array($val)) {
						$val = General::array_map_recursive(array('FrontendPage', 'sanitizeParameter'), $val);
					} else {
						$val = FrontendPage::sanitizeParameter($val);
					}

					$context['url-' . $key] = $val;
				}
			}

			return array(
				'env' => array(
					'url' => $context,
					'pool' => array(
						'root' => URL,
						'workspace' => URL . '/workspace'
					)
				)
			);
		}
	}
