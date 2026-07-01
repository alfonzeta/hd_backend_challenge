/**
 * Vending Machine — frontend controller
 *
 * Layered like the backend, but for the UI:
 *   api      → fetch wrappers for every endpoint (no DOM access)
 *   renderer → pure DOM mutation from state data (no fetch calls)
 *   animator → visual choreography (Web Animations API + CSS classes)
 *   sound    → tiny WebAudio synth for tactile feedback (optional, mutable)
 *
 * Orchestrators (handleXxx) sequence these layers; no business logic here —
 * the backend is the single source of truth, the UI only reflects it.
 */

'use strict';

// ---------------------------------------------------------------------------
// Config & utilities
// ---------------------------------------------------------------------------

const PRODUCTS = ['WATER', 'JUICE', 'SODA'];
const COIN_DENOMS = [5, 10, 25, 100];
const MAX_LANE_UNITS = 5;

const prefersReducedMotion =
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const $ = (id) => document.getElementById(id);

/** @param {number} cents */
const formatCredit = (cents) => (cents / 100).toFixed(2);

/** @param {number} cents */
const coinLabel = (cents) => (cents < 100 ? `${cents}¢` : `€${cents / 100}`);

/** @param {number} cents */
const isSilver = (cents) => cents < 100;

const sleep = (ms) =>
  new Promise((r) => setTimeout(r, prefersReducedMotion ? 0 : ms));

/**
 * Adds a class, waits for its animation to end, then removes it.
 * @param {HTMLElement} el
 * @param {string} cls
 */
function animateClass(el, cls) {
  return new Promise((resolve) => {
    el.classList.remove(cls);
    void el.offsetWidth; // restart if re-triggered
    el.classList.add(cls);
    el.addEventListener(
      'animationend',
      () => {
        el.classList.remove(cls);
        resolve();
      },
      { once: true },
    );
  });
}

// ---------------------------------------------------------------------------
// Stage scaling — the machine is designed at a fixed size and scaled to fit
// ---------------------------------------------------------------------------

const stage = {
  scale: 1,

  fit() {
    const wrap = $('stage-wrap');
    const el = $('stage');
    const styles = getComputedStyle(document.documentElement);
    const designW = parseFloat(styles.getPropertyValue('--stage-w'));
    const designH = parseFloat(styles.getPropertyValue('--stage-h'));

    this.scale = Math.min(
      wrap.clientWidth / designW,
      wrap.clientHeight / designH,
      1.1,
    );
    el.style.transform = `scale(${this.scale})`;
  },

  observe() {
    this.fit();
    new ResizeObserver(() => this.fit()).observe($('stage-wrap'));
  },
};

// ---------------------------------------------------------------------------
// Sound — short synthesised blips (no assets), respects mute toggle
// ---------------------------------------------------------------------------

const sound = {
  ctx: null,
  muted: localStorage.getItem('vm-muted') === 'true',

  ensureContext() {
    if (!this.ctx) {
      const Ctx = window.AudioContext ?? window.webkitAudioContext;
      if (Ctx) this.ctx = new Ctx();
    }
    if (this.ctx?.state === 'suspended') this.ctx.resume();
    return this.ctx;
  },

  /**
   * @param {number} freq
   * @param {number} duration seconds
   * @param {OscillatorType} type
   * @param {number} gain
   */
  blip(freq, duration = 0.08, type = 'square', gain = 0.04) {
    if (this.muted || prefersReducedMotion) return;
    const ctx = this.ensureContext();
    if (!ctx) return;

    const osc = ctx.createOscillator();
    const amp = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    amp.gain.setValueAtTime(gain, ctx.currentTime);
    amp.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duration);
    osc.connect(amp).connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + duration);
  },

  coin() {
    this.blip(1180, 0.06, 'triangle', 0.06);
    setTimeout(() => this.blip(1560, 0.09, 'triangle', 0.05), 55);
  },

  keypress() { this.blip(340, 0.05, 'square', 0.035); },

  vendMotor() {
    if (this.muted || prefersReducedMotion) return;
    let n = 0;
    const tick = setInterval(() => {
      this.blip(90 + (n % 3) * 12, 0.045, 'sawtooth', 0.028);
      if (++n >= 9) clearInterval(tick);
    }, 90);
  },

  thud() { this.blip(70, 0.16, 'sine', 0.09); },

  change() {
    [0, 90, 180].forEach((delay, i) =>
      setTimeout(() => this.blip(1250 + i * 180, 0.07, 'triangle', 0.05), delay),
    );
  },

  error() {
    this.blip(160, 0.18, 'square', 0.05);
    setTimeout(() => this.blip(120, 0.24, 'square', 0.05), 140);
  },

  toggleMute() {
    this.muted = !this.muted;
    localStorage.setItem('vm-muted', String(this.muted));
    return this.muted;
  },
};

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

  async returnCoins() {
    const res = await fetch('/return', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error ?? 'Return failed');
    return data;
  },

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
// Renderer (pure DOM mutation)
// ---------------------------------------------------------------------------

