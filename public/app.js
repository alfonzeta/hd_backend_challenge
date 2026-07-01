/**
 * Vending Machine — frontend controller
 *
 * Three concerns, clearly separated:
 *   api      → fetch wrappers for every endpoint
 *   renderer → pure DOM mutation from state data
 *   animator → CSS class / DOM choreography
 *
 * Orchestrators (handleXxx) sequence these three;
 * no business logic lives here.
 */

'use strict';

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

/** @param {number} cents */
function formatMoney(cents) {
  return `€\u00a0${(cents / 100).toFixed(2)}`;
}

/** @param {number} cents */
function coinLabel(cents) {
  if (cents < 100) return `${cents}¢`;
  return `€${(cents / 100).toFixed(2)}`;
}

/** @param {number} cents */
function isSilverCoin(cents) {
  return cents < 100;
}

/**
 * Wait for an animation to end on an element, then remove the class.
 * @param {HTMLElement} el
 * @param {string} cls
 * @returns {Promise<void>}
 */
function animateClass(el, cls) {
  return new Promise((resolve) => {
    el.classList.add(cls);
    const done = () => {
      el.classList.remove(cls);
      el.removeEventListener('animationend', done);
      resolve();
    };
    el.addEventListener('animationend', done, { once: true });
  });
}

/** Stagger-delay helper: resolves after `ms` ms. */
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------------------
// API
// ---------------------------------------------------------------------------

