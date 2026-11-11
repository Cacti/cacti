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
 * byte-identical between the plugins that use it. It wires the DOM behaviour
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

	/* Locate the live card element for an id, used to re-resolve a reference that
	 * an overlapping replacement may have detached. */
	function findCard(grid, adapter, id) {
		var match = null;
		if (id == null) {
			return null;
		}
		grid.querySelectorAll(cardSel(adapter)).forEach(function(card) {
			if (match === null && cardId(card, adapter) === id) {
				match = card;
			}
		});
		return match;
	}

	/* Keep the documented data-* contract (data-expandable / data-expanded) in
	 * sync with the classes the CSS clipping and serialize() rely on, so a host
	 * may drive card state through either representation. Called whenever a card
	 * is (re)bound: initial, added, or refreshed. */
	function normalizeState(card) {
		var expandable = card.getAttribute('data-expandable');
		if (expandable === '1' || expandable === 'true') {
			card.classList.add('ccCardExpandable');
		} else if (expandable != null) {
			card.classList.remove('ccCardExpandable');
		}
		if (card.classList.contains('ccCardExpandable')) {
			card.setAttribute('data-expandable', '1');
		}

		var expanded = card.getAttribute('data-expanded');
		if (expanded === '1' || expanded === 'true') {
			card.classList.add('ccCardExpanded');
		} else if (expanded != null) {
			card.classList.remove('ccCardExpanded');
		}
		card.setAttribute('data-expanded', card.classList.contains('ccCardExpanded') ? '1' : '0');
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

	/* The insertion reference for a drop: the first card that follows the pointer
	 * in reading order. Resolve the pointer's row before comparing horizontal
	 * centres so dragging over a later card in a row no longer snaps the
	 * insertion point back to the start of that row. */
	function dragReference(grid, adapter, x, y) {
		var cards = Array.prototype.slice.call(grid.querySelectorAll(cardSel(adapter) + ':not(.ccCardDragging)'));
		var rowMatch = null;   // first card in the pointer's row whose centre is past x
		var belowMatch = null; // first card that begins in a row below the pointer

		cards.forEach(function(card) {
			var box = card.getBoundingClientRect();
			if (y >= box.top && y <= box.bottom) {
				// Pointer is within this card's row band: compare horizontally.
				if (rowMatch === null && x < box.left + box.width / 2) {
					rowMatch = card;
				}
			} else if (y < box.top && belowMatch === null) {
				// This card starts in a row below the pointer; it ends the
				// pointer's row when nothing in that row is past the pointer.
				belowMatch = card;
			}
		});

		return rowMatch || belowMatch;
	}

	/* Decide, mirroring normalizeState(), whether a fresh card will be
	 * expandable, so a preserved toggle is never forced onto a card that cannot
	 * expand (which would leave normalizeState() marking it ccCardExpanded and
	 * persisting expanded:true for a non-expandable card). */
	function willExpand(card) {
		var expandable = card.getAttribute('data-expandable');
		if (expandable === '1' || expandable === 'true') {
			return true;
		}
		if (expandable != null) {
			return false;
		}
		return card.classList.contains('ccCardExpandable');
	}

	/* Swap a card's markup in place (after refresh/add) and re-bind it.
	 *
	 * capturedExpanded (optional) is the card's expanded state when an internal
	 * refresh/resize request started. When it is supplied and the live card has
	 * since been toggled by the user, the incoming markup reflects the stale
	 * captured state, so the live state is carried onto the fresh markup to avoid
	 * reverting that toggle - but only when the fresh card is still expandable.
	 * When it is omitted (direct callers) or the state is unchanged, the host
	 * markup's own data-expanded stays authoritative (the normalizeState
	 * contract, so a host may still drive expansion through returned markup). */
	function replaceCard(grid, adapter, card, html, capturedExpanded) {
		var tmp = document.createElement('div');
		tmp.innerHTML = (html || '').trim();
		var fresh = tmp.querySelector(cardSel(adapter)) || tmp.firstElementChild;
		if (!fresh) {
			return null;
		}
		// Overlapping refresh/resize responses may reference a card that an
		// earlier response already swapped out, leaving the captured node
		// detached. Re-resolve the live element by id so the latest response
		// replaces what is currently in the grid instead of throwing on (and
		// losing the update to) a detached node.
		var target = card;
		if (!grid.contains(target)) {
			target = findCard(grid, adapter, cardId(card, adapter));
		}
		if (!target || !target.parentNode) {
			return null;
		}
		if (capturedExpanded != null) {
			var liveExpanded = target.classList.contains('ccCardExpanded');
			if (liveExpanded !== capturedExpanded && willExpand(fresh)) {
				fresh.setAttribute('data-expanded', liveExpanded ? '1' : '0');
			}
		}
		target.parentNode.replaceChild(fresh, target);
		bindCard(grid, adapter, fresh);
		if (typeof adapter.applyTheme === 'function') {
			adapter.applyTheme(grid);
		}
		return fresh;
	}

	/* A per-card-id request generation shared by refresh and resize so a slow
	 * response cannot overwrite the markup of a newer request for the same card
	 * (for example a refresh that lands after a later grow). The most recently
	 * started request for an id wins; earlier in-flight responses are discarded. */
	function startRequest(grid, id) {
		if (!grid.__ccReq) {
			grid.__ccReq = {};
		}
		grid.__ccReq[id] = (grid.__ccReq[id] || 0) + 1;
		return grid.__ccReq[id];
	}

	function requestSuperseded(grid, id, token) {
		return !!grid.__ccReq && grid.__ccReq[id] !== token;
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
		var token = startRequest(grid, id);
		Promise.resolve(adapter.fetchCard(id, state)).then(function(html) {
			// A newer refresh/resize for this card superseded us; discard the
			// stale markup instead of reverting the card to an older state.
			if (html != null && !requestSuperseded(grid, id, token)) {
				replaceCard(grid, adapter, card, html, state.expanded);
			}
		}).catch(function() { /* fire-and-forget: the host surfaces adapter errors */ });
	}

	function removeCard(grid, adapter, card) {
		var id = cardId(card, adapter);
		// Only drop the live card and persist the new layout once the host has
		// confirmed the deletion. Re-resolve by id so an overlapping refresh that
		// swapped the node does not leave a detached reference behind.
		var commit = function() {
			var target = grid.contains(card) ? card : findCard(grid, adapter, id);
			if (target && target.parentNode) {
				target.parentNode.removeChild(target);
				saveLayout(grid, adapter);
			}
		};
		if (typeof adapter.onRemoveCard === 'function') {
			Promise.resolve(adapter.onRemoveCard(id, card)).then(commit).catch(function() {
				/* rejected: leave the card and its saved layout untouched */
			});
		} else {
			commit();
		}
	}

	function resizeCard(grid, adapter, card, dir) {
		var id = cardId(card, adapter);
		if (typeof adapter.onResize === 'function') {
			var token = startRequest(grid, id);
			var capturedExpanded = card.classList.contains('ccCardExpanded');
			Promise.resolve(adapter.onResize(id, dir, card)).then(function(html) {
				// A newer refresh/resize for this card has superseded us; leave
				// its markup and layout in place rather than reverting them.
				if (requestSuperseded(grid, id, token)) {
					return;
				}
				if (typeof html === 'string') {
					replaceCard(grid, adapter, card, html, capturedExpanded);
				}
				saveLayout(grid, adapter);
			}).catch(function() { /* fire-and-forget: the host surfaces adapter errors */ });
		} else {
			saveLayout(grid, adapter);
		}
	}

	/* After expand/collapse hides the button that was activated (see cards.css),
	 * move keyboard focus to the now-visible counterpart so the control is not
	 * lost when the card is toggled from the keyboard. */
	function refocusToolCounterpart(card, tool, counterpart) {
		if (document.activeElement !== tool) {
			return;
		}
		var next = card.querySelector('.ccTool[data-tool="' + counterpart + '"]');
		if (next) {
			next.focus();
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

		normalizeState(card);

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

		card.addEventListener('dragstart', function(event) {
			// Only start a reorder when the card itself is the armed drag source.
			// Native drags bubbling up from links or images in the body (or any
			// drag while the handle is hidden via .ccNoDrag, which leaves the card
			// unarmed) must not enter reorder mode.
			if (!card.draggable || event.target !== card) {
				return;
			}
			dragging = card;
			card.classList.add('ccCardDragging');
		});
		card.addEventListener('dragend', function() {
			// Native HTML drag-and-drop suppresses the mouseup that would
			// otherwise disarm the card, so disarm here on every drag end.
			disarm();
			if (dragging !== card) {
				return;
			}
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
					refocusToolCounterpart(card, tool, 'collapse');
					saveLayout(grid, adapter);
					break;
				case 'collapse':
					card.classList.remove('ccCardExpanded');
					card.setAttribute('data-expanded', '0');
					refocusToolCounterpart(card, tool, 'expand');
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
						// A second add of the same card can resolve while the first
						// is still pending (the option stays selectable until the
						// debounced catalogue sync), so skip ids already on the grid
						// to avoid appending a duplicate the server then dedupes.
						if (fresh && findCard(grid, adapter, cardId(fresh, adapter)) === null) {
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