/** Last known machine state, used for affordability hints. */
let lastState = null;
let messageTimer = 0;

const renderer = {
  /** @param {Record<string,{price:number,stock:number}>} products */
  renderSlots(products) {
    for (const selector of PRODUCTS) {
      const info = products[selector];
      if (!info) continue;

      const lane = $(`products-${selector}`);
      lane.innerHTML = '';

      const visible = Math.min(info.stock, MAX_LANE_UNITS);
      for (let i = 0; i < visible; i++) {
        const unit = document.createElement('div');
        unit.className = `product product--${selector}`;
        lane.appendChild(unit);
      }
      for (let i = visible; i < MAX_LANE_UNITS; i++) {
        const ghost = document.createElement('div');
        ghost.className = `product product--${selector} product--ghost`;
        lane.appendChild(ghost);
      }

      renderer.renderCoil(selector);

      $(`price-${selector}`).textContent = `€${formatCredit(info.price)}`;
      $(`soldout-${selector}`).hidden = info.stock > 0;
      $(`key-${selector}`).disabled = info.stock === 0;
    }
    renderer.refreshAffordability();
  },

  /** Rebuilds the spiral rings behind the products of one lane. */
  renderCoil(selector) {
    const coil = $(`coil-${selector}`);
    coil.innerHTML = '';
    const RINGS = 7;
    for (let i = 0; i < RINGS; i++) {
      const ring = document.createElement('i');
      ring.className = 'coil-ring';
      ring.style.left = `${i * 48}px`;
      coil.appendChild(ring);
    }
  },

  /** @param {number} cents */
  renderCredit(cents) {
    $('led-credit').textContent = formatCredit(cents);
    if (lastState) lastState.balance = cents;
    renderer.refreshAffordability();
  },

  /** Pulses keypad LEDs for products the current credit can buy. */
  refreshAffordability() {
    if (!lastState) return;
    for (const selector of PRODUCTS) {
      const info = lastState.products[selector];
      const btn = $(`key-${selector}`);
      const affordable =
        !!info && info.stock > 0 && lastState.balance >= info.price;
      btn.classList.toggle('keypad__btn--ready', affordable);
    }
  },

  /**
   * Shows a message on the LED. Long messages scroll like a real ticker.
   * @param {string} text
   * @param {{error?: boolean, holdMs?: number}} [opts]
   */
  message(text, opts = {}) {
    const el = $('led-message');
    clearTimeout(messageTimer);
    el.classList.remove('led__message--error', 'led__message--scroll');
    void el.offsetWidth;

    el.textContent = text.toUpperCase();
    if (opts.error) el.classList.add('led__message--error');
    if (text.length > 22) el.classList.add('led__message--scroll');

    if (opts.holdMs) {
      messageTimer = setTimeout(() => renderer.idleMessage(), opts.holdMs);
    }
  },

  /** Default resting message, depends on whether there is credit. */
  idleMessage() {
    const credit = lastState?.balance ?? 0;
    renderer.message(credit > 0 ? 'SELECT PRODUCT' : 'INSERT COIN');
  },

  /** @param {Record<string,number>} change */
  renderChangeLamp(change) {
    const low = (change[5] ?? 0) < 2 && (change[10] ?? 0) < 2;
    $('lamp-change').dataset.on = String(low);
  },

  /** Adds a vended product pill inside the delivery bin. */
  addBinItem(selector) {
    const cavity = $('bin-cavity');
    const item = document.createElement('div');
    item.className = 'bin-item';
    const shape = document.createElement('div');
    shape.className = `product product--${selector}`;
    shape.style.transform = 'scale(0.9)';
    item.appendChild(shape);
    cavity.appendChild(item);
  },

  /** Drops coins into the return cup. */
  addCupCoins(coins) {
    const cavity = $('cup-cavity');
    coins.forEach((cents, i) => {
      const coin = document.createElement('div');
      coin.className = `cup-coin ${isSilver(cents) ? 'cup-coin--silver' : 'cup-coin--gold'}`;
      coin.textContent = coinLabel(cents);
      coin.style.animationDelay = `${i * 110}ms`;
      cavity.appendChild(coin);
    });
  },

  renderAll(state) {
    lastState = state;
    renderer.renderSlots(state.products);
    renderer.renderCredit(state.balance);
    renderer.renderChangeLamp(state.change ?? {});
  },
};

