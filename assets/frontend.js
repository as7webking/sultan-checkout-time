(function () {
	'use strict';
	var minimumSubscriptionBound = false;
	var deliveryInitialCheckStarted = false;

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
				minimumFractionDigits: Number(config.currencyDecimals || 2),
				maximumFractionDigits: Number(config.currencyDecimals || 2)
			}).format(amount);
		} catch (error) {
			return amount.toFixed(2) + (config.currency ? ' ' + config.currency : '');
		}
	}

	function getPlaceOrderButtons() {
		return document.querySelectorAll('#place_order, .wc-block-components-checkout-place-order-button, .checkout-button, .wc-block-cart__submit-button, .wc-block-components-proceed-to-checkout-button');
	}

	function isLocalPickupSelected() {
		return Array.prototype.some.call(document.querySelectorAll('input[type="radio"]:checked'), function (input) {
			var value = String(input.value || '').toLowerCase();
			return value.indexOf('local_pickup') !== -1 || value.indexOf('local-pickup') !== -1;
		});
	}

	function updateMinimumOrder() {
		var config = window.sultanCheckoutConfig;
		if (!config || (!config.isCart && !config.isCheckout)) {
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
					button.dataset.sultanHadTabindex = button.getAttribute('tabindex') || '';
				}
				if ('disabled' in button) {
					button.disabled = true;
				}
				button.setAttribute('aria-disabled', 'true');
				button.setAttribute('tabindex', '-1');
				button.classList.add('sultan-minimum-disabled');
				button.dataset.sultanMinimumDisabled = '1';
			} else if (button.dataset.sultanMinimumDisabled === '1') {
				if ('disabled' in button) {
					button.disabled = button.dataset.sultanWasDisabled === '1';
				}
				if (button.dataset.sultanHadAriaDisabled) {
					button.setAttribute('aria-disabled', button.dataset.sultanHadAriaDisabled);
				} else {
					button.removeAttribute('aria-disabled');
				}
				if (button.dataset.sultanHadTabindex) {
					button.setAttribute('tabindex', button.dataset.sultanHadTabindex);
				} else {
					button.removeAttribute('tabindex');
				}
				button.classList.remove('sultan-minimum-disabled');
				delete button.dataset.sultanMinimumDisabled;
				delete button.dataset.sultanWasDisabled;
				delete button.dataset.sultanHadAriaDisabled;
				delete button.dataset.sultanHadTabindex;
			}
		});

		if (!notice) {
			return;
		}

		if (!belowMinimum) {
			if (!notice.hidden) {
				notice.hidden = true;
			}
			if (notice.firstChild) {
				notice.replaceChildren();
			}
			return;
		}
		if (notice.hidden) {
			notice.hidden = false;
		}

		var message = String(config.minimumText || 'The minimum order is %1$s. Add %2$s more in products to continue.')
			.replace('%1$s', formatMoney(minimum, config))
			.replace('%2$s', formatMoney(remaining, config));
		var text = notice.querySelector('.sultan-minimum-order-message');
		if (!text) {
			text = document.createElement('p');
			text.className = 'sultan-minimum-order-message';
			notice.appendChild(text);
		}
		if (text.textContent !== message) {
			text.textContent = message;
		}

		var link = notice.querySelector('.sultan-minimum-order-shop-link');
		if (!link) {
			link = document.createElement('a');
			link.className = 'sultan-minimum-order-shop-link';
			notice.appendChild(link);
		}
		var shopUrl = config.shopUrl || '/';
		var shopLabel = config.shopLabel || 'Browse products';
		if (link.href !== shopUrl || link.textContent !== shopLabel) {
			link.href = shopUrl;
			link.textContent = shopLabel;
		}

		if (config.isCart && Array.isArray(config.recommendations) && config.recommendations.length && !isLocalPickupSelected()) {
			if (!notice.querySelector('.sultan-cart-recommendations')) {
				var title = document.createElement('h3');
				title.className = 'sultan-cart-recommendation-title';
				title.textContent = config.cartRecommendationTitle || 'Add more products to reach the delivery minimum';
				var products = document.createElement('div');
				products.className = 'sultan-cart-recommendations';
				config.recommendations.forEach(function (product) {
					var card = document.createElement('article');
					card.className = 'sultan-cart-recommendation';
					var productLink = document.createElement('a');
					productLink.href = product.url;
					productLink.className = 'sultan-cart-recommendation__product';
					var image = document.createElement('img');
					image.src = product.image;
					image.alt = '';
					image.loading = 'lazy';
					var name = document.createElement('span');
					name.textContent = product.name;
					productLink.append(image, name);
					var price = document.createElement('span');
					price.className = 'sultan-cart-recommendation__price';
					price.textContent = product.price;
					card.append(productLink, price);
					if (product.isSimple && product.cartUrl) {
						var addLink = document.createElement('a');
						addLink.href = product.cartUrl;
						addLink.className = 'sultan-cart-recommendation__add';
						addLink.textContent = product.cartLabel || config.addProductLabel || 'Add';
						card.appendChild(addLink);
					}
					products.appendChild(card);
				});
				notice.append(title, products);
			}
		} else {
			var recommendationTitle = notice.querySelector('.sultan-cart-recommendation-title');
			var recommendationGrid = notice.querySelector('.sultan-cart-recommendations');
			if (recommendationTitle) {
				recommendationTitle.remove();
			}
			if (recommendationGrid) {
				recommendationGrid.remove();
			}
		}
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

		document.querySelectorAll('select[name="billing_country"], select[name="shipping_country"], select[name="billing-country"], select[name="shipping-country"]').forEach(function (select) {
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

	var deliveryCheckTimer = null;
	var deliveryCheckSequence = 0;

	function findAddressField(prefix, field) {
		var dashedField = field.replace(/_/g, '-');
		var selectors = [
			'#' + prefix + '_' + field,
			'[name="' + prefix + '_' + field + '"]',
			'#' + prefix + '-' + dashedField,
			'[name="' + prefix + '-' + dashedField + '"]'
		];
		return document.querySelector(selectors.join(','));
	}

	function readAddressField(prefix, field, config) {
		var input = findAddressField(prefix, field);
		if (input && input.value) {
			return String(input.value).trim();
		}
		if ('country' === field && config.fixedCountry) {
			return config.fixedCountry;
		}
		return '';
	}

	function getDeliveryAddress(config) {
		var prefix = 'billing';
		if (config.isCart) {
			prefix = 'calc_shipping';
		} else {
			var shippingCheckbox = document.querySelector('#ship-to-different-address-checkbox, [name="ship_to_different_address"]');
			var shippingAddress = findAddressField('shipping', 'address_1');
			if ((shippingCheckbox && shippingCheckbox.checked) || (shippingAddress && shippingAddress.value)) {
				prefix = 'shipping';
			}
		}

		return {
			prefix: prefix,
			address_1: readAddressField(prefix, 'address_1', config),
			address_2: readAddressField(prefix, 'address_2', config),
			postcode: readAddressField(prefix, 'postcode', config),
			city: readAddressField(prefix, 'city', config),
			state: readAddressField(prefix, 'state', config),
			country: readAddressField(prefix, 'country', config)
		};
	}

	function getDeliveryStatusNode(prefix) {
		var target = findAddressField(prefix, 'address_1') || findAddressField(prefix, 'postcode');
		if (!target && 'calc_shipping' === prefix) {
			target = document.querySelector('#calc_shipping_postcode, [name="calc_shipping_postcode"]');
		}
		if (!target) {
			return null;
		}

		var status = document.getElementById('sultan-delivery-status');
		if (!status) {
			status = document.createElement('div');
			status.id = 'sultan-delivery-status';
			status.className = 'sultan-delivery-status';
			status.setAttribute('role', 'status');
			status.setAttribute('aria-live', 'polite');
		}

		var container = target.closest('.form-row, .wc-block-components-text-input, .wc-block-components-select, .wc-block-components-address-form__postcode, .wc-block-components-address-form__address_1') || target.parentElement;
		if (container && status.parentElement !== container) {
			container.appendChild(status);
		}
		return status;
	}

	function setDeliveryStatus(prefix, state, message) {
		var status = getDeliveryStatusNode(prefix);
		if (!status) {
			return;
		}
		var className = 'sultan-delivery-status is-' + state;
		if (status.className !== className) {
			status.className = className;
		}
		if (status.textContent !== message) {
			status.textContent = message;
		}
	}

	function checkDeliveryAvailability() {
		var config = window.sultanCheckoutConfig;
		if (!config || !config.ajaxUrl || (!config.isCheckout && !config.isCart)) {
			return;
		}

		var address = getDeliveryAddress(config);
		if (!address.address_1 || !address.postcode || !address.city || !address.country) {
			setDeliveryStatus(address.prefix, 'incomplete', config.deliveryIncomplete || 'Enter your full delivery address to check delivery.');
			return;
		}

		var sequence = ++deliveryCheckSequence;
		setDeliveryStatus(address.prefix, 'checking', config.deliveryChecking || 'Checking delivery availability...');
		var payload = new URLSearchParams();
		payload.set('action', 'sultan_check_delivery');
		payload.set('nonce', config.deliveryNonce || '');
		Object.keys(address).forEach(function (key) {
			if ('prefix' !== key) {
				payload.set(key, address[key]);
			}
		});

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: payload.toString()
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Delivery check failed');
				}
				return response.json();
			})
			.then(function (result) {
				if (sequence !== deliveryCheckSequence) {
					return;
				}
				var data = result && result.data ? result.data : {};
				if ('available' === data.status) {
					var message = String(config.deliveryAvailable || 'Delivery available: %1$s km, %2$s delivery fee.')
						.replace('%1$s', data.distance || '')
						.replace('%2$s', data.fee || '');
					setDeliveryStatus(address.prefix, 'available', message);
				} else if ('incomplete' === data.status) {
					setDeliveryStatus(address.prefix, 'incomplete', config.deliveryIncomplete || 'Enter your full delivery address to check delivery.');
				} else if ('error' === data.status) {
					setDeliveryStatus(address.prefix, 'error', data.message || config.deliveryError || 'Delivery could not be checked right now.');
				} else {
					setDeliveryStatus(address.prefix, 'unavailable', data.message || config.deliveryUnavailable || 'Delivery is not available to this address.');
				}
			})
			.catch(function () {
				if (sequence === deliveryCheckSequence) {
					setDeliveryStatus(address.prefix, 'error', config.deliveryError || 'Delivery could not be checked right now.');
				}
			});
	}

	function scheduleDeliveryCheck(delay) {
		deliveryInitialCheckStarted = true;
		window.clearTimeout(deliveryCheckTimer);
		deliveryCheckTimer = window.setTimeout(checkDeliveryAvailability, delay || 700);
	}

	function isDeliveryAddressField(element) {
		if (!element || !(element.name || element.id)) {
			return false;
		}
		return /(?:billing|shipping|calc_shipping)[_-](?:address[_-]1|address[_-]2|postcode|city|state|country)$/.test(element.name || element.id);
	}

	document.addEventListener('click', function (event) {
		var disabledCheckout = event.target.closest('.sultan-minimum-disabled');
		if (disabledCheckout) {
			event.preventDefault();
			event.stopPropagation();
		}
	}, true);

	document.addEventListener('input', function (event) {
		if (isDeliveryAddressField(event.target)) {
			scheduleDeliveryCheck(900);
		}
	});

	document.addEventListener('change', function (event) {
		if (isDeliveryAddressField(event.target)) {
			scheduleDeliveryCheck(350);
		}
	});

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
		var config = window.sultanCheckoutConfig;
		if (!deliveryInitialCheckStarted && config && (config.isCheckout || config.isCart)) {
			var hasAddressFields = document.querySelector('#shipping_address_1, [name="shipping_address_1"], #shipping-address-1, [name="shipping-address-1"], #billing_address_1, [name="billing_address_1"], #billing-address-1, [name="billing-address-1"], #calc_shipping_postcode, [name="calc_shipping_postcode"]');
			if (hasAddressFields) {
				var initialAddress = getDeliveryAddress(config);
				if (initialAddress.address_1 && initialAddress.postcode && initialAddress.city && initialAddress.country) {
					deliveryInitialCheckStarted = true;
					scheduleDeliveryCheck(300);
				} else if (!document.getElementById('sultan-delivery-status')) {
					setDeliveryStatus(initialAddress.prefix, 'incomplete', config.deliveryIncomplete || 'Enter your full delivery address to check availability.');
				}
			}
		}
	}

	document.body.addEventListener('updated_checkout', function () {
		updateMinimumOrder();
		scheduleDeliveryCheck(300);
	});
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
