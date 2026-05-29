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
	var feedback = {
		service: form.querySelector('[data-qs-feedback="service"]'),
		date: form.querySelector('[data-qs-feedback="date"]'),
		time: form.querySelector('[data-qs-feedback="time"]')
	};
	var stepIndexes = {
		service: getStepIndex('service'),
		date: getStepIndex('date'),
		time: getStepIndex('time'),
		details: getStepIndex('details')
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
		requests: {
			dates: 0,
			slots: 0
		}
	};

	function getStepIndex(slug) {
		return panels.findIndex(function (panel) {
			return panel.getAttribute('data-step-panel') === slug;
		});
	}

	function text(key, fallback) {
		return i18n[key] || fallback;
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

	function focusStepTarget(target) {
		window.requestAnimationFrame(function () {
			if (target && typeof target.focus === 'function') {
				target.focus();
				return;
			}

			var panel = panels[state.activeIndex];
			var selected = panel.querySelector('.is-selected, [aria-pressed="true"]');
			var firstControl = panel.querySelector('button:not([disabled]), input:not([disabled])');
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

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
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

			return '' +
				'<button type="button" class="qs-service-card' + (isSelected ? ' is-selected' : '') + '" data-qs-service-id="' + service.id + '" aria-pressed="' + (isSelected ? 'true' : 'false') + '">' +
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

	function renderCalendarLoading() {
		if (!calendarGrid || !calendarTitle) {
			return;
		}

		calendarTitle.textContent = getMonthLabel(state.currentMonth);
		calendarGrid.innerHTML = '<div class="qs-loading-state">' + escapeHtml(text('loading', 'Loading...')) + '</div>';
		setFeedback('date', text('loading', 'Loading...'), 'loading');
		updateCalendarNavigation();
	}

	function renderSlotsLoading() {
		if (!slotsContainer) {
			return;
		}

		slotsContainer.innerHTML = '<div class="qs-loading-state">' + escapeHtml(text('loading', 'Loading...')) + '</div>';
		setFeedback('time', text('loading', 'Loading...'), 'loading');
	}

	function getMonthStart(monthKey) {
		return new Date(monthKey + '-01T00:00:00');
	}

	function getMonthKey(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		return year + '-' + month;
	}

	function getTodayKey() {
		return getDateKey(new Date());
	}

	function getDateKey(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		var day = String(date.getDate()).padStart(2, '0');
		return year + '-' + month + '-' + day;
	}

	function getMonthLabel(monthKey) {
		var parts = monthKey.split('-');
		var year = Number(parts[0]);
		var monthIndex = Number(parts[1]) - 1;
		var monthNames = Array.isArray(i18n.monthNames) ? i18n.monthNames : [];
		var monthLabel = monthNames[monthIndex] || monthKey;
		return monthLabel + ' ' + year;
	}

	function getAdjacentMonth(monthKey, offset) {
		var monthDate = getMonthStart(monthKey);
		monthDate.setMonth(monthDate.getMonth() + offset);
		return getMonthKey(monthDate);
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

	function renderCalendar() {
		if (!calendarGrid || !calendarTitle) {
			return;
		}

		var monthDate = getMonthStart(state.currentMonth);
		var daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
		var startDay = monthDate.getDay();
		var availableDateMap = {};
		var parts = [];
		var weekdayNames = Array.isArray(i18n.weekdayNames) ? i18n.weekdayNames : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

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

			parts.push(
				'<button type="button" class="' + className + '" data-qs-date="' + dateKey + '"' +
					(isAvailable ? '' : ' disabled') +
					' aria-pressed="' + (isSelected ? 'true' : 'false') + '">' +
					escapeHtml(String(day)) +
				'</button>'
			);
		}

		calendarGrid.innerHTML = parts.join('');
		updateCalendarNavigation();

		if (!state.availableDates.length) {
			setFeedback('date', text('noDates', 'No dates available'), 'empty');
		} else {
			setFeedback('date', '', '');
		}
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

	function renderDatePrompt() {
		if (!calendarGrid || !calendarTitle) {
			return;
		}

		calendarTitle.textContent = getMonthLabel(state.currentMonth);
		calendarGrid.innerHTML = '<div class="qs-empty-state">' + escapeHtml(text('selectService', 'Select a service to continue.')) + '</div>';
		setFeedback('date', '', '');
		updateCalendarNavigation();
	}

	function renderTimePrompt() {
		if (!slotsContainer) {
			return;
		}

		slotsContainer.innerHTML = '<div class="qs-empty-state">' + escapeHtml(text('selectDate', 'Select an available date to continue.')) + '</div>';
		setFeedback('time', '', '');
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
		}

		renderServices(state.services);
		setActiveStep(stepIndexes.date);
		loadDates(state.currentMonth, true);
	}

	function handleDateSelection(dateKey) {
		if (state.selectedDate === dateKey) {
			setActiveStep(stepIndexes.time);
			loadSlots(dateKey, true);
			return;
		}

		state.selectedDate = dateKey;
		resetSlotSelection();
		renderCalendar();
		setActiveStep(stepIndexes.time);
		loadSlots(dateKey, true);
	}

	function handleSlotSelection(slot) {
		state.selectedSlot = slot;
		renderSlots();
		setActiveStep(stepIndexes.details);
	}

	form.addEventListener('click', function (event) {
		var nextButton = event.target.closest('[data-qs-nav="next"]');
		var backButton = event.target.closest('[data-qs-nav="back"]');
		var serviceButton = event.target.closest('[data-qs-service-id]');
		var dateButton = event.target.closest('[data-qs-date]');
		var slotButton = event.target.closest('[data-qs-slot]');
		var calendarNav = event.target.closest('[data-qs-calendar-nav]');

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

		if (nextButton) {
			event.preventDefault();
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
