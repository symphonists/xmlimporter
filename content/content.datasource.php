<?php

class contentExtensionXmlimporterDataSource extends XMLPage
{

    public function view()
    {
        $handle = $_GET['ds'];

        try {
            $datasource = DatasourceManager::create($handle);
            $this->_Result = new XMLElement('data');
            $this->_Result->appendChild($datasource->grab());
        }
        catch (Exception $e) {
            $this->_Result = new XMLElement('error', 'Datasource could not be previewed.');
        }
    }
}
