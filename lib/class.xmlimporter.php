<?php
	
	class XmlImporter extends Manager {
		protected $_sort_column = '';
		protected $_sort_direction = '';
		
		public function __getPath() {
			return EXTENSIONS . '/xmlimporter/importers/';
		}
		
		public function __getHandleFromFilename($name) {
			return preg_replace(array('/^import./i', '/.php$/i'), '', $name);
		}
		
		public function __getClassPath($name) {
			return $this->__getPath();
		}
		
		public function __getDriverPath($name) {	        
			return $this->__getPath() . "import.$name.php";
		}
		
		public function __getClassName($name) {
			return 'import' . str_replace('-', '_', $name);
		}
		
		protected function __sort($a, $b) {
			if ($this->_sort_direction != 'asc') {
				$t = $b; $b = $a; $a = $t;
			}
			
			return strnatcasecmp($a[$this->_sort_column], $b[$this->_sort_column]);
		}
		
		public function listAll($sort_column = 'name', $sort_direction = 'asc') {
			//header('content-type: text/plain');
			
			$this->_sort_column = $sort_column;
			$this->_sort_direction = $sort_direction;
			
			$result = array();
			$path = $this->__getPath();
			
			$structure = General::listStructure($path, '/import.[\w-]+.php/', false, 'ASC', $path);
			
			if (is_array($structure['filelist']) and !empty($structure['filelist'])) {
				foreach ($structure['filelist'] as $file) {
					$file = self::__getHandleFromFilename($file);
					
					//var_dump($this->__getClassName($file));
					
					if ($about = $this->about($file)) {
						$classname = $this->__getClassName($file);
						$path = $this->__getDriverPath($file);
						
						$about['handle'] = $file;
						$about['root'] = @call_user_func(array(&$classname, 'getRootExpression'));
						
				    	if (is_callable(array($classname, 'allowEditorToParse'))) {
							$about['can_parse'] = @call_user_func(array(&$classname, 'allowEditorToParse'));							
						} else {
							$about['can_parse'] = false;
						}
						
						$result[] = $about;
					}
				}
			}
			
			usort($result, array($this, "__sort"));
			
			return $result;
		}
		
        public function create($name) {
			$classname = $this->__getClassName($name);	        
			$path = $this->__getDriverPath($name);
			
			if (!@is_file($path)) {
				return false;
			}
			
			if (!class_exists($classname)) require_once($path);
			
			$this->_pool[] =& new $classname($this->_Parent);	

			return end($this->_pool);
        } 
	}
	
?>