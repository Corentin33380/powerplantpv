/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
(function () {
	'use strict';

	function decorateHomeTiles() {
		function decorate(iconSelector, colors) {
			var icon = document.querySelector(iconSelector);
			var tile = icon && icon.closest('.info-box');
			if (!tile) return null;
			tile.classList.add('powerplantpv-home-tile');
			tile.querySelectorAll('.info-box-line').forEach(function (line) {
				var link = line.querySelector('a[href]');
				if (!link) return;
				var status = new URL(link.href).searchParams.get('search_status');
				var badge = line.querySelector('.classfortooltip');
				if (!badge || !colors[status]) return;
				badge.classList.remove('opacitymedium', 'badge-info', 'badge-secondary', 'badge-success', 'badge-warning', 'badge-danger');
				badge.classList.add('badge', colors[status]);
			});
			return tile;
		}

		var plants = decorate('.bg-infobox-powerplantpv', {
			'1': 'badge-secondary', '2': 'badge-success', '3': 'badge-danger'
		});
		decorate('.bg-infobox-powerplantpvmaintenance', {
			'planned,due,scheduled,overdue,covered,incomplete': 'badge-secondary',
			'planned,due': 'badge-warning', 'overdue': 'badge-danger'
		});
		if (!plants) return;
		var inService = plants.querySelector('a[href$="search_status=2"]');
		var outOfService = plants.querySelector('a[href$="search_status=3"]');
		if (!inService || !outOfService) return;
		var serviceLine = inService.closest('.info-box-line');
		var outLine = outOfService.closest('.info-box-line');
		if (!serviceLine || !outLine || serviceLine === outLine) return;
		var labels = serviceLine.querySelector('.info-box-line-text');
		var outLabel = outLine.querySelector('.info-box-line-text');
		var counts = serviceLine.querySelector('.nowraponall');
		var outCounts = outLine.querySelector('.nowraponall');
		if (!labels || !outLabel || !counts || !outCounts) return;

		// Keep both original links and tooltips; only their visual grouping changes.
		var labelGroup = labels.cloneNode(false);
		[inService, outOfService].forEach(function (link, index) {
			var label = link.querySelector('.info-box-line-text');
			if (index) labelGroup.appendChild(document.createTextNode(' / '));
			link.setAttribute('title', label.textContent);
			link.replaceChildren(document.createTextNode(label.textContent));
			labelGroup.appendChild(link);
		});
		counts.appendChild(document.createTextNode(' '));
		while (outCounts.firstChild) counts.appendChild(outCounts.firstChild);
		serviceLine.replaceChildren(labelGroup, counts);
		outLine.remove();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', decorateHomeTiles);
	} else {
		decorateHomeTiles();
	}
}());
