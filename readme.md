# XML Importer

XML Importer is a way of creating repeatable templates to import data from XML feeds directly into Symphony sections. It provides a way of mapping content from XML nodes directly onto fields in your sections, and the ability to both create new and update existing entries.

### Note

XML Importer 3.0 is a drastic change from previous XML Importer versions in that the retrieval of data is abstracted to using Data Sources. Old XML Importer files can still be used in this version, but they can not be edited. It is highly recommended to migrate to using a Data Source as this legacy behaviour will be removed in Version 4.

## Sources

As of version 3, XML Importer uses Data Sources to import data:

- If you'd like to import external data, please have a look at [Remote Data Source](https://github.com/symphonycms/remote_datasource) which accepts XML, JSON or CSV sources.
- If you'd like to alter existing data, you can make use of standard Data Sources

If you want to use the same importer for different input sources, you can modify the URL of the run:

	http://example.com/symphony/extension/xmlimporter/importers/run/twitter/?source=http://twitter.com/statuses/public_timeline.rss

## Destinations

Imported data will be stored in the section of your choice (either creating new entries or updating existing ones based on your importer's settings).

When setting up **XPath expressions**, please keep in mind that you explicitely have to reference the text node to get the value of element, e. g. `example/text()`.

## Using PHP Functions

The PHP Function field on each imported field allows for some additional processing of the value returned from the XPath Expression. This might be used for parsing out an image URL, converting to Markdown, formatting a date, and so on.

For example, if you wanted to store a hash of the Permalink instead of the URL, you can use the following:

	md5($value);

`$value` refers to value returned from the XPath Expression.

Functions more complex than one line are also possible, by adding them to the included XMLImporterHelpers class. One is included already, to convert HTML to Markdown. To use this function, use this:

	XMLImporterHelpers::markdownify

Notice that you do not need to pass `$value` as this will be done for you, as the method will be provided with a single argument, and should return a string. Since XMLImporter 2.1, you can add your own custom functions to `/workspace/xml-importers/class.xmlimporterhelpers.php`. Prior to this, custom functions were added to `/extensions/xmlimporter/lib/class.xmlimporterhelpers.php`, but this was changed for better flexibility and to match Symphony conventions better.

## Your fields and the XML Importer

The XMLImporter allows fields to implement a `prepareImportValue()` function which will preprocess the value from XML before being passed to `processRawFieldData()`. The XML Importer will check for the Field class for the `prepareImportValue()` otherwise it will fall back to rudimentary processing. The `prepareImportValue` function will be passed the value of the XPath and the `entry_id`, and should return a value that your Field's `processRawFieldData` function can accept.

Since XMLImporter 2.1, the `prepareImportValue` function will choose the first mode returned in the `getImportModes` array. It is anticipated that this mode will be the correct mode that will transform the XML value into a format that the field normally expects from either the Symphony backend, or from an event on the frontend. That is, if your field expects a single string value, then the first mode listed in `getImportModes` should be `ImportableField::STRING_VALUE`. If your field expects an array of data to come from the backend/frontend, then the first mode should be `ImportableField::ARRAY_VALUE`

## Advanced Tips

By default Symphony will set the timeout for retrieving the source URL to be 60 seconds. This can be updated by modifying the `timeout` setting in your saved XML Importer file which are located in `/workspace/xml-importers/`.