// ---------------------------------------------------------------------------
// Animator (Web Animations API + CSS class choreography)
// ---------------------------------------------------------------------------

const animator = {
  /**
   * Arcs a coin from the pocket button into the coin slot.
   * Runs in the fixed fx-layer, so viewport coordinates are used as-is.
   * @param {HTMLElement} fromBtn
   * @param {number} denomination
   */
  async coinFlight(fromBtn, denomination) {
    if (prefersReducedMotion) return;

    const layer = $('fx-layer');
    const slot = $('coin-slot');
    const from = fromBtn.getBoundingClientRect();
    const to = slot.getBoundingClientRect();

    const coin = document.createElement('div');
    coin.className = `fly-coin ${isSilver(denomination) ? 'fly-coin--silver' : 'fly-coin--gold'}`;
    coin.textContent = coinLabel(denomination);
    coin.style.left = `${from.left + from.width / 2 - 17}px`;
    coin.style.top = `${from.top + from.height / 2 - 17}px`;
    layer.appendChild(coin);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);

    // Arc: overshoot upward at midpoint, spin, then shrink into the slit.
    const anim = coin.animate(
      [
        { transform: 'translate(0, 0) rotate(0deg) scale(1)', opacity: 1 },
        {
          transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 110}px) rotate(340deg) scale(0.95)`,
          opacity: 1,
          offset: 0.55,
        },
        {
          transform: `translate(${dx}px, ${dy}px) rotate(680deg) scaleX(0.12) scaleY(0.7)`,
          opacity: 0.9,
        },
      ],
      { duration: 620, easing: 'cubic-bezier(0.45, 0, 0.55, 1)', fill: 'forwards' },
    );

    await anim.finished.catch(() => {});
    coin.remove();
    animateClass($('coin-slot'), 'coin-entry__slot--feed');
  },

  /**
   * Vends the front unit of a lane: the coil turns, the product wobbles
   * forward and tips over the shelf edge, falling through the drop zone.
   * @param {string} selector
   */
  async vend(selector) {
    const slotEl = $(`slot-${selector}`);
    const lane = $(`products-${selector}`);
    const units = lane.querySelectorAll('.product:not(.product--ghost)');
    if (units.length === 0) return;

    const unit = units[0];
    unit.classList.add('product--vending');

    // Lift the whole slot above its siblings so the falling product is not
    // painted behind the shelves below it.
    slotEl.style.zIndex = '10';

    sound.vendMotor();

    // Phase 1 — coil rotation pushes the product forward with a judder.
    slotEl.classList.add('slot--vending');
    if (!prefersReducedMotion) {
      await unit.animate(
        [
          { transform: 'translateX(0) rotate(0deg)' },
          { transform: 'translateX(3px) rotate(-2deg)', offset: 0.2 },
          { transform: 'translateX(6px) rotate(1.5deg)', offset: 0.4 },
          { transform: 'translateX(10px) rotate(-1.5deg)', offset: 0.6 },
          { transform: 'translateX(14px) rotate(1deg)', offset: 0.8 },
          { transform: 'translateX(17px) rotate(0deg)' },
        ],
        { duration: 850, easing: 'linear', fill: 'forwards' },
      ).finished.catch(() => {});
    }
    slotEl.classList.remove('slot--vending');

    // Phase 2 — tip over the shelf edge and free-fall inside the glass.
    // Distances are computed in visual px and divided by the stage scale
    // because the transform applies in the element's (scaled) local space.
    const glass = $('window-glass');
    const unitRect = unit.getBoundingClientRect();
    const glassRect = glass.getBoundingClientRect();
    const fallPx = (glassRect.bottom - unitRect.bottom + 10) / stage.scale;

    if (!prefersReducedMotion) {
      await unit.animate(
        [
          { transform: 'translateX(17px) translateY(0) rotate(0deg)', opacity: 1 },
          {
            transform: 'translateX(24px) translateY(12px) rotate(24deg)',
            opacity: 1,
            offset: 0.22,
          },
          {
            transform: `translateX(30px) translateY(${fallPx * 0.65}px) rotate(72deg)`,
            opacity: 1,
            offset: 0.7,
          },
          {
            transform: `translateX(32px) translateY(${fallPx}px) rotate(88deg)`,
            opacity: 0,
          },
        ],
        {
          duration: 640,
          easing: 'cubic-bezier(0.5, 0, 0.9, 0.6)',
          fill: 'forwards',
        },
      ).finished.catch(() => {});
    }

    unit.remove();
    sound.thud();
  },

  /**
   * Product appears in the bin with a bounce. The flap stays open until
   * the user collects the item (you can't see through a closed flap).
   */
  async binReceive(selector) {
    renderer.addBinItem(selector);
    $('bin').classList.add('bin--open');
    await sleep(650);
  },

  /** Turns the coin-return lever. */
  leverTurn() {
    return animateClass($('btn-return'), 'coin-entry__return--turn');
  },

  /** Coins clatter into the cup. */
  async cupReceive(coins) {
    if (coins.length === 0) return;
    renderer.addCupCoins(coins);
    sound.change();
    await animateClass($('cup'), 'cup--flash');
  },

  /** Machine shake + red LED for failures. */
  async error(message) {
    sound.error();
    renderer.message(message, { error: true, holdMs: 3600 });
    $('led-credit').classList.add('led__credit--error');
    await animateClass($('machine'), 'machine--shake');
    $('led-credit').classList.remove('led__credit--error');
  },

  serviceFlash() {
    return animateClass($('machine'), 'machine--service-flash');
  },
};

// ---------------------------------------------------------------------------
// Busy lock — one request at a time, mirrors a real machine's single motor
// ---------------------------------------------------------------------------

let busy = false;

function setBusy(value) {
  busy = value;
  $('machine').classList.toggle('machine--busy', value);
  document.body.classList.toggle('body--busy', value);
}

// ---------------------------------------------------------------------------
// Orchestrators
// ---------------------------------------------------------------------------

async function handleInsertCoin(btn, denomination) {
  if (busy) return;
  setBusy(true);
  try {
    sound.coin();
    await animator.coinFlight(btn, denomination);
    const data = await api.insertCoin(denomination);
    renderer.renderCredit(data.balance);
    renderer.idleMessage();
  } catch (err) {
    await animator.error(err.message);
  } finally {
    setBusy(false);
  }
}

async function handlePurchase(selector) {
  if (busy) return;
  setBusy(true);
  sound.keypress();
  try {
    const data = await api.purchase(selector);

    renderer.message('VENDING…');
    await animator.vend(selector);
    await animator.binReceive(selector);

    renderer.renderCredit(data.balance);
    if (data.change.length > 0) {
      renderer.message('TAKE YOUR CHANGE', { holdMs: 3600 });
      await animator.cupReceive(data.change);
    } else {
      renderer.message('ENJOY!', { holdMs: 3000 });
    }

    const state = await api.getState();
    renderer.renderAll(state);
  } catch (err) {
    await animator.error(err.message);
  } finally {
    setBusy(false);
  }
}

async function handleReturn() {
  if (busy) return;
  setBusy(true);
  try {
    await animator.leverTurn();
    const data = await api.returnCoins();
    renderer.renderCredit(0);

    if (data.coins.length > 0) {
      renderer.message('COINS RETURNED', { holdMs: 3000 });
      await animator.cupReceive(data.coins);
    } else {
      renderer.message('NOTHING TO RETURN', { holdMs: 2600 });
    }
  } catch (err) {
    await animator.error(err.message);
  } finally {
    setBusy(false);
  }
}

async function handleService() {
  const applyBtn = $('tech-apply');
  applyBtn.disabled = true;
  try {
    const products = Object.fromEntries(
      PRODUCTS.map((p) => [p, parseInt($(`svc-${p}`).value, 10) || 0]),
    );
    const change = Object.fromEntries(
      COIN_DENOMS.map((d) => [d, parseInt($(`svc-coin-${d}`).value, 10) || 0]),
    );

    await api.service({ products, change });

    closeTechPanel();
    await animator.serviceFlash();

    const state = await api.getState();
    renderer.renderAll(state);
    renderer.message('SERVICE COMPLETE', { holdMs: 3000 });
  } catch (err) {
    renderer.message(err.message, { error: true, holdMs: 4000 });
  } finally {
    applyBtn.disabled = false;
  }
}

/** Collect items from the bin (clears it and lets the flap swing shut). */
async function handleBinCollect() {
  const bin = $('bin');
  const cavity = $('bin-cavity');
  if (cavity.children.length === 0) return;
  await animateClass(bin, 'bin--nudge');
  cavity.innerHTML = '';
  bin.classList.remove('bin--open');
}

/** Collect coins from the cup. */
function handleCupCollect() {
  const cavity = $('cup-cavity');
  if (cavity.children.length === 0) return;
  sound.coin();
  cavity.innerHTML = '';
}

// ---------------------------------------------------------------------------
// Technician drawer
// ---------------------------------------------------------------------------

async function openTechPanel() {
  $('tech-panel').setAttribute('aria-hidden', 'false');
  try {
    const state = await api.getState();
    for (const p of PRODUCTS) {
      $(`svc-${p}`).value = state.products[p]?.stock ?? 5;
    }
    for (const d of COIN_DENOMS) {
      $(`svc-coin-${d}`).value = state.change[d] ?? 10;
    }
  } catch {
    // keep defaults — the drawer is still usable
  }
}

function closeTechPanel() {
  $('tech-panel').setAttribute('aria-hidden', 'true');
}

// ---------------------------------------------------------------------------
// Event wiring
// ---------------------------------------------------------------------------

function wireEvents() {
  document.querySelectorAll('.pcoin').forEach((btn) => {
    btn.addEventListener('click', () =>
      handleInsertCoin(btn, parseInt(btn.dataset.coin, 10)),
    );
  });

  document.querySelectorAll('.keypad__btn').forEach((btn) => {
    btn.addEventListener('click', () => handlePurchase(btn.dataset.product));
  });

  $('btn-return').addEventListener('click', handleReturn);
  $('bin-flap').addEventListener('click', handleBinCollect);
  $('cup').addEventListener('click', handleCupCollect);

  $('tech-key').addEventListener('click', openTechPanel);
  $('tech-close').addEventListener('click', closeTechPanel);
  $('tech-apply').addEventListener('click', handleService);

  document.addEventListener('click', (e) => {
    const panel = $('tech-panel');
    if (
      panel.getAttribute('aria-hidden') === 'false' &&
      !panel.contains(e.target) &&
      !$('tech-key').contains(e.target)
    ) {
      closeTechPanel();
    }
  });

  const muteBtn = $('btn-mute');
  muteBtn.dataset.muted = String(sound.muted);
  muteBtn.addEventListener('click', () => {
    muteBtn.dataset.muted = String(sound.toggleMute());
  });
}

// ---------------------------------------------------------------------------
// Initialisation
// ---------------------------------------------------------------------------

async function init() {
  stage.observe();
  wireEvents();

  try {
    const state = await api.getState();
    renderer.renderAll(state);
    renderer.idleMessage();
  } catch {
    renderer.message('OUT OF ORDER', { error: true });
    $('led-credit').textContent = '--.--';
  }
}

init();
