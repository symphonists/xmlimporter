# XML Importer

XML Importer is a way of creating repeatable templates to import data from XML feeds directly into Symphony sections. It provides a way of mapping content from XML nodes directly onto fields in your sections, and the ability to both create new and update existing entries.

## Installation

1. Upload the `xmlimporter` folder to your Symphony `/extensions` folder
2. Enable it by selecting "XML Importer" on the System > Extensions page, choose Enable from the with-selected menu, then click Apply
3. Use the extension from the Blueprints > XML Importers menu


## Creating an Importer (tutorial)
An Importer is similar to a Dynamic XML Datasource in its configuration. Let's create a fictitious importer to store your Twitter messages into a section named "Tweets" that has three fields:

* Permalink (Text Input)
* Date (Date)
* Tweet (Textarea)


### Essentials

Start by creating a new Importer and give it a sensible **Name** such as `Tweets` and add any notes into the **Description** field: `Import Tweets from user's public RSS timeline`.


### Source

This is where we define the XML feed. Start by providing the feed URL, for example mine is:

	http://www.twitter.com/statuses/user_timeline/12675.rss

Take a look at the source of this feed and you'll see that it uses XML namespaces (the `xmlns` attributes). To be able to traverse this feed properly the XML Importer needs to know about these namespaces, so click **Add item** under the **Namespace Declarations** region to add the Name/URI values for each namespace:

* Name: `rss`, URI: `http://www.w3.org/2005/Atom`
* Name: `georss`, URI: `http://www.georss.org/georss`

**Included Elements** is an XPath expression representing each XML node that you want to convert into a Symphony entry. In our example we want to loop over each `<item>` node in the RSS feed:

	/rss/channel/item

This can also be written as:

	//item


### Destination

Now we configure the values for each field in our new entry. Start by selecting the section into which we want to create new entries (`Tweets`). The dropdown under **Fields** will now be filled with the name of each field in this section. We are going to store values for each of the Permalink, Date and Tweet fields.

Click **Add item** to configure the `Permalink` field. You will see three options appear: **XPath Expression**, **PHP Function** and **Is unique**.

Since our **Included Elements** are going to be `<item>` elements from the RSS feed, here is an example:

	<item>
	</item>

The **XPath Expression** for the `Permalink` field is therefore going to be relative to an `<item>` element. To get the tweet text the XPath would be:

	permalink/text()

Remember we want the text value (`text()`) and not the element itself!

**PHP Function** can be left blank, but is used for more complex processing of the selected value before saving. Be sure to check the **Is unique** radio button for the `Permalink` field so that duplicates cannot be created — this prevents the same tweets being added every time the importer is run.

Repeat the above step for the remaining fields. Your **Fields** options should eventually look like:

* **Permalink** XPath Expression: `permalink/text()`, PHP Function: (blank), Is unique: `Yes`
* **Date**, XPath Expression: `published/text()`, PHP Function: (blank), Is unique: `No`
* **Description**, XPath Expression: `description/text()`, PHP Function: (blank), Is unique: `No`

Finally, untick the **Can update existing entries** checkbox. When used in conjunction with the **Is unique** selection, this option allows the importer to update entries where the "unique" value matches. Twitter doesn't allow you edit Tweets once published, so this option isn't required.

Save your importer.


## Run an Importer

From the XML Importers list, click a row to highlight the importer and select **Run** from the **With Selected...** dropdown.

If you want to use the same Importer in multiple feeds (if you have more than one Twitter feed, for example) you can modify the URL of the Run URL. By default our Twitter importer will be executed this this URL:

	/symphony/extension/xmlimporter/importers/run/twitter/

But the feed URL can be overridden by appending a `source` parameter:

	http://example.com/symphony/extension/xmlimporter/importers/run/twitter/?source=http://twitter.com/statuses/public_timeline.rss


## Using PHP Functions

The **PHP Function** field on each imported field allows for some additional processing of the value returned from the XPath Expression. This might be used for parsing out an image URL, converting to Markdown, formatting a date, and so on.

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

- The `{$root}` parameter can used in your source URL which will be evaluated at run time to the value of the Symphony constant URL, eg. `{$root}/feed/news/` becomes `http://example.com/feed/news/`
- By default Symphony will set the timeout for retrieving the source URL to be 60 seconds. This can be updated by modifying the `timeout` setting in your saved XML Importer file which are located in `/workspace/xml-importers/`.

