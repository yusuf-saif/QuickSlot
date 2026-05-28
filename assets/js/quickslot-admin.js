jQuery(function ($) {
	function buildBreakRow(day) {
		return $(
			'<div class="qs-break-row">' +
				'<input type="time" name="availability[' + day + '][breaks][][start]" value="">' +
				'<input type="time" name="availability[' + day + '][breaks][][end]" value="">' +
				'<button type="button" class="button-link-delete qs-remove-break">' + quickslotAdmin.removeBreakLabel + '</button>' +
			'</div>'
		);
	}

	function toggleExceptionHours() {
		var type = $('.qs-exception-type').val();
		$('.qs-exception-hours-row').toggle(type === 'custom');
	}

	$(document).on('click', '.qs-add-break', function () {
		var day = $(this).data('day');
		$(this).siblings('.qs-breaks').append(buildBreakRow(day));
	});

	$(document).on('click', '.qs-remove-break', function () {
		$(this).closest('.qs-break-row').remove();
	});

	$(document).on('change', '.qs-exception-type', toggleExceptionHours);

	toggleExceptionHours();
});
