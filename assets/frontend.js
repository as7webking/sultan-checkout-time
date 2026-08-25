(function () {
	'use strict';

	function isPickupSelect(select) {
		var name = select.getAttribute('name') || '';
		var id = select.getAttribute('id') || '';

		return name.indexOf('sultan_pickup_time') !== -1 || id.indexOf('sultan_pickup_time') !== -1;
	}

	function optionSignature(select) {
		return Array.prototype.map.call(select.options, function (option) {
			return option.value + ':' + option.text + ':' + (option.disabled ? '1' : '0');
		}).join('|');
	}

	function syncButton(select, button) {
		var selected = select.options[select.selectedIndex];
		button.querySelector('.sultan-time-select__value').textContent = selected ? selected.text : '';
	}

	function closeDropdown(wrapper) {
		wrapper.classList.remove('is-open', 'opens-up');
		wrapper.querySelector('.sultan-time-select__button').setAttribute('aria-expanded', 'false');
	}

	function chooseOption(select, wrapper, value) {
		var descriptor = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value');

		if (descriptor && descriptor.set) {
			descriptor.set.call(select, value);
		} else {
			select.value = value;
		}

		select.dispatchEvent(new Event('input', { bubbles: true }));
		select.dispatchEvent(new Event('change', { bubbles: true }));
		syncButton(select, wrapper.querySelector('.sultan-time-select__button'));
		closeDropdown(wrapper);
	}

	function rebuildOptions(select, wrapper) {
		var list = wrapper.querySelector('.sultan-time-select__menu');
		list.innerHTML = '';

		Array.prototype.forEach.call(select.options, function (option) {
			var item = document.createElement('button');
			item.type = 'button';
			item.className = 'sultan-time-select__option';
			item.textContent = option.text;
			item.setAttribute('role', 'option');
			item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
			item.disabled = option.disabled || option.value === 'no_slots';

			if (option.selected) {
				item.classList.add('is-selected');
			}

			item.addEventListener('click', function () {
				chooseOption(select, wrapper, option.value);
			});

			list.appendChild(item);
		});

		select.dataset.sultanOptionSignature = optionSignature(select);
		syncButton(select, wrapper.querySelector('.sultan-time-select__button'));
	}

	function enhanceSelect(select) {
		if (!isPickupSelect(select)) {
			return;
		}

		if (select.dataset.sultanEnhanced === '1') {
			var existingWrapper = select.nextElementSibling;

			if (existingWrapper && existingWrapper.classList.contains('sultan-time-select')) {
				if (select.dataset.sultanOptionSignature !== optionSignature(select)) {
					rebuildOptions(select, existingWrapper);
				}
			}

			return;
		}

		select.dataset.sultanEnhanced = '1';
		select.classList.add('sultan-time-select__native');

		var wrapper = document.createElement('div');
		wrapper.className = 'sultan-time-select';
		wrapper.innerHTML =
			'<button type="button" class="sultan-time-select__button" aria-haspopup="listbox" aria-expanded="false">' +
				'<span class="sultan-time-select__value"></span>' +
				'<span class="sultan-time-select__chevron" aria-hidden="true"></span>' +
			'</button>' +
			'<div class="sultan-time-select__menu" role="listbox"></div>';

		select.insertAdjacentElement('afterend', wrapper);
		rebuildOptions(select, wrapper);

		var button = wrapper.querySelector('.sultan-time-select__button');

		button.addEventListener('click', function () {
			var opening = !wrapper.classList.contains('is-open');

			document.querySelectorAll('.sultan-time-select.is-open').forEach(closeDropdown);

			if (!opening) {
				return;
			}

			var rect = button.getBoundingClientRect();
			var spaceBelow = window.innerHeight - rect.bottom;
			wrapper.classList.toggle('opens-up', spaceBelow < 260 && rect.top > spaceBelow);
			wrapper.classList.add('is-open');
			button.setAttribute('aria-expanded', 'true');
		});

		select.addEventListener('change', function () {
			rebuildOptions(select, wrapper);
		});
	}

	function scan() {
		document.querySelectorAll('select').forEach(enhanceSelect);
	}

	document.addEventListener('click', function (event) {
		document.querySelectorAll('.sultan-time-select.is-open').forEach(function (wrapper) {
			if (!wrapper.contains(event.target)) {
				closeDropdown(wrapper);
			}
		});
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			document.querySelectorAll('.sultan-time-select.is-open').forEach(closeDropdown);
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', scan);
	} else {
		scan();
	}

	new MutationObserver(scan).observe(document.documentElement, {
		childList: true,
		subtree: true
	});
}());
