(function($) {

	$(document).ready(function() {
		var sections = $('select[name="fields[section]"]'),
			fields = $('div.section-fields'),
			label = sections.parents('fieldset').find('p.label'),
			namespaces = $('ol.namespaces-duplicator');

		// Initialise Duplicators
		namespaces.add(fields).symphonyDuplicator({
			orderable: true
		});

		// Select section
		sections.on('change.xmlimporter', function selectSection() {
			var id = $(this).val();
			fields.detach().filter('#section-' + id).insertAfter(label);
		}).trigger('change.xmlimporter');
	});

})(jQuery);
