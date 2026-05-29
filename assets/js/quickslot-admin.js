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

	function buildReminderRow() {
		return $(
			'<div class="qs-reminder-offset-row">' +
				'<input type="number" min="0.1" step="0.1" name="reminder_hours[]" value="">' +
				'<span>' + quickslotAdmin.reminderHoursSuffix + '</span>' +
				'<button type="button" class="button-link-delete qs-remove-reminder-offset">' + quickslotAdmin.removeReminderLabel + '</button>' +
			'</div>'
		);
	}

	function insertPlaceholder(token) {
		var $target = $('.qs-template-body:focus, input[type="text"]:focus, textarea:focus').first();

		if (! $target.length) {
			$target = $('.qs-template-body').first();
		}

		if (! $target.length) {
			return;
		}

		var el = $target.get(0);
		var value = $target.val();
		var start = el.selectionStart || value.length;
		var end = el.selectionEnd || value.length;
		var updated = value.substring(0, start) + token + value.substring(end);

		$target.val(updated);

		if (typeof el.setSelectionRange === 'function') {
			el.focus();
			el.setSelectionRange(start + token.length, start + token.length);
		}
	}

	$(document).on('click', '.qs-add-break', function () {
		var day = $(this).data('day');
		$(this).siblings('.qs-breaks').append(buildBreakRow(day));
	});

	$(document).on('click', '.qs-remove-break', function () {
		$(this).closest('.qs-break-row').remove();
	});

	$(document).on('change', '.qs-exception-type', toggleExceptionHours);

	$(document).on('click', '[data-qs-add-reminder-offset]', function () {
		$('[data-qs-reminder-offsets]').append(buildReminderRow());
	});

	$(document).on('click', '.qs-remove-reminder-offset', function () {
		$(this).closest('.qs-reminder-offset-row').remove();
	});

	$(document).on('click', '.qs-insert-placeholder', function () {
		insertPlaceholder($(this).data('placeholder'));
	});

	toggleExceptionHours();
});
