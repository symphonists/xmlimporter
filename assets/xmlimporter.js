(function($) {

	$(document).ready(function XMLImporter() {
		var namespaces = $('div.namespaces-duplicator'),
			sections = $('select[name="fields[section]"]'),
			fields = $('div.section-fields'),
			fields_label = fields.prev('p.label');
	
		// Namespaces
		namespaces.symphonyDuplicator({
			orderable:	true
		});

		// Fields
		fields.symphonyDuplicator({
			orderable:	true
		});
		
		// Switch sections
		sections.on('change.xmlimporter', function() {
			fields.detach().filter('#section-' + $(this).val()).insertAfter(fields_label);
		}).trigger('change.xmlimporter');		
	});

})(jQuery.noConflict());