const api = {
  /** @returns {Promise<{balance:number, products:Record<string,{price:number,stock:number}>, change:Record<string,number>}>} */
  async getState() {
    const res = await fetch('/state');
    if (!res.ok) throw new Error(await res.text());
    return res.json();
  },

  /**
   * @param {number} coin cents
   * @returns {Promise<{balance:number}>}
   */
  async insertCoin(coin) {
    const res = await fetch('/coins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ coin }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Insert coin failed');
    return data;
  },

  /**
   * @param {string} product
   * @returns {Promise<{product:string, change:number[], balance:number}>}
   */
  async purchase(product) {
    const res = await fetch('/purchase', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Purchase failed');
    return data;
  },

  /** @returns {Promise<{coins:number[]}>} */
  async returnCoins() {
    const res = await fetch('/return', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Return failed');
    return data;
  },

  /**
   * @param {{ products: Record<string,number>, change: Record<string,number> }} payload
   * @returns {Promise<{status:string}>}
   */
  async service(payload) {
    const res = await fetch('/service', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Service failed');
    return data;
  },
};

// ---------------------------------------------------------------------------
// Renderer  (pure DOM mutation — no fetch calls inside)
// ---------------------------------------------------------------------------

const MAX_STOCK_DISPLAY = 5;

const renderer = {
  /**
   * Renders product unit shapes in each shelf based on current stock.
   * @param {Record<string,{price:number,stock:number}>} products
   */
  renderShelves(products) {
    for (const [selector, info] of Object.entries(products)) {
      const shelf = document.getElementById(`shelf-${selector}`);
      if (!shelf) continue;

      shelf.innerHTML = '';

      const displayed = Math.min(info.stock, MAX_STOCK_DISPLAY);
      const ghosts    = MAX_STOCK_DISPLAY - displayed;

      for (let i = 0; i < displayed; i++) {
        const unit = document.createElement('div');
        unit.className = `product-unit product-unit--${selector}`;
        unit.dataset.product = selector;
        unit.dataset.index = String(i);
        shelf.appendChild(unit);
      }

      for (let i = 0; i < ghosts; i++) {
        const ghost = document.createElement('div');
        ghost.className = `product-unit product-unit--${selector} product-unit--ghost`;
        shelf.appendChild(ghost);
      }

      // Update selector button stock + price
      const btn = document.getElementById(`btn-${selector}`);
      if (btn) {
        btn.dataset.stock = String(info.stock);
        btn.disabled = info.stock === 0;
      }

      const priceEl = document.getElementById(`price-${selector}`);
      if (priceEl) {
        priceEl.textContent = formatMoney(info.price);
      }
    }
  },

  /** @param {number} cents */
  renderBalance(cents) {
    const el = document.getElementById('display-balance');
    if (el) el.textContent = formatMoney(cents);
  },

  /** @param {string} message */
  renderMessage(message) {
    const el = document.getElementById('display-message');
    if (el) el.textContent = message;
  },

  /** Clears the message after a delay. */
  clearMessageAfter(ms = 3000) {
    setTimeout(() => renderer.renderMessage(''), ms);
  },

  /**
   * Renders the change bank coin counts.
   * @param {Record<string,number>} change  keys are cent values as strings
   */
  renderChangeBank(change) {
    const container = document.getElementById('change-bank-coins');
    if (!container) return;
    container.innerHTML = '';

    const denoms = [5, 10, 25, 100];
    for (const d of denoms) {
      const count = change[d] ?? 0;
      const row   = document.createElement('div');
      row.className = 'change-bank__row';

      const denomEl = document.createElement('span');
      denomEl.className = 'change-bank__denom';
      denomEl.textContent = coinLabel(d);

      const countEl = document.createElement('span');
      countEl.className = `change-bank__count${count <= 2 ? ' change-bank__count--low' : ''}`;
      countEl.textContent = `×${count}`;

      row.append(denomEl, countEl);
      container.appendChild(row);
    }
  },

  /** Clears the dispensing tray. */
  clearTray() {
    const el = document.getElementById('tray-contents');
    if (!el) return;
    el.innerHTML = '<span class="tray__empty">— empty —</span>';
  },

  /**
   * Renders a vended product pill + change coins in the tray.
   * @param {string}   product
   * @param {number[]} changeCoins
   */
  renderTrayDispense(product, changeCoins) {
    const el = document.getElementById('tray-contents');
    if (!el) return;
    el.innerHTML = '';

    const pill = document.createElement('div');
    pill.className = `tray-product tray-product--${product}`;
    pill.textContent = product;
    el.appendChild(pill);

    changeCoins.forEach((cents, i) => {
      const coin = document.createElement('div');
      coin.className = `tray-coin ${isSilverCoin(cents) ? 'tray-coin--silver' : 'tray-coin--gold'}`;
      coin.textContent = coinLabel(cents);
      coin.style.animationDelay = `${100 + i * 100}ms`;
      el.appendChild(coin);
    });
  },

  /**
   * Renders returned coins in the tray.
   * @param {number[]} coins
   */
  renderTrayReturn(coins) {
    const el = document.getElementById('tray-contents');
    if (!el) return;
    el.innerHTML = '';

    if (coins.length === 0) {
      el.innerHTML = '<span class="tray__empty">— nothing to return —</span>';
      return;
    }

    coins.forEach((cents, i) => {
      const coin = document.createElement('div');
      coin.className = `tray-coin ${isSilverCoin(cents) ? 'tray-coin--silver' : 'tray-coin--gold'}`;
      coin.textContent = coinLabel(cents);
      coin.style.animationDelay = `${i * 80}ms`;
      el.appendChild(coin);
    });
  },

  /** Full re-render from a state object. */
  renderAll(state) {
    renderer.renderBalance(state.balance);
    renderer.renderShelves(state.products);
  },
};

// ---------------------------------------------------------------------------
// Animator  (CSS class choreography + DOM effects)
// ---------------------------------------------------------------------------

const animator = {
  /**
   * Spawns a flying coin that moves from the clicked button toward the slot.
   * @param {HTMLElement} btn
   * @param {number}      denomination
   */
  coinInsert(btn, denomination) {
    const layer  = document.getElementById('coin-animation-layer');
    const slot   = document.querySelector('.coin-slot__opening');
    if (!layer || !slot) return;

    const btnRect  = btn.getBoundingClientRect();
    const slotRect = slot.getBoundingClientRect();

    const coin = document.createElement('div');
    coin.className = `flying-coin ${isSilverCoin(denomination) ? 'flying-coin--silver' : 'flying-coin--gold'}`;
    coin.textContent = coinLabel(denomination);

    // Start at the coin button center
    coin.style.left = `${btnRect.left + btnRect.width / 2 - 10}px`;
    coin.style.top  = `${btnRect.top  + btnRect.height / 2 - 10}px`;

    // CSS custom properties drive the target
    const dx = slotRect.left + slotRect.width / 2 - (btnRect.left + btnRect.width / 2);
    const dy = slotRect.top  + slotRect.height / 2 - (btnRect.top  + btnRect.height / 2);
    coin.style.setProperty('--dx', `${dx}px`);
    coin.style.setProperty('--dy', `${dy}px`);

    // Override animation to use translate toward slot
    coin.style.animation = 'none';
    coin.style.transition = 'transform 0.32s cubic-bezier(0.4,0,0.8,0.6), opacity 0.32s';

    layer.appendChild(coin);

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        coin.style.transform = `translate(${dx}px, ${dy}px) scale(0.4)`;
        coin.style.opacity   = '0';
      });
    });

    setTimeout(() => coin.remove(), 400);
  },

  /**
   * Drops the front unit from a shelf with a fall animation.
   * @param {string} product
   * @returns {Promise<void>}
   */
  async productFall(product) {
    const shelf = document.getElementById(`shelf-${product}`);
    if (!shelf) return;

    // The "front" unit is the last non-ghost unit
    const units = Array.from(shelf.querySelectorAll(`.product-unit--${product}:not(.product-unit--ghost)`));
    if (units.length === 0) return;

    const frontUnit = units[units.length - 1];
    await animateClass(frontUnit, 'product-unit--falling');
  },

  /**
   * Shakes the machine and flashes the display red.
   * @param {string} message
   */
  async error(message) {
    const machine = document.getElementById('machine');
    const balance = document.getElementById('display-balance');

    renderer.renderMessage(message);
    renderer.clearMessageAfter(4000);

    if (balance) {
      balance.classList.add('display__value--error');
      await animateClass(machine, 'machine--shake');
      balance.classList.remove('display__value--error');
    } else {
      await animateClass(machine, 'machine--shake');
    }
  },

  /** Triggers the tray pulse. */
  trayPulse() {
    const tray = document.querySelector('.machine__tray');
    if (!tray) return;
    tray.classList.remove('machine__tray--pulse');
    // Force reflow to restart animation
    void tray.offsetWidth;
    tray.classList.add('machine__tray--pulse');
    tray.addEventListener('animationend', () => tray.classList.remove('machine__tray--pulse'), { once: true });
  },

  /** Green flash on the machine shell. */
  async serviceFlash() {
    const machine = document.getElementById('machine');
    await animateClass(machine, 'machine--service-flash');
  },
};

