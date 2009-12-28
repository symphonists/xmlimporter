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
		
		$('div.section-fields').each(function() {
			var checkboxes = $('input[type=checkbox]', $(this));
			checkboxes.bind('click', function() {
				checkboxes.each(function() {
					$(this).removeAttr('checked');
				});
				$(this).attr('checked', 'checked');
			});
		});
	},
	
	showSectionFields: function(id) {		
		$('div.section-fields').hide();
		$('#section-' + id).show();
	}
}