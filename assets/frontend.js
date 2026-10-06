(function () {
	'use strict';
	var minimumSubscriptionBound = false;

	function getBlockItemsTotal() {
		if (!window.wp || !window.wp.data || !window.wp.data.select) {
			return null;
		}

		var cartStore = window.wp.data.select('wc/store/cart');
		if (!cartStore) {
			return null;
		}

		var cartData = typeof cartStore.getCartData === 'function' ? cartStore.getCartData() : null;
		var totals = typeof cartStore.getCartTotals === 'function'
			? cartStore.getCartTotals()
			: cartData && (cartData.cartTotals || cartData.totals);
		if (!totals || !Object.prototype.hasOwnProperty.call(totals, 'total_items')) {
			return null;
		}

		var minorUnit = Number(totals.currency_minor_unit || window.sultanCheckoutConfig.currencyDecimals || 2);
		var itemsSubtotal = Number(totals.total_items) || 0;
		var discounts = Number(totals.total_discount) || 0;
		return Math.max(0, itemsSubtotal - discounts) / Math.pow(10, minorUnit);
	}

	function getItemsTotal(config) {
		var blockTotal = getBlockItemsTotal();
		if (blockTotal !== null && Number.isFinite(blockTotal)) {
			return blockTotal;
		}

		var dataNode = document.getElementById('sultan-minimum-order-data');
		if (dataNode && dataNode.dataset.itemsTotal !== undefined) {
			return Number(dataNode.dataset.itemsTotal) || 0;
		}

		return Number(config.itemsTotal) || 0;
	}

	function formatMoney(amount, config) {
		var locale = String(config.locale || 'en-US').replace(/_/g, '-');
		try {
			return new Intl.NumberFormat(locale, {
				style: config.currency ? 'currency' : 'decimal',
				currency: config.currency || undefined,
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(amount);
		} catch (error) {
			return amount.toFixed(2) + (config.currency ? ' ' + config.currency : '');
		}
	}

	function getPlaceOrderButtons() {
		return document.querySelectorAll('#place_order, .wc-block-components-checkout-place-order-button');
	}

	function isLocalPickupSelected() {
		return Array.prototype.some.call(document.querySelectorAll('input[type="radio"]:checked'), function (input) {
			var value = String(input.value || '').toLowerCase();
			return value.indexOf('local_pickup') !== -1 || value.indexOf('local-pickup') !== -1;
		});
	}

	function updateMinimumOrder() {
		var config = window.sultanCheckoutConfig;
		if (!config) {
			return;
		}

		var minimum = !config.requiresShipping || isLocalPickupSelected() ? 0 : Number(config.minimumOrder);
		var itemsTotal = getItemsTotal(config);
		var remaining = Math.max(0, minimum - itemsTotal);
		var belowMinimum = remaining > 0.00001;
		var buttons = getPlaceOrderButtons();
		var notice = document.getElementById('sultan-minimum-order-notice');

		if (!notice && buttons.length) {
			notice = document.createElement('div');
			notice.id = 'sultan-minimum-order-notice';
			notice.className = 'sultan-minimum-order-notice';
			notice.setAttribute('role', 'status');
			notice.setAttribute('aria-live', 'polite');
			buttons[0].parentNode.insertBefore(notice, buttons[0]);
		}

		buttons.forEach(function (button) {
			if (belowMinimum) {
				if (button.dataset.sultanMinimumDisabled !== '1') {
					button.dataset.sultanWasDisabled = button.disabled ? '1' : '0';
					button.dataset.sultanHadAriaDisabled = button.getAttribute('aria-disabled') || '';
				}
				button.disabled = true;
				button.setAttribute('aria-disabled', 'true');
				button.dataset.sultanMinimumDisabled = '1';
			} else if (button.dataset.sultanMinimumDisabled === '1') {
				button.disabled = button.dataset.sultanWasDisabled === '1';
				if (button.dataset.sultanHadAriaDisabled) {
					button.setAttribute('aria-disabled', button.dataset.sultanHadAriaDisabled);
				} else {
					button.removeAttribute('aria-disabled');
				}
				delete button.dataset.sultanMinimumDisabled;
				delete button.dataset.sultanWasDisabled;
				delete button.dataset.sultanHadAriaDisabled;
			}
		});

		if (!notice) {
			return;
		}

		notice.hidden = !belowMinimum;
		if (!belowMinimum) {
			notice.replaceChildren();
			return;
		}

		var message = String(config.minimumText || 'The minimum order is %1$s. Add %2$s more in products to continue.')
			.replace('%1$s', formatMoney(minimum, config))
			.replace('%2$s', formatMoney(remaining, config));
		var text = document.createElement('p');
		text.textContent = message;
		var link = document.createElement('a');
		link.href = config.shopUrl || '/';
		link.textContent = config.shopLabel || 'Browse products';
		notice.replaceChildren(text, link);
	}

	function bindMinimumOrderUpdates() {
		if (minimumSubscriptionBound || !window.wp || !window.wp.data || typeof window.wp.data.subscribe !== 'function') {
			return;
		}

		minimumSubscriptionBound = true;
		window.wp.data.subscribe(updateMinimumOrder);
	}

	function syncFixedCountry() {
		var config = window.sultanCheckoutConfig;
		if (!config || !config.isCheckout || !config.fixedCountry) {
			return;
		}

		document.querySelectorAll('select[name="billing_country"], select[name="shipping_country"]').forEach(function (select) {
			if (select.value === config.fixedCountry) {
				return;
			}

			var descriptor = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value');
			if (descriptor && descriptor.set) {
				descriptor.set.call(select, config.fixedCountry);
			} else {
				select.value = config.fixedCountry;
			}
			select.dispatchEvent(new Event('input', { bubbles: true }));
			select.dispatchEvent(new Event('change', { bubbles: true }));
		});
	}

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
		bindMinimumOrderUpdates();
		syncFixedCountry();
		updateMinimumOrder();
	}

	document.body.addEventListener('updated_checkout', updateMinimumOrder);
	document.body.addEventListener('change', updateMinimumOrder);

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
