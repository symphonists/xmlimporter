(function($) {

	$(document).ready(function() {
		var sections = $('select[name="fields[section]"]'),
			fields = $('div.frame.section-fields'),
			label = sections.parents('fieldset').find('p.label'),
			namespaces = $('div.frame.namespaces');

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
