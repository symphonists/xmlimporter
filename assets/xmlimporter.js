(function($) {

	$(document).ready(function() {
		var sections = $('select[name="fields[section]"]'),
			datasources = $('#ds-context'),
			preview = $('#xml-importer-preview'),
			code = preview.find('code'),
			fields = $('div.frame.section-fields'),
			label = sections.parents('fieldset').find('p.label'),
			namespaces = $('div.frame.namespaces'),
			sources = $('.xml-importer-reports .frame'),
			actionsTop = $('#context .actions a'),
			actionsBottom = $('.apply button');

		// Report sources
		sources.symphonyDuplicator({
			collapsible: true,
			constructable: false,
			destructable: false
		}).trigger('collapseall.collapsible');

		sources.find('.instance').on('expandbefore.collapsible', function() {
			Prism.highlightElement(this.querySelector('code'));
		});

		// Preview Datasources
		datasources.on('change', function() {
			if(!this.value) {
				return;
			}

			code.text('');

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
					xml = xml.replace(new RegExp('\n\t  ', 'g'), '\n      ');
					xml = xml.replace(new RegExp('\n\t\t', 'g'), '\n    ');
					xml = xml.replace(new RegExp('\n\t', 'g'), '\n  ');
					xml = xml.replace(new RegExp('  ', 'g'), '\t');

					// Replace special characters
					xml.replace(/&/g, '&amp;');
					xml.replace(/</g, '&lt;');
					xml.replace(/>/g, '&gt;');
					xml.replace(/'/g, '&quot;');
					xml.replace(/'/g, '&#039;');

					code.text(xml);
					Prism.highlightElement(code[0], true);
				},
				dataType: 'xml'
			});
		}).change();

		if (preview.length) {
			Prism.highlightElement(code[0], true);
		}

		// Initialise Duplicators
		namespaces.add(fields).symphonyDuplicator({
			orderable: true
		});

		// Select section
		sections.on('change.xmlimporter', function selectSection() {
			var id = $(this).val();
			fields.detach().filter('#section-' + id).insertAfter(label);
		}).trigger('change.xmlimporter');

		// Import
		actionsTop.on('click.xmlimporter', function clickTop(event) {
			var target = $(event.target);

			if (!target.is('.create')) {
				$('#contents').addClass('xml-importer-running');
			}
		});
		actionsBottom.on('click.xmlimporter', function clickBottom(event) {
			var target = $(event.target),
				select = target.prev('select');

			if (select.length && select.val() === 'run') {
				$('#contents').addClass('xml-importer-running');
			}
		});
	});

})(jQuery);
