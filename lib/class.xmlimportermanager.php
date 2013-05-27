<?php

	class XMLImporterManager {
		protected $_sort_column = '';
		protected $_sort_direction = '';
		protected $paths = array();

		public function __construct() {
			$this->paths = array(
				WORKSPACE . '/xml-importers',
				EXTENSIONS . '/xmlimporter/xml-importers'
			);

			$extensions = Symphony::ExtensionManager()->listInstalledHandles();

			if (is_array($extensions) and !empty($extensions)) {
				foreach ($extensions as $handle) {
					$path = EXTENSIONS . "/$e/xml-importers";

					if (is_dir($path)) $this->paths[] = $path;
				}
			}
		}

		public function __find($name) {
			foreach ($this->paths as $path) {
				if (is_file("{$path}/xml-importer.{$name}.php")) return $path;
			}

			return false;
		}

		public function __getHandleFromFilename($name) {
			return preg_replace(array('/^xml-importer./i', '/.php$/i'), '', $name);
		}

		public function __getDriverPath($name) {
			return $this->__getClassPath($name) . "/xml-importer.$name.php";
		}

		public function __getClassName($name) {
			return 'xmlimporter' . str_replace('-', '_', $name);
		}

		public function __getClassPath($name) {
			return $this->__find($name);
		}

		protected function __sort($a, $b) {
			if ($this->_sort_direction != 'asc') {
				$t = $b; $b = $a; $a = $t;
			}

			return strnatcasecmp($a[$this->_sort_column], $b[$this->_sort_column]);
		}

		public function about($name){

			$classname = $this->__getClassName($name);
			$path = $this->__getDriverPath($name);

			if(!@file_exists($path)) return false;

			require_once($path);

			$handle = $this->__getHandleFromFilename(basename($path));

			if(is_callable(array($classname, 'about'))){
				$about = call_user_func(array($classname, 'about'));
				return array_merge($about, array('handle' => $handle));
			}

		}

		public function create($name) {
			$classname = $this->__getClassName($name);
			$path = $this->__getDriverPath($name);

			if (!@is_file($path)) return false;

			if (!class_exists($classname)) require_once($path);

			return new $classname;
		}

		public function listAll($sort_column = 'name', $sort_direction = 'asc') {
			$this->_sort_column = $sort_column;
			$this->_sort_direction = $sort_direction;

			$result = array();

			foreach ($this->paths as $path) {
				$structure = General::listStructure($path, '/xml-importer.[\w-]+.php/', false, 'ASC', $path);

				if (is_array($structure['filelist']) and !empty($structure['filelist'])) {
					foreach ($structure['filelist'] as $file) {
						$file = $this->__getHandleFromFilename($file);

						if ($about = $this->about($file)) {
							$classname = $this->__getClassName($file);
							$path .= "/xml-importer.{$name}.php";
							$about['handle'] = $file;

							$result[] = $about;
						}
					}
				}
			}

			usort($result, array($this, "__sort"));

			return $result;
		}
	}
