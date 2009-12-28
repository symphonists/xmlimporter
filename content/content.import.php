<?php
	
	require_once(TOOLKIT . '/class.gateway.php');
	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionXmlImporterImport extends AdministrationPage {
		protected $_driver = null;
		protected $_uri = null;
		protected $_fields = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/xmlimporter';
			$this->_driver = $this->_Parent->ExtensionManager->create('xmlimporter');
		}
		
		public function build($context) {
			if (@$context[0] == 'edit' or @$context[0] == 'new') {
				$this->__prepareEdit($context);
				
			} else {
				$this->__prepareIndex();
			}
			
			parent::build($context);
		}
		
	/*-------------------------------------------------------------------------
		Edit
	-------------------------------------------------------------------------*/
		
		public function __prepareEdit($context) {
			
		}
		
		public function __actionNew() {
			$this->__actionEdit();
		}
		
		public function __actionEdit() {
			
		}
		
		public function __viewNew() {
			self::__viewEdit();
		}
		
		public function __viewEdit() {
			
		}
		
	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/
		
		protected $_importers = array();
		protected $_importer = null;
		protected $_status = null;
		
		public function __prepareIndex() {
			header('content-type: text/plain');
			
			$this->_fields = @$_REQUEST['fields'];
			
			$importManager = new XmlImporter($this->_Parent);
			$this->_importers = $importManager->listAll();
			
			// Import now?
			foreach ($this->_importers as $importer) {
				$importer = (object)$importer;
				
				if (isset($_GET[$importer->handle])) {
					$this->_fields['importer'] = $importer->handle;
					$this->_fields['source'] = $_GET[$importer->handle];
					
					$this->_importer = $importManager->create($this->_fields['importer']);
					
					$this->__actionIndex(); return;
				}
			}
			
			if ($this->_fields['importer']) {
				$this->_importer = $importManager->create($this->_fields['importer']);
			}
		}
		
		public function __actionIndex() {
			// Use external data:
			if ($this->_fields['source']) {
				$gateway = new Gateway();
				$gateway->init();
				$gateway->setopt('URL', $this->_fields['source']);
				$gateway->setopt('TIMEOUT', 6);
				
				// Validate data:
				$this->_status = $this->_importer->validate($gateway->exec());
				
				if ($this->_status == Importer::__OK__) {
					$this->_importer->commit();
				}
			}
		}
		
		public function __viewIndex() {
			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Import');
			$this->appendSubheading("Import");
			
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/jquery.js');
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/form.js');
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/form.css', 'screen');
			
		// Essentials ---------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Essentials'));
			
		// Importer -----------------------------------------------------------
			
			$label = Widget::Label('Importer');
			$options = array();
			
			foreach ($this->_importers as $importer) {
				$importer = (object)$importer;
				
				$options[] = array(
					$importer->handle, ($importer->handle == $this->_fields['importer']), $importer->name
				);
			}
			
			$select = Widget::Select(
				"fields[importer]", $options
			);
			$select->setAttribute('id', 'xmlimporter-importer');
			
			$label->appendChild($select);
			
			if (isset($this->_errors["{$sortorder}:page"])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors["{$sortorder}:page"]);
			}
			
			$fieldset->appendChild($label);
			
		// External -----------------------------------------------------------
			
			$label = Widget::Label('Source XML');
			$input = Widget::Input(
				"fields[source]", $this->_fields['source']
			);
			$input->setAttribute('id', 'xmlimporter-source');
			
			$label->appendChild($input);
			
			$fieldset->appendChild($label);
			$this->Form->appendChild($fieldset);
			
		// Report -------------------------------------------------------------
			
			if (!empty($this->_status)) {
				$entries = $this->_importer->getEntries();
				
				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', 'Report'));
				
				// Markup invalid:
				if ($this->_status == Importer::__ERROR_PREPARING__) {
					$fieldset->appendChild(new XMLElement(
						'h3', 'Import Failed'
					));
					
					$list = new XMLElement('ol');
					
					foreach ($this->_importer->getErrors() as $error) {
						$list->appendChild(new XMLElement('li', $error));
					}
					
					$fieldset->appendChild($list);
					
				// Invalid entry:
				} else if ($this->_status == Importer::__ERROR_VALIDATING__) {
					$fieldset->appendChild(new XMLElement(
						'h3', 'Import Failed'
					));

					// Gather statistics:
					$failed = array();
					
					foreach ($entries as $index => $current) if (!is_null($current['errors'])) {
						$current['position'] = $index + 1;
						$failed[] = $current;
					}
					
					$fieldset->appendChild(new XMLElement(
						'p', sprintf(
							'Import failed because %d entries did not validate, a total of %d entries passed.',
							count($failed), count($entries) - count($failed)
						)
					));
					
					foreach ($failed as $index => $current) {
						$fieldset->appendChild(new XMLElement(
							'h3', sprintf('Import entry #%d', $current['position'])
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
						
						$xml->loadXML($entry->ownerDocument->saveXML($entry));
						
						$source = htmlentities($xml->saveXML($xml->documentElement), ENT_COMPAT, 'UTF-8');
						
						$fieldset->appendChild(new XMLElement(
							'pre', "<code>{$source}</code>"
						));
						
						foreach($current['values'] as $field => $value) {
							$values[$field] = htmlentities($value);
						}
						
						ob_start();
						var_dump($values);
						$values_array = ob_get_contents();
						ob_end_clean();
						
						$fieldset->appendChild(new XMLElement(
							'pre',
							"<code>" . $values_array . "</code>"
						));
					}
					
				// Passed:
				} else {
					$fieldset->appendChild(new XMLElement(
						'h3', 'Import Complete'
					));
					
					$importer_result = array(
						'created' => 0,
						'updated' => 0,
						'skipped' => 0
					);
					
					foreach($entries as $entry) {
						$importer_result[$entry['entry']->get('importer_status')]++;
					}
					
					$fieldset->appendChild(new XMLElement(
						'p', sprintf(
							'Import completed successfully: %d new entries were created, %d updated, and %d skipped.',
							$importer_result['created'],
							$importer_result['updated'],
							$importer_result['skipped']
						)
					));
					
				}
				
				$this->Form->appendChild($fieldset);
			}
			
		// Footer -------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[import]', 'Start Import',
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);
			
			$this->Form->appendChild($div);
		}
	}
	
?>