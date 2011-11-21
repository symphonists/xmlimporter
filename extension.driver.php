<?php

	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');
	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimportermanager.php');

	class Extension_XmlImporter extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function about() {
			return array(
				'name'			=> 'XML Importer',
				'version'		=> '1.1.0',
				'release-date'	=> '2011-11-21',
				'author'		=> array(
					array(
						'name'		=> 'Nick Dunn',
						'website'	=> 'http://nick-dunn.co.uk'
					),
					array(
						'name'		=> 'Rowan Lewis',
						'website'	=> 'http://rowanlewis.com',
						'email'		=> 'me@rowanlewis.com'
					),
					array(
						'name'		=> 'Brendan Abbott',
						'website'	=> 'brendan@bloodbone.ws'
					)
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
					'location'	=> __('Blueprints'),
					'name'		=> __('XML Importers'),
					'link'		=> '/importers/'
				)
			);
		}

		public function initializeAdmin($context) {
			$page = $context['parent']->Page;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function countXMLImporters() {
			$xim = new XMLImporterManager();
			$results = 0;

			foreach ($xim->listAll() as $about) {
				$importer = $xim->create($about['handle']);

				if (!$importer->allowEditorToParse()) continue;

				$results++;
			}

			return $results;
		}

		public function getXMLImporters($column = 'name', $direction = 'asc', $page = 1, $length = 100000) {
			$xim = new XMLImporterManager();
			$results = array();

			foreach ($xim->listAll() as $about) {
				$importer = $xim->create($about['handle']);

				if (!$importer->allowEditorToParse()) continue;

				$data = $importer->options();
				$data['about'] = $about;
				$results[] = $data;
			}

			// Sorting:
			if ($column == 'name') {
				usort($results, array($this, 'getXMLImportersSortByName'));
			}

			else if ($column == 'description') {
				usort($results, array($this, 'getXMLImportersSortByDescription'));
			}

			else if ($column == 'url') {
				usort($results, array($this, 'getXMLImportersSortByURL'));
			}

			else if ($column == 'elements') {
				usort($results, array($this, 'getXMLImportersSortByElements'));
			}

			else if ($column == 'modified') {
				usort($results, array($this, 'getXMLImportersSortByModified'));
			}

			else if ($column == 'author') {
				usort($results, array($this, 'getXMLImportersSortByAuthor'));
			}

			if ($direction != 'asc') {
				$results = array_reverse($results);
			}

			// Pagination:
			$results = array_slice($results, ($page - 1) * $length, $length);

			return $results;
		}

		protected function getXMLImportersSortByName($a, $b) {
			return strcmp($a['about']['name'], $b['about']['name']);
		}

		protected function getXMLImportersSortByDescription($a, $b) {
			return strcmp($a['about']['description'], $b['about']['description']);
		}

		protected function getXMLImportersSortByURL($a, $b) {
			return strcmp($a['source'], $b['source']);
		}

		protected function getXMLImportersSortByElements($a, $b) {
			return strcmp($a['included-elements'], $b['included-elements']);
		}

		protected function getXMLImportersSortByModified($a, $b) {
			return strtotime($a['about']['updated']) > strtotime($b['about']['updated']);
		}

		protected function getXMLImportersSortByAuthor($a, $b) {
			return strcmp($a['about']['author']['name'], $b['about']['author']['name']);
		}

		public function getXMLImporter($name) {
			$xim = new XMLImporterManager();
			$importer = $xim->create($name);

			$data = $importer->options();
			$data['about'] = $importer->about();

			return $data;
		}

		public function setXMLImporter(&$name, &$error, $new) {
			$template = file_get_contents(EXTENSIONS . '/xmlimporter/templates/xml-importer.php');
			$old = (!empty($name) ? $this->getXMLImporter($name) : array());

			// Update author:
			if (!isset($new['about']['author'])) {
				$new['about']['author'] = array(
					'name'		=> Symphony::Engine()->Author->getFullName(),
					'website'	=> URL,
					'email'		=> Symphony::Engine()->Author->get('email')
				);
			}

			// Update dates:
			$new['about']['created'] = DateTimeObj::getGMT('c', @strtotime($new['about']['created']));
			$new['about']['updated'] = DateTimeObj::getGMT('c');

			// New name:
			$name = str_replace('-', '', Lang::createHandle($new['about']['name']));

			// Create new file:
			if (strpos(@$new['about']['file'], dirname(__FILE__)) === 0) {
				$rootdir = dirname(__FILE__);
			}

			else {
				$rootdir = WORKSPACE;
			}

			$filemode = Symphony::Configuration()->get('write_mode', 'file');
			$filename = sprintf(
				'%s/xml-importers/xml-importer.%s.php',
				$rootdir, $name
			);
			$dirmode = Symphony::Configuration()->get('write_mode', 'directory');
			$dirname = dirname($filename);

			// Make sure the directory exists:
			if (!is_dir($dirname)) {
				General::realiseDirectory($dirname, $dirmode);
			}

			// Make sure new file can be written:
			if (!is_writable($dirname) or (file_exists($filename) and !is_writable($filename))) {
				$error = __('Cannot save formatter, path is not writable.');
				return false;
			}

			$filedata = sprintf(
				$template,

				// Class name:
				str_replace(
					' ', '',
					ucwords(
						str_replace('-', ' ', Lang::createHandle($new['about']['name']))
					)
				),

				// Name:
				var_export($new['about']['name'], true),

				// Author:
				var_export($new['about']['author']['name'], true),

				// Website:
				var_export($new['about']['author']['website'], true),

				// Email:
				var_export($new['about']['author']['email'], true),

				// Description:
				var_export($new['about']['description'], true),

				// Dates:
				var_export($new['about']['created'], true),
				var_export($new['about']['updated'], true),

				// Options:
				var_export($new['can-update'], true),
				var_export($new['fields'], true),
				var_export($new['included-elements'], true),
				var_export($new['namespaces'], true),
				var_export($new['source'], true),
				var_export($new['section'], true),
				var_export($new['unique-field'], true)
			);

			// Write file to disk:
			General::writeFile($filename, $filedata, $filemode);

			// Cleanup old file:
			if (
				$filename != @$old['about']['html-formatter-file']
				and file_exists($filename) and @file_exists($old['about']['html-formatter-file'])
			) {
				General::deleteFile($old['about']['html-formatter-file']);
			}

			return true;
		}

		public function validateXPath($expression, $namespaces = array()) {
			$document = new DOMDocument();
			$document->loadXML('<data />');
			$xpath = new DOMXPath($document);
			$html_errors = ini_get('html_errors');
			$exception = null;

			// Register namespaces:
			if (is_array($namespaces)) {
				foreach ($namespaces as $namespace) {
					$xpath->registerNamespace($namespace['name'], $namespace['uri']);
				}
			}

			try {
				ini_set('html_errors', false);
				$xpath->evaluate($expression);
			}

			catch (Exception $exception) { }

			ini_set('html_errors', $html_errors);

			if ($exception) throw $exception;

			return true;
		}

		public function truncateValue($value) {
			$max_length = Symphony::Configuration()->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);

			$value = General::sanitize($value);
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');

			return $value;
		}
	}

?>
