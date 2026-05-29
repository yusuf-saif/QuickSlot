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

	function normalizeHex(value, fallback) {
		if (!value) {
			return fallback;
		}

		if (value.charAt(0) !== '#') {
			value = '#' + value;
		}

		return value;
	}

	function hexToRgb(hex) {
		hex = normalizeHex(hex, '#6366F1').replace('#', '');

		if (hex.length === 3) {
			hex = hex.charAt(0) + hex.charAt(0) + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2);
		}

		return {
			r: parseInt(hex.substring(0, 2), 16),
			g: parseInt(hex.substring(2, 4), 16),
			b: parseInt(hex.substring(4, 6), 16)
		};
	}

	function rgbToHex(rgb) {
		function toHex(value) {
			var bounded = Math.max(0, Math.min(255, Math.round(value)));
			var hex = bounded.toString(16).toUpperCase();
			return hex.length === 1 ? '0' + hex : hex;
		}

		return '#' + toHex(rgb.r) + toHex(rgb.g) + toHex(rgb.b);
	}

	function darkenHex(hex, percent) {
		var rgb = hexToRgb(hex);
		var factor = Math.max(0, Math.min(100, percent)) / 100;

		return rgbToHex({
			r: rgb.r * (1 - factor),
			g: rgb.g * (1 - factor),
			b: rgb.b * (1 - factor)
		});
	}

	function lightenHex(hex, percent) {
		var rgb = hexToRgb(hex);
		var factor = Math.max(0, Math.min(100, percent)) / 100;

		return rgbToHex({
			r: rgb.r + ((255 - rgb.r) * factor),
			g: rgb.g + ((255 - rgb.g) * factor),
			b: rgb.b + ((255 - rgb.b) * factor)
		});
	}

	function hexToRgba(hex, alpha) {
		var rgb = hexToRgb(hex);
		return 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + alpha + ')';
	}

	function updateColorSettingsState() {
		var useSiteTheme = $('[data-qs-use-site-theme]').is(':checked');
		$('[data-qs-color-row]').toggle(!useSiteTheme);
		$('.qs-color-picker').wpColorPicker('option', 'disabled', useSiteTheme);
	}

	function updateDesignPreview() {
		var preview = $('[data-qs-design-preview]');

		if (!preview.length) {
			return;
		}

		var useSiteTheme = $('[data-qs-use-site-theme]').is(':checked');
		var primary = normalizeHex($('[data-qs-primary-color]').val(), '#6366F1');
		var accent = normalizeHex($('[data-qs-accent-color]').val(), '#8B5CF6');

		if (useSiteTheme) {
			preview.removeAttr('style');
			return;
		}

		preview.css({
			'--qs-color-primary': primary,
			'--qs-color-primary-hover': darkenHex(primary, 12),
			'--qs-color-primary-light': lightenHex(primary, 85),
			'--qs-color-accent': accent,
			'--qs-shadow-focus': '0 0 0 3px ' + hexToRgba(primary, 0.24)
		});
	}

	function initColorPickers() {
		if (typeof $.fn.wpColorPicker !== 'function') {
			return;
		}

		$('.qs-color-picker').wpColorPicker({
			change: function () {
				window.setTimeout(updateDesignPreview, 0);
			},
			clear: function () {
				window.setTimeout(updateDesignPreview, 0);
			}
		});

		updateColorSettingsState();
		updateDesignPreview();
	}

	function copyToClipboard(value) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			return navigator.clipboard.writeText(value);
		}

		return new Promise(function (resolve, reject) {
			var tempInput = document.createElement('input');
			tempInput.type = 'text';
			tempInput.value = value;
			document.body.appendChild(tempInput);
			tempInput.select();

			try {
				if (document.execCommand('copy')) {
					resolve();
				} else {
					reject();
				}
			} catch (error) {
				reject(error);
			}

			document.body.removeChild(tempInput);
		});
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

	$(document).on('click', '[data-qs-copy-button]', function () {
		var $button = $(this);
		var $target = $($button.attr('data-qs-copy-target'));
		var $feedback = $button.siblings('[data-qs-copy-feedback]');

		if (! $target.length) {
			return;
		}

		copyToClipboard(String($target.val() || ''))
			.then(function () {
				if ($feedback.length) {
					$feedback.text(quickslotAdmin.copiedLabel || 'Copied!');
					window.setTimeout(function () {
						$feedback.text('');
					}, 2000);
				}
			})
			.catch(function () {
				$target.trigger('focus').trigger('select');
			});
	});

	$(document).on('change', '[data-qs-use-site-theme]', function () {
		updateColorSettingsState();
		updateDesignPreview();
	});

	$(document).on('input change', '[data-qs-primary-color], [data-qs-accent-color]', updateDesignPreview);

	toggleExceptionHours();
	initColorPickers();
});
