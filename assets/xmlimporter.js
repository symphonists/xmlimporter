jQuery(document).ready(function() {
	$ = jQuery;

	XmlImporter.init();
});

var XmlImporter = {
	init: function() {
		var self = this;
		
		$('ol.namespaces-duplicator').symphonyDuplicator({
			orderable:	true
		});

		$('ol.section-fields').symphonyDuplicator({
			orderable:	true
		});

		this.showSectionFields($('select[name="fields[section]"]').val());

		$('select[name="fields[section]"]').bind('change', function() {
			self.showSectionFields($(this).val());
		});
	},

	showSectionFields: function(id) {
		$('ol.section-fields').closest('.frame').hide();
		$('#section-' + id).closest('.frame').show();
	}
}
