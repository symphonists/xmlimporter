jQuery(document).ready(function() {
	$ = jQuery;

	XmlImporter.init();
});

var XmlImporter = {
	init: function() {
		var self = this;

		this.showSectionFields($('select[name="fields[section]"]').val());

		$('select[name="fields[section]"]').bind('change', function() {
			self.showSectionFields($(this).val());
		});

		$('ol.namespaces-duplicator').symphonyDuplicator({
			orderable:	true
		});

		$('ol.section-fields').symphonyDuplicator({
			orderable:	true
		});
	},

	showSectionFields: function(id) {
		$('ol.section-fields').hide();
		$('#section-' + id).show();
	}
}
