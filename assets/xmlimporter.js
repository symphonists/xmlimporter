(function($) {

	$(document).ready(function() {
		var sections = $('select[name="fields[section]"]'),
			datasources = $('#ds-context'),
			preview = $('.xml-importer-preview'),
			fields = $('div.frame.section-fields'),
			label = sections.parents('fieldset').find('p.label'),
			namespaces = $('div.frame.namespaces');

		// Preview Datasources
		datasources.on('change', function() {
			if(!this.value) {
				return;
			}

			preview.val('');

			$.ajax({
				url: Symphony.Context.get('symphony') + '/extension/xmlimporter/datasource/',
				data: {
					'ds': this.value
				},
				success: function(doc) {
					var serializer = new XMLSerializer(),
						xml;

					// Clean-up
					doc.firstChild.removeAttribute('status');
					xml = serializer.serializeToString(doc);

					// Unify indentation
					xml = xml.replace(new RegExp("\n\t  ", "g"), '\n      ');
					xml = xml.replace(new RegExp("\n\t\t", "g"), '\n    ');
					xml = xml.replace(new RegExp("\n\t", "g"), '\n  ');
					xml = xml.replace(new RegExp("  ", "g"), '\t');

					preview.val(xml);
				},
				dataType: 'xml'
			});
		}).change();

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
