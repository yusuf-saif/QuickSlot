document.addEventListener('DOMContentLoaded', function () {
	var form = document.getElementById('qs-booking-form');

	if (!form) {
		return;
	}

	var panels = Array.prototype.slice.call(form.querySelectorAll('[data-step-panel]'));
	var progressSteps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
	var activeIndex = 0;

	function setActiveStep(index) {
		if (index < 0 || index >= panels.length) {
			return;
		}

		activeIndex = index;

		panels.forEach(function (panel, panelIndex) {
			var isActive = panelIndex === activeIndex;
			panel.classList.toggle('is-active', isActive);
			panel.hidden = !isActive;
		});

		progressSteps.forEach(function (step, stepIndex) {
			step.classList.toggle('is-active', stepIndex === activeIndex);
		});

		form.setAttribute('data-active-step', panels[activeIndex].getAttribute('data-step-panel') || 'service');
	}

	form.addEventListener('click', function (event) {
		var nextButton = event.target.closest('[data-qs-nav="next"]');
		var backButton = event.target.closest('[data-qs-nav="back"]');

		if (nextButton) {
			event.preventDefault();
			setActiveStep(activeIndex + 1);
		}

		if (backButton) {
			event.preventDefault();
			setActiveStep(activeIndex - 1);
		}
	});

	setActiveStep(0);
});