// ---------------------------------------------------------------------------
// In-flight lock — disables all interactive controls during a request
// ---------------------------------------------------------------------------

function setBusy(busy) {
  const machine = document.getElementById('machine');
  if (busy) {
    machine.classList.add('machine--busy');
  } else {
    machine.classList.remove('machine--busy');
  }
}

// ---------------------------------------------------------------------------
// Orchestrators
// ---------------------------------------------------------------------------

async function handleInsertCoin(btn, denomination) {
  setBusy(true);
  try {
    animator.coinInsert(btn, denomination);
    const data = await api.insertCoin(denomination);
    renderer.renderBalance(data.balance);
    renderer.renderMessage('');
  } catch (err) {
    await animator.error(err.message);
  } finally {
    setBusy(false);
  }
}

async function handlePurchase(product) {
  setBusy(true);
  renderer.clearTray();
  try {
    const data = await api.purchase(product);

    // Animate fall, then update shelves + tray
    await animator.productFall(product);
    animator.trayPulse();

    renderer.renderBalance(data.balance);
    renderer.renderTrayDispense(data.product, data.change);
    renderer.renderMessage('ENJOY!');
    renderer.clearMessageAfter(3000);

    // Refresh full state to update stock
    const state = await api.getState();
    renderer.renderShelves(state.products);
  } catch (err) {
    await animator.error(err.message);
  } finally {
    setBusy(false);
  }
}

