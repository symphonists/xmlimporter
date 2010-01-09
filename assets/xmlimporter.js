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
		
		$('ol.section-fields').symphonyDuplicator();
		
		$('ol.section-fields').each(function() {
			var checkboxes = $('input[type=checkbox]', $(this));
			checkboxes.live('click', function() {
				checkboxes.each(function() {
					$(this).removeAttr('checked');
				});
				$(this).attr('checked', 'checked');
			});
		});
	},
	
	showSectionFields: function(id) {		
		$('ol.section-fields').hide();
		$('#section-' + id).show();
	}
}
