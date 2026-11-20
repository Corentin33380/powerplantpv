/* global window, document */
(function () {
	'use strict';
	var mobileMediaQuery = window.matchMedia ? window.matchMedia('(max-width: 767px)') : null;
	var editorIframeSelector = '.powerplantpv-report-input iframe';
	var editorFontMarker = 'data-powerplantpv-mobile-font-size';
	var editorOriginalFontSize = 'data-powerplantpv-original-font-size';
	var editorOriginalFontPriority = 'data-powerplantpv-original-font-priority';

	function isMobileReportViewport() {
		return mobileMediaQuery ? mobileMediaQuery.matches : window.innerWidth <= 767;
	}

	function updateEditorIframeFontSize(iframe) {
		var editorDocument;
		var editorBody;

		try {
			editorDocument = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
			editorBody = editorDocument ? editorDocument.body : null;
		} catch (error) {
			return;
		}

		if (!editorBody) {
			return;
		}

		if (isMobileReportViewport()) {
			if (!editorBody.hasAttribute(editorFontMarker)) {
				editorBody.setAttribute(editorFontMarker, '1');
				editorBody.setAttribute(editorOriginalFontSize, editorBody.style.getPropertyValue('font-size'));
				editorBody.setAttribute(editorOriginalFontPriority, editorBody.style.getPropertyPriority('font-size'));
			}
			editorBody.style.setProperty('font-size', '16px', 'important');
			return;
		}

		if (editorBody.hasAttribute(editorFontMarker)) {
			var originalFontSize = editorBody.getAttribute(editorOriginalFontSize) || '';
			var originalFontPriority = editorBody.getAttribute(editorOriginalFontPriority) || '';
			if (originalFontSize) {
				editorBody.style.setProperty('font-size', originalFontSize, originalFontPriority);
			} else {
				editorBody.style.removeProperty('font-size');
			}
			editorBody.removeAttribute(editorFontMarker);
			editorBody.removeAttribute(editorOriginalFontSize);
			editorBody.removeAttribute(editorOriginalFontPriority);
		}
	}

	function registerEditorIframe(iframe) {
		if (!iframe.matches(editorIframeSelector)) {
			return;
		}

		if (!iframe.hasAttribute('data-powerplantpv-font-listener')) {
			iframe.setAttribute('data-powerplantpv-font-listener', '1');
			iframe.addEventListener('load', function () {
				updateEditorIframeFontSize(iframe);
			});
		}

		updateEditorIframeFontSize(iframe);
	}

	function updateEditorIframes(form) {
		var editorIframes = form.querySelectorAll(editorIframeSelector);
		for (var i = 0; i < editorIframes.length; i++) {
			registerEditorIframe(editorIframes[i]);
		}
	}

	function observeEditorIframes(form) {
		if (!window.MutationObserver) {
			return;
		}

		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				for (var j = 0; j < mutations[i].addedNodes.length; j++) {
					var addedNode = mutations[i].addedNodes[j];
					if (addedNode.nodeType !== 1) {
						continue;
					}
					if (addedNode.matches && addedNode.matches(editorIframeSelector)) {
						registerEditorIframe(addedNode);
					}
					if (addedNode.querySelectorAll) {
						var nestedIframes = addedNode.querySelectorAll(editorIframeSelector);
						for (var k = 0; k < nestedIframes.length; k++) {
							registerEditorIframe(nestedIframes[k]);
						}
					}
				}
			}
		});

		observer.observe(form, {childList: true, subtree: true});
	}

	function getScrollY() {
		if (typeof window.scrollY === 'number') {
			return window.scrollY;
		}

		return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
	}

	function captureScrollPosition(form) {
		var scrollInput = form.querySelector('#powerplantpv-scroll-y');
		if (!scrollInput) {
			return;
		}

		scrollInput.value = String(Math.max(0, Math.round(getScrollY())));
	}

	function cleanScrollParameter() {
		if (!window.history || !window.history.replaceState || !window.URL) {
			return;
		}

		var url = new URL(window.location.href);
		if (!url.searchParams.has('report_scroll_y')) {
			return;
		}

		url.searchParams.delete('report_scroll_y');
		window.history.replaceState(null, document.title, url.pathname + url.search + url.hash);
	}

	function restoreScrollPosition() {
		var reportForm = document.getElementById('powerplantpvreportform');
		if (reportForm && reportForm.dataset.advanceAfterSave === '1') { return; }
		var restoreInput = document.getElementById('powerplantpv-restore-scroll-y');
		if (!restoreInput) {
			return;
		}

		var scrollY = parseInt(restoreInput.value, 10);
		if (!Number.isFinite(scrollY) || scrollY < 0) {
			return;
		}

		var restore = function () { window.scrollTo(0, scrollY); };
		if (document.readyState !== 'complete') { window.addEventListener('load', restore, {once: true}); }
		window.requestAnimationFrame(function () {
			window.scrollTo(0, scrollY);
			window.setTimeout(function () {
				window.scrollTo(0, scrollY);
				cleanScrollParameter();
			}, 150);
		});
	}

	function enableReportWorkflow(form) {
		if (form.dataset.workflowReady || !form.classList.contains('powerplantpv-report-form-editable')) { return; }
		form.dataset.workflowReady = '1';
		var dirty = false;
		var submitted = false;
		var state = form.querySelector('.powerplantpv-report-save-state');
		var storageKey = 'powerplantpv-report-mobile-view-v2:' + window.location.pathname + ':' + (form.querySelector('[name="id"]') || {}).value;
		var sections = form.querySelectorAll('details');
		function sectionKey(section, index) { return section.dataset.stateKey || section.dataset.cardKey || 'summary:' + index; }
		var view = null;
		if (isMobileReportViewport()) {
			try {
				view = JSON.parse(window.sessionStorage.getItem(storageKey));
				if (view && view.sections) {
					sections.forEach(function (section, index) {
						var key = sectionKey(section, index);
						if (Object.prototype.hasOwnProperty.call(view.sections, key)) { section.open = view.sections[key] === true; }
					});
				}
			} catch (error) { /* Optional navigation memory; never store field values. */ }
		}
		function rememberView() {
			if (!isMobileReportViewport()) { return; }
			var states = {};
			sections.forEach(function (section, index) { states[sectionKey(section, index)] = section.open; });
			try { window.sessionStorage.setItem(storageKey, JSON.stringify({sections: states, y: Math.max(0, Math.round(getScrollY()))})); } catch (error) { /* Storage may be disabled. */ }
		}
		sections.forEach(function (section) { section.addEventListener('toggle', rememberView); });
		window.addEventListener('pagehide', rememberView);
		var rememberingScroll = false;
		window.addEventListener('scroll', function () {
			if (rememberingScroll || !isMobileReportViewport()) { return; }
			rememberingScroll = true;
			window.requestAnimationFrame(function () { rememberingScroll = false; rememberView(); });
		}, {passive: true});
		// Restore only after all saved disclosure states have been applied.
		var savedNavigation = new URL(window.location.href).searchParams.has('report_saved_card');
		if (!savedNavigation && isMobileReportViewport() && view && Number.isFinite(view.y)) {
			var position = document.getElementById('powerplantpv-restore-scroll-y');
			if (position && !new URL(window.location.href).searchParams.has('report_scroll_y')) { position.value = String(Math.max(0, view.y)); }
		}
		function updateProgress(target) {
			var field = target && target.closest ? target.closest('[data-progress-required="1"]') : null;
			if (field) {
				var type = field.dataset.progressType;
				var controls = Array.prototype.slice.call(field.querySelectorAll('input:not([type="hidden"]), select, textarea'));
				var filled = controls.some(function (control) {
					if (control.type === 'file') { return control.files.length > 0 || field.dataset.progressFilled === '1'; }
					if (type === 'yesno' || type === 'checkbox') { return control.value === '0' || control.value === '1'; }
					if (control.multiple) { return control.selectedOptions.length > 0; }
					return control.value.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() !== '';
				});
				if (type === 'date' || type === 'datetime') {
					var parts = controls.filter(function (control) { return /(?:day|month|year)$/.test(control.name); });
					filled = parts.length >= 3 && parts.every(function (control) { return Number(control.value) > 0; });
				}
				field.dataset.progressFilled = filled ? '1' : '0';
			}
			form.querySelectorAll('.powerplantpv-report-section').forEach(function (section) {
				var fields = section.querySelectorAll('[data-progress-required="1"]');
				var progress = section.querySelector('.powerplantpv-report-progress');
				if (!progress || !fields.length) { return; }
				var filled = 0;
				fields.forEach(function (item) { if (item.dataset.progressFilled === '1') { filled++; } });
				progress.textContent = ' · ' + filled + '/' + fields.length;
				progress.title = progress.dataset.progressLabel;
			});
		}
		updateProgress(null);
		// Only the successful POST redirect carries this marker. Errors leave the card open.
		var savedUrl = new URL(window.location.href);
		var savedKey = savedUrl.searchParams.get('report_saved_card');
		if (savedKey && /^[a-f0-9]{32}$/.test(savedKey) && isMobileReportViewport()) {
			form.dataset.advanceAfterSave = '1';
			var cards = Array.prototype.slice.call(form.querySelectorAll('details.powerplantpv-dc-card'));
			var savedIndex = cards.findIndex(function (card) { return card.dataset.cardKey === savedKey; });
			if (savedIndex >= 0) {
				cards[savedIndex].open = false;
				var next = cards.slice(savedIndex + 1).find(function (card) { return card.querySelector('.powerplantpv-save-collapse'); });
				if (!next) {
					var reportSections = Array.prototype.slice.call(form.querySelectorAll('.powerplantpv-report-section'));
					var currentSection = cards[savedIndex].closest('.powerplantpv-report-section');
					next = reportSections[reportSections.indexOf(currentSection) + 1] || form.querySelector('.powerplantpv-floating-save');
				}
				if (next) {
					var ancestor = next.closest('details');
					while (ancestor) { ancestor.open = true; ancestor = ancestor.parentElement.closest('details'); }
					window.setTimeout(function () { next.scrollIntoView({block: 'start'}); rememberView(); }, 180);
				}
			}
		}
		if (savedKey) { savedUrl.searchParams.delete('report_saved_card'); window.history.replaceState(null, document.title, savedUrl.pathname + savedUrl.search + savedUrl.hash); }
		form.querySelectorAll('.powerplantpv-save-collapse').forEach(function (button) {
			button.addEventListener('click', function (event) { event.stopPropagation(); });
		});

		form.addEventListener('input', function (event) { updateProgress(event.target); });
		form.addEventListener('change', function (event) { updateProgress(event.target); });
		function markDirty() {
			dirty = true;
			if (state) { state.textContent = form.dataset.unsaved; }
		}
		form.addEventListener('input', markDirty);
		form.addEventListener('change', markDirty);
		// Dolibarr's Select2 emits jQuery change events.
		if (window.jQuery) { window.jQuery(form).on('change.powerplantpvReport', 'select', function () { markDirty(); updateProgress(this); }); }
		function registerEditor(iframe) {
			if (iframe.dataset.powerplantpvDirtyRegistered) { return; }
			iframe.dataset.powerplantpvDirtyRegistered = '1';
			function connect() {
				try {
					var body = iframe.contentDocument && iframe.contentDocument.body;
					if (body && !body.dataset.powerplantpvDirtyListener) { body.dataset.powerplantpvDirtyListener = '1'; body.addEventListener('input', function () { markDirty(); var field = iframe.closest('[data-progress-required="1"]'); if (field) { field.dataset.progressFilled = body.textContent.trim() ? '1' : '0'; updateProgress(null); } }); }
				} catch (error) { /* Cross-origin editors cannot be read. */ }
			}
			iframe.addEventListener('load', connect); connect();
		}
		form.querySelectorAll('iframe').forEach(registerEditor);
		if (window.MutationObserver) {
			new MutationObserver(function () { form.querySelectorAll('iframe').forEach(registerEditor); }).observe(form, {childList: true, subtree: true});
		}
		form.addEventListener('submit', function (event) {
			if (event.defaultPrevented) { return; }
			if (submitted) { event.preventDefault(); return; }
			submitted = true;
			if (state) { state.textContent = form.dataset.saving; }
			rememberView();
			// Restore the retry action if a navigation fails without replacing the page.
			window.setTimeout(function () { submitted = false; if (dirty && state) { state.textContent = form.dataset.unsaved; } }, 10000);
		});
		window.addEventListener('beforeunload', function (event) {
			if (dirty && !submitted) { event.preventDefault(); event.returnValue = ''; }
		});
		window.addEventListener('pageshow', function () { submitted = false; });
		form.addEventListener('invalid', function (event) {
			var detail = event.target.closest('details');
			while (detail) { detail.open = true; detail = detail.parentElement.closest('details'); }
		}, true);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('powerplantpvreportform');
		if (form) {
			enableReportWorkflow(form);
			form.addEventListener('submit', function () {
				captureScrollPosition(form);
			});
			updateEditorIframes(form);
			observeEditorIframes(form);

			var updateFonts = function () {
				updateEditorIframes(form);
				if (!isMobileReportViewport()) { form.querySelectorAll('details.powerplantpv-dc-card').forEach(function (card) { card.open = true; }); }
			};
			if (mobileMediaQuery) {
				if (mobileMediaQuery.addEventListener) {
					mobileMediaQuery.addEventListener('change', updateFonts);
				} else if (mobileMediaQuery.addListener) {
					mobileMediaQuery.addListener(updateFonts);
				}
			} else {
				window.addEventListener('resize', updateFonts);
			}
		}

		var dcHistoryTarget = /^#powerplantpv-dc-measures-\d+$/.test(window.location.hash)
			? document.getElementById(window.location.hash.slice(1)) : null;
		if (dcHistoryTarget) {
			dcHistoryTarget.open = true;
			var showDcHistory = function () {
				dcHistoryTarget.scrollIntoView({block: 'start'});
			};
			window.requestAnimationFrame(showDcHistory);
			if (document.readyState !== 'complete') {
				window.addEventListener('load', showDcHistory, {once: true});
			}
		} else {
			restoreScrollPosition();
		}
	});
}());

jQuery(function ($) {
	const form = $('#powerplantpv-signature-form');
	if (!form.length || typeof $.fn.jSignature !== 'function') {
		return;
	}
	const pad = $('#powerplantpv-signature-pad');
	pad.jSignature({ color: '#000', height: 180 });
	$('#powerplantpv-signature-clear').on('click', function () {
		pad.jSignature('clear');
	});
	form.on('submit', function () {
		const drawing = pad.jSignature('getData', 'image');
		form.find('[name="signature_image"]').val('data:' + drawing.join(','));
	});
});
