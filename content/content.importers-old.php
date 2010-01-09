<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	
	require_once(EXTENSIONS . '/xmlimporter/lib/class.importer.php');
	
	class contentExtensionXmlImporterImporters extends AdministrationPage {
		protected $_driver = null;
		protected $_uri = null;
		
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
		
		
	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/
		
		protected $_pagination = null;
		protected $_column = 'name';
		protected $_columns = array();
		protected $_direction = 'name';
		protected $_templates = array();
		
		public function __prepareIndex() {
			$manager = new XmlImporter($this->_Parent);
			$this->_importers = $manager->listAll();			
		}
		
		public function __actionIndex() {
			
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; Importers');
			
			$this->appendSubheading(__('XML Importers'), Widget::Anchor(
				__('Create New'), $this->_Parent->getCurrentPageURL() . 'edit/', __('Create New'), 'create button'
			));
			
			$tableHead = array(
				array('Name', 'col'),
				array('Root Expression', 'col'),
				array('Run', 'col'),
			);
			$tableBody = array();
			
			if (!is_array($this->_importers) or empty($this->_importers)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
				
			} else {
				foreach ($this->_importers as $importer) {
					$importer = (object)$importer;
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$this->_driver->truncateValue($importer->name),
							$this->_uri . "/importers/edit/{$importer->handle}/"
						)
					);
					$col_name->appendChild(Widget::Input("items[{$importer->id}]", null, 'checkbox'));
					
					$col_root = Widget::TableData($importer->root);
					
					$col_run = Widget::TableData(
						Widget::Anchor(
							'Run',
							$this->_uri . "/import/?{$importer->handle}"
						)
					);
					
					$tableBody[] = Widget::TableRow(array(
						$col_name, $col_root, $col_run
					));
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
		}

	
	/*-------------------------------------------------------------------------
		Edit
	-------------------------------------------------------------------------*/
		
		public function __prepareEdit($context) {
			$manager = new XmlImporter($this->_Parent);
			
			$this->_importer = $this->_importer = $manager->create($context[1]);
			if (!$this->_importer) $this->_importer = new Importer($this->_Parent);
			
			if (isset($_POST['action']['save'])) {
				$this->__actionEdit($context);
			}
		}
		
		public function __actionEdit($context) {
			if (@array_key_exists('delete', $_POST['action'])) {
				$this->__actionEditDelete();
			}
			
			else {
				$this->__actionEditNormal($context);
			}
		}
		
		public function __actionEditDelete() {
			header('content-type: text/plain');
			
			$about = $this->_importer->about();
			
			exit;
			
			General::deleteFile($this->_fields['about']['html-formatter-file']);
			
			redirect("{$this->_uri}/formatters/");
		}
		
		public function __actionEditNormal($context) {
			$fields = $_POST['fields'];
			$original_name = $context[1];
			
			General::deleteFile(EXTENSIONS . '/xmlimporter/importers/import.' . $original_name . '.php');
			
			$class_name = Lang::createHandle($fields['name']);
			$class_name = preg_replace('/-/', '_', $class_name);
			
			$file = EXTENSIONS . '/xmlimporter/template/importer.tpl';
			$tpl = file_get_contents($file);
			
			$tpl = str_replace('!CLASS_NAME!', $class_name, $tpl);
			$tpl = str_replace('!NAME!', addslashes($fields['name']), $tpl);
			$tpl = str_replace('!SECTION!', $fields['section'], $tpl);
			$tpl = str_replace('!ROOT_EXPRESSION!', addslashes($fields['for-each']), $tpl);
			$tpl = str_replace('!CAN_UPDATE!', ($fields['can-update'] == 'yes') ? 'true' : 'false', $tpl);
			
			$unique_field = null;
			
			$mappings = 'array(' . self::CRLF;
			
			foreach($fields as $section => $mapping) {
				if ($section == 'section-' . $fields['section']) {
					foreach($mapping as $id => $meta) {
						$id = preg_replace('/field-/', '', $id);
						
						if (trim($meta['xpath']) == '') continue;
						
						$mappings .= 'array(' . self::CRLF;
						$mappings .= " 'field' => " . $id . ", " . self::CRLF;
						$mappings .= " 'xpath' => '" . addslashes(trim($meta['xpath'])) . "', " . self::CRLF;
						$mappings .= " 'php' => '" . addslashes(trim($meta['php'])) . "'" . self::CRLF;
						$mappings .= '),' . self::CRLF;
						
						if ($meta['unique']) $unique_field = $id;
						
					}
				}				
			}
			
			$mappings .= ')';
			$tpl = str_replace('!MAPPING!', $mappings, $tpl);
			$tpl = str_replace('!UNIQUE_FIELD!', (is_null($unique_field)) ? 'null' : $unique_field, $tpl);
			
			General::writeFile(EXTENSIONS . '/xmlimporter/importers/import.' . $class_name . '.php', $tpl, $this->_Parent->Configuration->get('write_mode', 'file'));
			
			redirect(URL . '/symphony/extension/xmlimporter/importers/edit/'.$class_name.'/'.($this->_context[0] == 'new' ? 'created' : 'saved') . '/');
		}
		
		public function __viewNew($context) {
			$this->__actionEdit($context);
		}
		
		public function __viewEdit() {
			
			if(isset($this->_context[2])) {
				switch($this->_context[2]) {					
					case 'saved':
						$this->pageAlert('Importer updated.', Alert::SUCCESS);
						break;
					case 'created':
						$this->pageAlert('Importer created.', Alert::SUCCESS);
						break;
				}
			}
			
			$this->setPageType('form');
			$this->addStylesheetToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.css', 'screen', 100);
			$this->addScriptToHead(URL . '/extensions/xmlimporter/assets/xmlimporter.js', 101);
			
			$about = $this->_importer->about();
			$_mapping = $this->_importer->getFieldMapping();
			$mapping = array();
			foreach($_mapping as $map) {
				$mapping[$map['field']]['xpath'] = $map['xpath'];
				$mapping[$map['field']]['php'] = $map['php'];
			}
			
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('XML Importers') . ' &ndash; ' . (
				@$about['name'] ? $about['name'] : __('Untitled')
			));
			$this->appendSubheading("<a href=\"{$this->_uri}/importers/\">" . __('XML Importers') . "</a> &raquo; " . (
				@$about['name'] ? $about['name'] : __('Untitled')
			));
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$column = new XMLElement('div');
			
		// Title -------------------------------------------------------------
			
			$label = Widget::Label(__('Title'));		
			$label->appendChild(Widget::Input(
				'fields[name]', General::sanitize(stripslashes($about['name']))
			));
			$column->appendChild($label);
			
			$label = Widget::Label(__('Root Expression (for each)'));		
			$label->appendChild(Widget::Input(
				'fields[for-each]', stripslashes($this->_importer->getRootExpression())
			));
			$column->appendChild($label);
			
			$group->appendChild($column);		
			
			
		// Section -------------------------------------------------------------
			
			$column = new XMLElement('div');
			
			$sectionManager = new SectionManager($this->_Parent);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array();
			
			if (is_array($sections)) foreach ($sections as $section) {
				$options[] = array($section->get('id'), ($this->_importer->getSection() == $section->get('id')), $section->get('name'));
			}
			
			$label = Widget::Label(__('Section'));		
			$label->appendChild(Widget::Select(
				'fields[section]', $options
			));
			$column->appendChild($label);
			
			$label = Widget::Label();
			$input = Widget::Input('fields[can-update]', 'yes', 'checkbox', ($this->_importer->canUpdate()) ? array('checked' => 'checked') : NULL);
			$label->setValue($input->generate(false) . ' Can update existing entries');
			$column->appendChild($label);
			
			$group->appendChild($column);
			$fieldset->appendChild($group);
			
			$this->Form->appendChild($fieldset);
		
		// Fields -----------------------------------------------------------
			
			if (is_array($sections)) {
				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Fields')));
			
				foreach($sections as $section) {
					$section_div = new XMLElement('div', null, array('class' => 'subsection section-fields', 'id' => 'section-' . $section->get('id')));
					$section_fields = new XMLElement('ol');
				
					foreach($section->fetchFields() as $field) {
						$li = new XMLElement('li');
					
						$li->appendChild(new XMLElement('h4', $field->get('label')));
					
						$label = Widget::Label();
						$label->setAttribute('class', 'meta');
						$input = Widget::Input('fields[section-'.$section->get('id').'][field-'.$field->get('id').'][unique]', 'yes', 'checkbox', ($this->_importer->getUniqueField() == $field->get('id')) ? array('checked'=>'checked') : null);
						$label->setValue($input->generate(false) . ' Is unique');
						$li->appendChild($label);
					
						$group = new XMLElement('div');
						$group->setAttribute('class', 'group');
					
						$label = Widget::Label('XPath Expression');
						$input = Widget::Input('fields[section-'.$section->get('id').'][field-'.$field->get('id').'][xpath]', stripslashes($mapping[$field->get('id')]['xpath']));
						$label->appendChild($input);
						$group->appendChild($label);
					
						$label = Widget::Label('PHP Function <i>Optional</i>');
						$input = Widget::Input('fields[section-'.$section->get('id').'][field-'.$field->get('id').'][php]', htmlentities(stripslashes($mapping[$field->get('id')]['php'])));
						$label->appendChild($input);
						$group->appendChild($label);
					
						$li->appendChild($group);
					
						$section_fields->appendChild($li);
					}
				
					$section_div->appendChild($section_fields);
					$fieldset->appendChild($section_div);
				}
			
				$this->Form->appendChild($fieldset);
			}
			
		// Controls -----------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			
			$button = Widget::Input(
				'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create Importer')),
				'submit', array('accesskey' => 's')
			);
			
			// No sections, that's an error!
			if (!is_array($sections)) {
				$this->pageAlert(__('No sections have been created.'), Alert::ERROR);
				$button->setAttribute('disabled', 'disabled');
			}
			
			$div->appendChild($button);
			
			if ($this->_editing) {
				$button = new XMLElement('button', 'Delete');
				$button->setAttributeArray(array(
					'name'		=> 'action[delete]',
					'class'		=> 'confirm delete',
					'title'		=> 'Delete this formatter'
				));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
		}
	}
	
?>
