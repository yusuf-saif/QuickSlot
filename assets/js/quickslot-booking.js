document.addEventListener('DOMContentLoaded', function () {
	var form = document.getElementById('qs-booking-form');
	var localized = window.QuickSlotData || {};
	var i18n = localized.i18n || {};

	if (!form) {
		return;
	}

	var panels = Array.prototype.slice.call(form.querySelectorAll('[data-step-panel]'));
	var progressSteps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
	var serviceContainer = form.querySelector('[data-qs-services]');
	var calendarContainer = form.querySelector('[data-qs-calendar]');
	var calendarTitle = form.querySelector('[data-qs-calendar-title]');
	var calendarGrid = form.querySelector('[data-qs-calendar-grid]');
	var slotsContainer = form.querySelector('[data-qs-slots]');
	var reviewSummary = form.querySelector('[data-qs-review-summary]');
	var successTitle = form.querySelector('[data-qs-success-title]');
	var successMessage = form.querySelector('[data-qs-success-message]');
	var detailsNextButton = form.querySelector('[data-step-panel="details"] [data-qs-nav="next"]');
	var confirmButton = form.querySelector('[data-qs-confirm]');
	var fieldInputs = Array.prototype.slice.call(form.querySelectorAll('[data-qs-field]'));
	var feedback = {
		service: form.querySelector('[data-qs-feedback="service"]'),
		date: form.querySelector('[data-qs-feedback="date"]'),
		time: form.querySelector('[data-qs-feedback="time"]'),
		details: form.querySelector('[data-qs-feedback="details"]'),
		review: form.querySelector('[data-qs-feedback="review"]')
	};
	var fieldErrors = {};
	var stepIndexes = {
		service: getStepIndex('service'),
		date: getStepIndex('date'),
		time: getStepIndex('time'),
		details: getStepIndex('details'),
		review: getStepIndex('review'),
		success: getStepIndex('success')
	};
	var state = {
		activeIndex: 0,
		services: [],
		serviceId: null,
		selectedService: null,
		selectedDate: null,
		selectedSlot: null,
		currentMonth: getMonthKey(new Date()),
		availableDates: [],
		slots: [],
		bookingId: null,
		isSubmitting: false,
		requests: {
			dates: 0,
			slots: 0
		},
		details: {
			customer_name: '',
			customer_email: '',
			customer_phone: '',
			customer_note: '',
			website: ''
		}
	};

	fieldInputs.forEach(function (input) {
		var fieldName = input.getAttribute('data-qs-field');
		fieldErrors[fieldName] = form.querySelector('[data-qs-field-error="' + fieldName + '"]');
	});

	if (detailsNextButton) {
		detailsNextButton.textContent = text('reviewBooking', 'Review Booking');
	}

	if (confirmButton) {
		confirmButton.textContent = text('confirmBooking', 'Confirm Booking');
	}

	function getStepIndex(slug) {
		return panels.findIndex(function (panel) {
			return panel.getAttribute('data-step-panel') === slug;
		});
	}

	function text(key, fallback) {
		return i18n[key] || fallback;
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function setFeedback(step, message, type) {
		if (!feedback[step]) {
			return;
		}

		feedback[step].className = 'qs-step-feedback';

		if (!message) {
			feedback[step].textContent = '';
			feedback[step].hidden = true;
			return;
		}

		feedback[step].textContent = message;
		feedback[step].hidden = false;

		if (type) {
			feedback[step].classList.add('is-' + type);
		}
	}

	function focusStepTarget(target) {
		window.requestAnimationFrame(function () {
			if (target && typeof target.focus === 'function') {
				target.focus();
				return;
			}

			var panel = panels[state.activeIndex];
			var selected = panel.querySelector('.is-selected, [aria-pressed="true"]');
			var firstControl = panel.querySelector('button:not([disabled]), input:not([disabled]), textarea:not([disabled])');
			var heading = panel.querySelector('.qs-booking-step__title');

			if (selected && typeof selected.focus === 'function') {
				selected.focus();
			} else if (firstControl && typeof firstControl.focus === 'function') {
				firstControl.focus();
			} else if (heading) {
				heading.setAttribute('tabindex', '-1');
				heading.focus();
			}
		});
	}

	function setActiveStep(index, focusTarget) {
		if (index < 0 || index >= panels.length) {
			return;
		}

		state.activeIndex = index;

		panels.forEach(function (panel, panelIndex) {
			var isActive = panelIndex === state.activeIndex;
			panel.classList.toggle('is-active', isActive);
			panel.hidden = !isActive;
		});

		progressSteps.forEach(function (step, stepIndex) {
			step.classList.toggle('is-active', stepIndex === state.activeIndex);
		});

		form.setAttribute('data-active-step', panels[state.activeIndex].getAttribute('data-step-panel') || 'service');
		updateStepActions();
		focusStepTarget(focusTarget);
	}

	function updateStepActions() {
		panels.forEach(function (panel) {
			var slug = panel.getAttribute('data-step-panel');
			var nextButton = panel.querySelector('[data-qs-nav="next"]');

			if (!nextButton) {
				return;
			}

			if ('service' === slug || 'date' === slug || 'time' === slug) {
				nextButton.hidden = true;
				nextButton.disabled = true;
				return;
			}

			nextButton.hidden = false;
			nextButton.disabled = false;
		});

		if (confirmButton) {
			confirmButton.disabled = state.isSubmitting;
			confirmButton.textContent = state.isSubmitting ? text('submitting', 'Submitting...') : text('confirmBooking', 'Confirm Booking');
		}
	}

	function formatDuration(minutes) {
		return String(minutes) + ' ' + text('minutes', 'minutes');
	}

	function formatPrice(service) {
		if (!service || !service.is_paid || service.price === null) {
			return text('free', 'Free');
		}

		return text('price', 'Price') + ': ' + String(service.price);
	}

	function getMonthStart(monthKey) {
		return new Date(monthKey + '-01T00:00:00');
	}

	function getMonthKey(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		return year + '-' + month;
	}

	function getDateKey(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		var day = String(date.getDate()).padStart(2, '0');
		return year + '-' + month + '-' + day;
	}

	function getTodayKey() {
		return getDateKey(new Date());
	}

	function getMonthLabel(monthKey) {
		var parts = monthKey.split('-');
		var year = Number(parts[0]);
		var monthIndex = Number(parts[1]) - 1;
		var monthNames = Array.isArray(i18n.monthNames) ? i18n.monthNames : [];
		return (monthNames[monthIndex] || monthKey) + ' ' + year;
	}

	function getAdjacentMonth(monthKey, offset) {
		var monthDate = getMonthStart(monthKey);
		monthDate.setMonth(monthDate.getMonth() + offset);
		return getMonthKey(monthDate);
	}

	function getClientTimezone() {
		if (window.Intl && Intl.DateTimeFormat) {
			var resolved = Intl.DateTimeFormat().resolvedOptions();

			if (resolved && resolved.timeZone) {
				return resolved.timeZone;
			}
		}

		return localized.timezone || '';
	}

	function syncDetailsState() {
		fieldInputs.forEach(function (input) {
			var fieldName = input.getAttribute('data-qs-field');
			state.details[fieldName] = input.value;
		});
	}

	function clearFieldErrors() {
		Object.keys(fieldErrors).forEach(function (fieldName) {
			if (fieldErrors[fieldName]) {
				fieldErrors[fieldName].textContent = '';
				fieldErrors[fieldName].hidden = true;
			}

			var input = form.querySelector('[data-qs-field="' + fieldName + '"]');
			if (input) {
				input.removeAttribute('aria-invalid');
			}
		});
	}

	function applyFieldErrors(errors) {
		clearFieldErrors();

		Object.keys(errors).forEach(function (fieldName) {
			if (fieldErrors[fieldName]) {
				fieldErrors[fieldName].textContent = errors[fieldName];
				fieldErrors[fieldName].hidden = false;
			}

			var input = form.querySelector('[data-qs-field="' + fieldName + '"]');
			if (input) {
				input.setAttribute('aria-invalid', 'true');
			}
		});
	}

	function validateDetails() {
		var errors = {};
		var email = (state.details.customer_email || '').trim();
		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		if (!(state.details.customer_name || '').trim()) {
			errors.customer_name = text('fieldNameRequired', 'Please enter your name.');
		}

		if (!email) {
			errors.customer_email = text('fieldEmailRequired', 'Please enter your email address.');
		} else if (!emailPattern.test(email)) {
			errors.customer_email = text('fieldEmailInvalid', 'Please enter a valid email address.');
		}

		return errors;
	}

	function renderServices(services) {
		if (!serviceContainer) {
			return;
		}

		if (!services.length) {
			serviceContainer.innerHTML = '';
			setFeedback('service', text('noServices', 'No services available'), 'empty');
			return;
		}

		serviceContainer.innerHTML = services.map(function (service) {
			var isSelected = service.id === state.serviceId;
			var description = service.description ? '<p class="qs-service-card__text">' + escapeHtml(service.description) + '</p>' : '';

			return '<button type="button" class="qs-service-card' + (isSelected ? ' is-selected' : '') + '" data-qs-service-id="' + service.id + '" aria-pressed="' + (isSelected ? 'true' : 'false') + '">' +
				'<span class="qs-service-card__title">' + escapeHtml(service.name) + '</span>' +
				'<span class="qs-service-card__meta">' + escapeHtml(formatDuration(service.duration)) + '</span>' +
				'<span class="qs-service-card__price">' + escapeHtml(formatPrice(service)) + '</span>' +
				description +
			'</button>';
		}).join('');

		setFeedback('service', '', '');
	}

	function renderServiceLoading() {
		if (!serviceContainer) {
			return;
		}

		serviceContainer.innerHTML = '';
		setFeedback('service', text('loading', 'Loading...'), 'loading');
	}

	function updateCalendarNavigation() {
		if (!calendarContainer) {
			return;
		}

		var previousButton = calendarContainer.querySelector('[data-qs-calendar-nav="prev"]');
		var nextButton = calendarContainer.querySelector('[data-qs-calendar-nav="next"]');
		var currentMonth = getMonthKey(new Date());

		if (previousButton) {
			previousButton.disabled = getAdjacentMonth(state.currentMonth, -1) < currentMonth;
			previousButton.setAttribute('aria-label', text('previousMonth', 'Previous month'));
		}

		if (nextButton) {
			nextButton.setAttribute('aria-label', text('nextMonth', 'Next month'));
		}
	}

	function renderCalendarLoading() {
		if (!calendarGrid || !calendarTitle) {
			return;
		}

		calendarTitle.textContent = getMonthLabel(state.currentMonth);
		calendarGrid.innerHTML = '<div class="qs-loading-state">' + escapeHtml(text('loading', 'Loading...')) + '</div>';
		setFeedback('date', text('loading', 'Loading...'), 'loading');
		updateCalendarNavigation();
	}

	function renderCalendar() {
		if (!calendarGrid || !calendarTitle) {
			return;
		}

		var monthDate = getMonthStart(state.currentMonth);
		var daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
		var startDay = monthDate.getDay();
		var availableDateMap = {};
		var weekdayNames = Array.isArray(i18n.weekdayNames) ? i18n.weekdayNames : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		var parts = [];

		state.availableDates.forEach(function (dateKey) {
			availableDateMap[dateKey] = true;
		});

		calendarTitle.textContent = getMonthLabel(state.currentMonth);

		weekdayNames.forEach(function (dayName) {
			parts.push('<div class="qs-calendar__weekday">' + escapeHtml(dayName) + '</div>');
		});

		for (var blank = 0; blank < startDay; blank++) {
			parts.push('<span class="qs-calendar__cell qs-calendar__cell--empty" aria-hidden="true"></span>');
		}

		for (var day = 1; day <= daysInMonth; day++) {
			var date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
			var dateKey = getDateKey(date);
			var isSelected = state.selectedDate === dateKey;
			var isPast = dateKey < getTodayKey();
			var isAvailable = !!availableDateMap[dateKey] && !isPast;
			var className = 'qs-calendar__day';

			if (isSelected) {
				className += ' is-selected';
			}

			if (!isAvailable) {
				className += ' is-disabled';
			}

			parts.push('<button type="button" class="' + className + '" data-qs-date="' + dateKey + '"' + (isAvailable ? '' : ' disabled') + ' aria-pressed="' + (isSelected ? 'true' : 'false') + '">' + escapeHtml(String(day)) + '</button>');
		}

		calendarGrid.innerHTML = parts.join('');
		updateCalendarNavigation();

		if (!state.availableDates.length) {
			setFeedback('date', text('noDates', 'No dates available'), 'empty');
		} else {
			setFeedback('date', '', '');
		}
	}

	function renderDatePrompt() {
		if (!calendarGrid || !calendarTitle) {
			return;
		}

		calendarTitle.textContent = getMonthLabel(state.currentMonth);
		calendarGrid.innerHTML = '<div class="qs-empty-state">' + escapeHtml(text('selectService', 'Select a service to continue.')) + '</div>';
		setFeedback('date', '', '');
		updateCalendarNavigation();
	}

	function renderSlotsLoading() {
		if (!slotsContainer) {
			return;
		}

		slotsContainer.innerHTML = '<div class="qs-loading-state">' + escapeHtml(text('loading', 'Loading...')) + '</div>';
		setFeedback('time', text('loading', 'Loading...'), 'loading');
	}

	function renderSlots() {
		if (!slotsContainer) {
			return;
		}

		if (!state.slots.length) {
			slotsContainer.innerHTML = '';
			setFeedback('time', text('noSlots', 'No slots available'), 'empty');
			return;
		}

		slotsContainer.innerHTML = state.slots.map(function (slot) {
			var isSelected = state.selectedSlot === slot;
			return '<button type="button" class="qs-slot-button' + (isSelected ? ' is-selected' : '') + '" data-qs-slot="' + escapeHtml(slot) + '" aria-pressed="' + (isSelected ? 'true' : 'false') + '">' + escapeHtml(slot) + '</button>';
		}).join('');

		setFeedback('time', '', '');
	}

	function renderTimePrompt() {
		if (!slotsContainer) {
			return;
		}

		slotsContainer.innerHTML = '<div class="qs-empty-state">' + escapeHtml(text('selectDate', 'Select an available date to continue.')) + '</div>';
		setFeedback('time', '', '');
	}

	function renderReview() {
		if (!reviewSummary) {
			return;
		}

		syncDetailsState();

		reviewSummary.innerHTML = '<dl class="qs-review-list">' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('serviceLabel', 'Service')) + '</dt><dd>' + escapeHtml(state.selectedService ? state.selectedService.name : '') + '</dd></div>' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('dateLabel', 'Date')) + '</dt><dd>' + escapeHtml(state.selectedDate || '') + '</dd></div>' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('timeLabel', 'Time')) + '</dt><dd>' + escapeHtml(state.selectedSlot || '') + '</dd></div>' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('name', 'Name')) + '</dt><dd>' + escapeHtml(state.details.customer_name || '') + '</dd></div>' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('email', 'Email')) + '</dt><dd>' + escapeHtml(state.details.customer_email || '') + '</dd></div>' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('phone', 'Phone')) + '</dt><dd>' + escapeHtml(state.details.customer_phone || '-') + '</dd></div>' +
			'<div class="qs-review-list__row"><dt>' + escapeHtml(text('note', 'Note')) + '</dt><dd>' + escapeHtml(state.details.customer_note || '-') + '</dd></div>' +
		'</dl>';
	}

	function renderSuccess() {
		if (successTitle) {
			successTitle.textContent = text('bookingConfirmed', 'Booking Confirmed');
		}

		if (successMessage) {
			if (state.bookingId) {
				successMessage.textContent = text('bookingSuccess', 'Your booking has been received. Booking ID: %s').replace('%s', String(state.bookingId));
			} else {
				successMessage.textContent = text('bookingConfirmed', 'Booking Confirmed');
			}
		}
	}

	function toQuery(params) {
		return Object.keys(params).map(function (key) {
			return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
		}).join('&');
	}

	function fetchJson(path, params) {
		var url = localized.restUrl + path;

		if (params && Object.keys(params).length) {
			url += '?' + toQuery(params);
		}

		return window.fetch(url, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': localized.nonce || ''
			}
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('request_failed');
			}

			return response.json();
		}).then(function (payload) {
			if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
				throw new Error('invalid_payload');
			}

			return payload.data;
		});
	}

	function postJson(path, payload) {
		return window.fetch(localized.restUrl + path, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': localized.nonce || ''
			},
			body: JSON.stringify(payload)
		}).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (body) {
				return {
					ok: response.ok,
					status: response.status,
					body: body
				};
			});
		});
	}

	function resetDateSelection() {
		state.selectedDate = null;
		state.selectedSlot = null;
		state.availableDates = [];
		state.slots = [];
	}

	function resetSlotSelection() {
		state.selectedSlot = null;
		state.slots = [];
	}

	function loadServices() {
		renderServiceLoading();
		renderDatePrompt();
		renderTimePrompt();

		fetchJson('services').then(function (services) {
			state.services = services;
			renderServices(services);
		}).catch(function () {
			state.services = [];
			if (serviceContainer) {
				serviceContainer.innerHTML = '';
			}
			setFeedback('service', text('error', 'Something went wrong. Please try again.'), 'error');
		});
	}

	function loadDates(monthKey, shouldFocus) {
		var requestId;

		if (!state.serviceId) {
			renderDatePrompt();
			return;
		}

		state.currentMonth = monthKey;
		requestId = state.requests.dates + 1;
		state.requests.dates = requestId;
		renderCalendarLoading();

		fetchJson('availability/dates', {
			service_id: state.serviceId,
			month: monthKey
		}).then(function (dates) {
			if (requestId !== state.requests.dates) {
				return;
			}

			state.availableDates = dates;
			renderCalendar();

			if (shouldFocus) {
				focusStepTarget(calendarGrid.querySelector('button:not([disabled])'));
			}
		}).catch(function () {
			if (requestId !== state.requests.dates) {
				return;
			}

			state.availableDates = [];
			renderCalendar();
			setFeedback('date', text('error', 'Something went wrong. Please try again.'), 'error');
		});
	}

	function loadSlots(dateKey, shouldFocus) {
		var requestId;

		if (!state.serviceId || !dateKey) {
			renderTimePrompt();
			return;
		}

		requestId = state.requests.slots + 1;
		state.requests.slots = requestId;
		renderSlotsLoading();

		fetchJson('availability/slots', {
			service_id: state.serviceId,
			date: dateKey
		}).then(function (slots) {
			if (requestId !== state.requests.slots) {
				return;
			}

			state.slots = slots;
			renderSlots();

			if (shouldFocus) {
				focusStepTarget(slotsContainer.querySelector('button:not([disabled])'));
			}
		}).catch(function () {
			if (requestId !== state.requests.slots) {
				return;
			}

			state.slots = [];
			renderSlots();
			setFeedback('time', text('error', 'Something went wrong. Please try again.'), 'error');
		});
	}

	function handleServiceSelection(serviceId) {
		var nextService = state.services.find(function (service) {
			return service.id === serviceId;
		}) || null;
		var serviceChanged = state.serviceId !== serviceId;

		if (!nextService) {
			return;
		}

		state.serviceId = serviceId;
		state.selectedService = nextService;

		if (serviceChanged) {
			resetDateSelection();
			state.currentMonth = getMonthKey(new Date());
			setFeedback('time', '', '');
			setFeedback('review', '', '');
		}

		renderServices(state.services);
		setActiveStep(stepIndexes.date);
		loadDates(state.currentMonth, true);
	}

	function handleDateSelection(dateKey) {
		state.selectedDate = dateKey;
		resetSlotSelection();
		setFeedback('time', '', '');
		setFeedback('review', '', '');
		renderCalendar();
		setActiveStep(stepIndexes.time);
		loadSlots(dateKey, true);
	}

	function handleSlotSelection(slot) {
		state.selectedSlot = slot;
		setFeedback('review', '', '');
		renderSlots();
		setActiveStep(stepIndexes.details);
	}

	function handleDetailsNext() {
		syncDetailsState();
		clearFieldErrors();
		setFeedback('details', '', '');

		var errors = validateDetails();

		if (Object.keys(errors).length) {
			applyFieldErrors(errors);
			setFeedback('details', text('validationMessage', 'Please review the highlighted fields and try again.'), 'error');
			focusStepTarget(form.querySelector('[aria-invalid="true"]'));
			return;
		}

		renderReview();
		setActiveStep(stepIndexes.review);
	}

	function submitBooking() {
		syncDetailsState();
		clearFieldErrors();
		setFeedback('details', '', '');
		setFeedback('review', '', '');

		var errors = validateDetails();

		if (Object.keys(errors).length) {
			applyFieldErrors(errors);
			setFeedback('details', text('validationMessage', 'Please review the highlighted fields and try again.'), 'error');
			setActiveStep(stepIndexes.details, form.querySelector('[aria-invalid="true"]'));
			return;
		}

		state.isSubmitting = true;
		updateStepActions();
		setFeedback('review', text('submitting', 'Submitting...'), 'loading');

		postJson('bookings', {
			service_id: state.serviceId,
			booking_date: state.selectedDate,
			booking_time: state.selectedSlot,
			customer_name: state.details.customer_name,
			customer_email: state.details.customer_email,
			customer_phone: state.details.customer_phone,
			customer_note: state.details.customer_note,
			timezone: getClientTimezone(),
			website: state.details.website
		}).then(function (result) {
			state.isSubmitting = false;
			updateStepActions();

			if (result.ok && result.body && result.body.success === true) {
				state.bookingId = result.body.data && result.body.data.booking_id ? result.body.data.booking_id : null;
				renderSuccess();
				setActiveStep(stepIndexes.success);
				return;
			}

			if (result.status === 409) {
				state.selectedSlot = null;
				renderSlots();
				setFeedback('time', text('slotUnavailable', 'This time slot is no longer available.'), 'error');
				setActiveStep(stepIndexes.time);
				loadSlots(state.selectedDate, true);
				return;
			}

			if (result.status === 422) {
				var responseErrors = result.body && result.body.errors ? result.body.errors : {};
				applyFieldErrors(responseErrors);
				setFeedback('details', result.body && result.body.message ? result.body.message : text('validationMessage', 'Please review the highlighted fields and try again.'), 'error');
				setActiveStep(stepIndexes.details, form.querySelector('[aria-invalid="true"]'));
				return;
			}

			setFeedback('review', text('error', 'Something went wrong. Please try again.'), 'error');
		}).catch(function () {
			state.isSubmitting = false;
			updateStepActions();
			setFeedback('review', text('error', 'Something went wrong. Please try again.'), 'error');
		});
	}

	fieldInputs.forEach(function (input) {
		input.addEventListener('input', function () {
			var fieldName = input.getAttribute('data-qs-field');
			state.details[fieldName] = input.value;

			if (fieldErrors[fieldName]) {
				fieldErrors[fieldName].textContent = '';
				fieldErrors[fieldName].hidden = true;
			}

			input.removeAttribute('aria-invalid');
		});
	});

	form.addEventListener('click', function (event) {
		var nextButton = event.target.closest('[data-qs-nav="next"]');
		var backButton = event.target.closest('[data-qs-nav="back"]');
		var serviceButton = event.target.closest('[data-qs-service-id]');
		var dateButton = event.target.closest('[data-qs-date]');
		var slotButton = event.target.closest('[data-qs-slot]');
		var calendarNav = event.target.closest('[data-qs-calendar-nav]');
		var clickedConfirmButton = event.target.closest('[data-qs-confirm]');

		if (serviceButton) {
			event.preventDefault();
			handleServiceSelection(Number(serviceButton.getAttribute('data-qs-service-id')));
			return;
		}

		if (dateButton) {
			event.preventDefault();
			handleDateSelection(dateButton.getAttribute('data-qs-date'));
			return;
		}

		if (slotButton) {
			event.preventDefault();
			handleSlotSelection(slotButton.getAttribute('data-qs-slot'));
			return;
		}

		if (calendarNav) {
			event.preventDefault();

			if (calendarNav.disabled) {
				return;
			}

			loadDates(getAdjacentMonth(state.currentMonth, calendarNav.getAttribute('data-qs-calendar-nav') === 'next' ? 1 : -1), true);
			return;
		}

		if (clickedConfirmButton) {
			event.preventDefault();

			if (!state.isSubmitting) {
				submitBooking();
			}

			return;
		}

		if (nextButton) {
			event.preventDefault();

			if (state.activeIndex === stepIndexes.details) {
				handleDetailsNext();
				return;
			}

			setActiveStep(state.activeIndex + 1);
			return;
		}

		if (backButton) {
			event.preventDefault();
			setActiveStep(state.activeIndex - 1);
		}
	});

	setActiveStep(0);
	loadServices();
});
