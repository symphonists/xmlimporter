(function($) {

	$(document).ready(function() {
		var sections = $('select[name="fields[section]"]'),
			datasources = $('#ds-context'),
			preview = $('.xml-importer-preview'),
			fields = $('div.frame.section-fields'),
			label = sections.parents('fieldset').find('p.label'),
			namespaces = $('div.frame.namespaces');

		// Preview Datasources
		datasources.on('change load', function() {
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
		});

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

	$(document).on('click','.throttle',function(){
		var data = {
			"created":0,
			"updated":0,
			"skipped":0,
			"failed":0,
		};
		data.next = $('.next-page').data('next');
		data.nexturi = $('.next-page').data('uri');
		throttle(data,0);
	});

	function throttle(data,maxpage){
		$importdetails = $('.xmlimport-details');
		$importdetails.data('created', $importdetails.data('created') + data.created);
		$importdetails.data('updated', $importdetails.data('updated') + data.updated);
		$importdetails.data('skipped', $importdetails.data('skipped') + data.skipped);
		$importdetails.data('failed', $importdetails.data('failed') + data.failed);
		$importdetails.text( $importdetails.data('created') + ' new entries were created, ' + 
								$importdetails.data('updated') +' updated, ' + 
								$importdetails.data('skipped') +' skipped and ' + 
								$importdetails.data('failed') +' failed'
							);

		if (data.next != null && (data.next <= maxpage || maxpage == 0)){
			$importdetails.prev().text('Currently Importing Page ' + data.next);
			$.getJSON( data.nexturi + '&ajax=1' , function( data ) {
				throttle(data,maxpage);
			});
		} else {
			$importdetails.prev().text('Import Complete');			
		}
	}

})(jQuery);
