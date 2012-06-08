<?php

	require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');

	class XMLImporter%s extends XMLImporter {
		public function about() {
			return array(
				'name'			=> %s,
				'author'		=> array(
					'name'			=> %s,
					'email'			=> %s
				),
				'description'	=> %s,
				'file'			=> __FILE__,
				'created'		=> %s,
				'updated'		=> %s
			);
		}

		public function options() {
			return array(
				'can-update'		=> %s,
				'fields'			=> %s,
				'included-elements'	=> %s,
				'namespaces'		=> %s,
				'source'			=> %s,
				'timeout'			=> %s,
				'section'			=> %s,
				'unique-field'		=> %s
			);
		}

		public function allowEditorToParse() {
			return true;
		}
	}

