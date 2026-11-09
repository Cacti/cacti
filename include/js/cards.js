/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * cacti-cards vX.Y - shared "card chrome" controller (interaction layer).
 *
 * Bundled per-plugin with no cross-plugin or core dependency and kept
 * byte-identical between the plugins that use it. It wires the DOM behaviou
 * for a .ccGrid of .ccCard elements (see cards.css for the HTML contract):
 * keyboard-accessible drag/drop reordering, the per-card tool buttons
 * (expand/collapse, grow/shrink, maximize, refresh, remove), an optional
 * "add card" control, and debounced layout persistence. Everything that is
 * plugin-specific (fetching a card, persisting layout, the add catalogue,
 * maximize, resize) is delegated to an adapter the host supplies.
 *
 * Usage (host, from an external nonce'd init or its own JS):
 *   CactiCards.init(document.getElementById('my_grid'), {
 *     fetchCard:     function(id, state) { return Promise<outerHTML string>; },
 *     onLayoutChange:function(state)     { ... persist {order, cards} ... },
 *     onAddCard:     function(id)        { return Promise<outerHTML string>; },
 *     onRemoveCard:  function(id)        { return Promise|any; },
 *     onResize:      function(id, dir, cardEl) { return Promise|any; }, // 'grow'|'shrink'
 *     maximize:      function(id, mode, cardEl) { ... },                // mode from data-maximize
 *     applyTheme:    function(gridEl)    { ... set --cc-* vars ... }
 *   });
 *
 * The adapter is optional; every hook has a safe no-op default. init() is
 * idempotent per grid element.
 */

window.CactiCards = window.CactiCards || (function() {
	'use strict';

	var dragging = null;

	function cardSel(adapter) {
		return adapter.cardSelector || '.ccCard';
	}

	function cardId(card, adapter) {
		return card.getAttribute(adapter.idAttr || 'data-card');
	}

	/* Collect the current order and per-card state for persistence. */
	function serialize(grid, adapter) {
		var order = [];
		var cards = {};

		grid.querySelectorAll(cardSel(adapter)).forEach(function(card) {
			var id = cardId(card, adapter);
			if (id == null) {
				return;
			}
			order.push(id);
			cards[id] = {
				expanded: card.classList.contains('ccCardExpanded'),
				height:   Number.parseInt(card.getAttribute('data-height') || '1', 10)
			};
		});

		return { order: order, cards: cards };
	}

	/* Debounce layout saves so a burst of reorders coalesces into one call. */
	function saveLayout(grid, adapter) {
		if (typeof adapter.onLayoutChange !== 'function') {
			return;
		}
		if (grid.__ccSaveTimer) {
			clearTimeout(grid.__ccSaveTimer);
		}
		grid.__ccSaveTimer = setTimeout(function() {
			grid.__ccSaveTimer = null;
			adapter.onLayoutChange(serialize(grid, adapter));
		}, 250);
	}

	/* The card the pointer is currently over, used as the drop insertion point. */
	function dragReference(grid, adapter, x, y) {
		var cards = Array.prototype.slice.call(grid.querySelectorAll(cardSel(adapter) + ':not(.ccCardDragging)'));
		var ref = null;

		cards.forEach(function(card) {
			var box = card.getBoundingClientRect();
			// Insert before the first card whose centre is past the pointer.
			if (y < box.top + box.height / 2 || (y < box.bottom && x < box.left + box.width / 2)) {
				if (ref === null) {
					ref = card;
				}
			}
		});

		return ref;
	}

	/* Swap a card's markup in place (after refresh/add) and re-bind it. */
	function replaceCard(grid, adapter, card, html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = (html || '').trim();
		var fresh = tmp.querySelector(cardSel(adapter)) || tmp.firstElementChild;
		if (!fresh) {
			return null;
		}
		card.parentNode.replaceChild(fresh, card);
		bindCard(grid, adapter, fresh);
		if (typeof adapter.applyTheme === 'function') {
			adapter.applyTheme(grid);
		}
		return fresh;
	}

	function refreshCard(grid, adapter, card) {
		var id = cardId(card, adapter);
		// Hosts that inject into an inner body (rather than replacing the whole
		// card) provide onRefresh and keep ownership of the swap.
		if (typeof adapter.onRefresh === 'function') {
			adapter.onRefresh(id, card);
			return;
		}
		if (typeof adapter.fetchCard !== 'function') {
			return;
		}
		var state = {
			expanded: card.classList.contains('ccCardExpanded'),
			height:   Number.parseInt(card.getAttribute('data-height') || '1', 10)
		};
		Promise.resolve(adapter.fetchCard(id, state)).then(function(html) {
			if (html != null) {
				replaceCard(grid, adapter, card, html);
			}
		}).catch(function() { /* fire-and-forget: the host surfaces adapter errors */ });
	}

	function removeCard(grid, adapter, card) {
		var id = cardId(card, adapter);
		if (typeof adapter.onRemoveCard === 'function') {
			adapter.onRemoveCard(id, card);
		}
		card.parentNode.removeChild(card);
		saveLayout(grid, adapter);
	}

	function resizeCard(grid, adapter, card, dir) {
		var id = cardId(card, adapter);
		if (typeof adapter.onResize === 'function') {
			Promise.resolve(adapter.onResize(id, dir, card)).then(function(html) {
				if (typeof html === 'string') {
					replaceCard(grid, adapter, card, html);
				}
				saveLayout(grid, adapter);
			}).catch(function() { /* fire-and-forget: the host surfaces adapter errors */ });
		} else {
			saveLayout(grid, adapter);
		}
	}

	/* Wire a single card: its drag handle (mouse + touch + keyboard) and drag
	 * events. Handle interactions are DELEGATED from the persistent card element
	 * so they survive hosts that replace the header markup on refresh/reload. */
	function bindCard(grid, adapter, card) {
		if (card.__ccBound) {
			return;
		}
		card.__ccBound = true;

		var onHandle = function(target) {
			return target && target.closest && target.closest('.ccDrag');
		};

		// Arm HTML5 drag only from the handle, and disarm on release when no drag
		// began so selecting content elsewhere can't drag the card.
		var disarm = function() { card.draggable = false; };
		card.addEventListener('mousedown', function(event) {
			if (!onHandle(event.target)) {
				return;
			}
			card.draggable = true;
			document.addEventListener('mouseup', disarm, { once: true });
		});
		card.addEventListener('touchstart', function(event) {
			if (!onHandle(event.target)) {
				return;
			}
			card.draggable = true;
			document.addEventListener('touchend', disarm, { once: true });
			document.addEventListener('touchcancel', disarm, { once: true });
		}, { passive: true });

		// Keyboard-accessible reordering with the arrow keys.
		card.addEventListener('keydown', function(event) {
			if (!onHandle(event.target)) {
				return;
			}
			var back = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
			var fwd  = event.key === 'ArrowRight' || event.key === 'ArrowDown';
			if (!back && !fwd) {
				return;
			}
			event.preventDefault();
			if (back && card.previousElementSibling) {
				grid.insertBefore(card, card.previousElementSibling);
			} else if (fwd && card.nextElementSibling) {
				grid.insertBefore(card.nextElementSibling, card);
			} else {
				return;
			}
			var handle = card.querySelector('.ccDrag');
			if (handle) {
				handle.focus();
			}
			saveLayout(grid, adapter);
		});

		card.addEventListener('dragstart', function() {
			dragging = card;
			card.classList.add('ccCardDragging');
		});
		card.addEventListener('dragend', function() {
			card.classList.remove('ccCardDragging');
			dragging = null;
			saveLayout(grid, adapter);
		});
	}

	function init(grid, adapter) {
		if (!grid || grid.__ccInit) {
			return;
		}
		grid.__ccInit = true;
		adapter = adapter || {};

		if (typeof adapter.applyTheme === 'function') {
			adapter.applyTheme(grid);
		}

		grid.querySelectorAll(cardSel(adapter)).forEach(function(card) {
			bindCard(grid, adapter, card);
		});

		grid.addEventListener('dragover', function(event) {
			if (!dragging || dragging.parentNode !== grid) {
				return;
			}
			event.preventDefault();
			if (event.dataTransfer) {
				event.dataTransfer.dropEffect = 'move';
			}
			var before = dragReference(grid, adapter, event.clientX, event.clientY);
			if (before == null) {
				grid.appendChild(dragging);
			} else if (before !== dragging) {
				grid.insertBefore(dragging, before);
			}
		});

		// Per-card tool buttons bubble up to the grid.
		grid.addEventListener('click', function(event) {
			var tool = event.target.closest ? event.target.closest('.ccTool') : null;
			if (!tool || !grid.contains(tool)) {
				return;
			}
			var card = tool.closest(cardSel(adapter));
			if (!card) {
				return;
			}
			// Tools may be rendered as anchors (e.g. remove); never navigate.
			event.preventDefault();
			switch (tool.getAttribute('data-tool')) {
				case 'expand':
					card.classList.add('ccCardExpanded');
					card.setAttribute('data-expanded', '1');
					saveLayout(grid, adapter);
					break;
				case 'collapse':
					card.classList.remove('ccCardExpanded');
					card.setAttribute('data-expanded', '0');
					saveLayout(grid, adapter);
					break;
				case 'grow':   resizeCard(grid, adapter, card, 'grow'); break;
				case 'shrink': resizeCard(grid, adapter, card, 'shrink'); break;
				case 'maximize':
					if (typeof adapter.maximize === 'function') {
						adapter.maximize(cardId(card, adapter), card.getAttribute('data-maximize') || 'body', card);
					}
					break;
				case 'refresh': refreshCard(grid, adapter, card); break;
				case 'remove':  removeCard(grid, adapter, card); break;
			}
		});

		// Optional "add card" control referenced by adapter.addControl (an element or id).
		var add = adapter.addControl;
		if (typeof add === 'string') {
			add = document.getElementById(add);
		}
		if (add) {
			add.addEventListener('change', function() {
				var id = this.value;
				this.value = '';
				if (id && typeof adapter.onAddCard === 'function') {
					Promise.resolve(adapter.onAddCard(id)).then(function(html) {
						if (html == null) {
							return;
						}
						var tmp = document.createElement('div');
						tmp.innerHTML = String(html).trim();
						var fresh = tmp.querySelector(cardSel(adapter)) || tmp.firstElementChild;
						if (fresh) {
							grid.appendChild(fresh);
							bindCard(grid, adapter, fresh);
							if (typeof adapter.applyTheme === 'function') {
								adapter.applyTheme(grid);
							}
							saveLayout(grid, adapter);
						}
					}).catch(function() { /* fire-and-forget: the host surfaces adapter errors */ });
				}
			});
		}
	}

	return {
		init: init,
		serialize: serialize,
		replaceCard: replaceCard,
		bindCard: bindCard
	};
})();
