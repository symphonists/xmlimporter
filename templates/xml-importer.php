<?php

require_once(EXTENSIONS . '/xmlimporter/lib/class.xmlimporter.php');

class XMLImporter%s extends XMLImporter {
    public function about()
    {
        return array(
            'name' => %s,
            'author' => array(
                'name' => %s,
                'email' => %s
            ),
            'description' => %s,
            'file' => __FILE__,
            'created' => %s,
            'updated' => %s,
            'version' => 'XML Importer 3.0'
        );
    }

    public function options()
    {
        return array(
            'can-update' => %s,
            'fields' => %s,
            'included-elements' => %s,
            'namespaces' => %s,
            'source' => %s,
            'timeout' => %s,
            'section' => %s,
            'unique-field' => %s
        );
    }

    public function pagination()
    {
        return array(
            'variable' => %s,
            'start' => %s,
            'next' => %s,
        );
    }

    public function allowEditorToParse()
    {
        return true;
    }
}