async function handleReturn() {
  setBusy(true);
  try {
    const data = await api.returnCoins();
    renderer.renderBalance(0);
    renderer.renderTrayReturn(data.coins);

    if (data.coins.length > 0) {
      animator.trayPulse();
      renderer.renderMessage('COINS RETURNED');
    } else {
      renderer.renderMessage('NOTHING TO RETURN');
    }
    renderer.clearMessageAfter(3000);
  } catch (err) {
    await animator.error(err.message);
  } finally {
    setBusy(false);
  }
}

async function handleService() {
  const applyBtn = document.getElementById('tech-apply');
  applyBtn.disabled = true;

  try {
    const products = {
      WATER: parseInt(document.getElementById('svc-WATER').value, 10) || 0,
      JUICE: parseInt(document.getElementById('svc-JUICE').value, 10) || 0,
      SODA:  parseInt(document.getElementById('svc-SODA').value,  10) || 0,
    };
    const change = {
      5:   parseInt(document.getElementById('svc-coin-5').value,   10) || 0,
      10:  parseInt(document.getElementById('svc-coin-10').value,  10) || 0,
      25:  parseInt(document.getElementById('svc-coin-25').value,  10) || 0,
      100: parseInt(document.getElementById('svc-coin-100').value, 10) || 0,
    };

    await api.service({ products, change });

    closeTechPanel();
    await animator.serviceFlash();

    const state = await api.getState();
    renderer.renderAll(state);
    renderer.renderMessage('SERVICE COMPLETE');
    renderer.clearMessageAfter(3000);
  } catch (err) {
    renderer.renderMessage(err.message);
    renderer.clearMessageAfter(4000);
  } finally {
    applyBtn.disabled = false;
  }
}

// ---------------------------------------------------------------------------
// Technician panel
// ---------------------------------------------------------------------------

async function openTechPanel() {
  const panel = document.getElementById('tech-panel');
  panel.setAttribute('aria-hidden', 'false');

  try {
    const state = await api.getState();
    document.getElementById('svc-WATER').value    = state.products['WATER']?.stock ?? 5;
    document.getElementById('svc-JUICE').value    = state.products['JUICE']?.stock ?? 5;
    document.getElementById('svc-SODA').value     = state.products['SODA']?.stock  ?? 5;
    document.getElementById('svc-coin-5').value   = state.change[5]   ?? 10;
    document.getElementById('svc-coin-10').value  = state.change[10]  ?? 10;
    document.getElementById('svc-coin-25').value  = state.change[25]  ?? 10;
    document.getElementById('svc-coin-100').value = state.change[100] ?? 10;
  } catch (_) {
    // silently ignore — defaults remain
  }
}

function closeTechPanel() {
  const panel = document.getElementById('tech-panel');
  panel.setAttribute('aria-hidden', 'true');
}

// ---------------------------------------------------------------------------
// Event wiring
// ---------------------------------------------------------------------------

function wireEvents() {
  // Coin buttons
  document.querySelectorAll('.coin-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const denomination = parseInt(btn.dataset.coin, 10);
      handleInsertCoin(btn, denomination);
    });
  });

  // Product selector buttons
  document.querySelectorAll('.selector-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const product = btn.dataset.product;
      handlePurchase(product);
    });
  });

  // Return coin button
  document.getElementById('btn-return').addEventListener('click', handleReturn);

  // Technician key toggle
  document.getElementById('tech-key').addEventListener('click', openTechPanel);
  document.getElementById('tech-close').addEventListener('click', closeTechPanel);
  document.getElementById('tech-apply').addEventListener('click', handleService);

  // Close panel when clicking outside
  document.addEventListener('click', (e) => {
    const panel = document.getElementById('tech-panel');
    if (
      panel.getAttribute('aria-hidden') === 'false' &&
      !panel.contains(e.target) &&
      !document.getElementById('tech-key').contains(e.target)
    ) {
      closeTechPanel();
    }
  });
}

// ---------------------------------------------------------------------------
// Initialisation
// ---------------------------------------------------------------------------

async function init() {
  try {
    const state = await api.getState();
    renderer.renderAll(state);
    renderer.clearTray();
  } catch (err) {
    renderer.renderMessage('CONNECTION ERROR');
    const balance = document.getElementById('display-balance');
    if (balance) balance.textContent = '— —';
  }

  wireEvents();
}

init();
