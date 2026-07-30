// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Local SocialCert — frontend helpers.
 *
 * Responsibilities:
 * - Lightweight event delegation utility for click actions.
 * - Handlers for: opening external links, copying generated text, and AI “typewriter” effect.
 * - Triggering the AI request and streaming the response into the DOM.
 * - Public API: { init, register, runAiHandler, typewriter }.
 *
 * Notes:
 * - This module is loaded via $PAGE->requires->js_call_amd('local_socialcert/actions', 'init').
 * - The HTML is rendered by the Mustache template and provides the data-* hooks.
 *
 * Security model for the AI card output:
 * - Remote content (the AI service reply and any provider supplied message) is ALWAYS inserted
 *   as text nodes. It never reaches innerHTML, so markup in it cannot create elements and cannot
 *   register event handlers.
 * - Plugin owned messages (the credits / license / generic lang strings, which legitimately carry
 *   a link to the Datacurso shop) travel through a separate rendering path that rebuilds the DOM
 *   from an allowlist: text nodes plus <a> elements with an http(s) href. Nothing else survives.
 * - The two paths are selected by the ORIGIN of the string, never by sniffing its content.
 */

import {get_string as getString} from 'core/str';
import Ajax from 'core/ajax';

/* ============================================================================
 * Action registry
 * ==========================================================================*/

/**
 * Action registry mapping: actionName -> handler(event, element).
 * Handlers are registered once in {@link init} and invoked via delegation.
 * @type {Map<string, Function>}
 */
const registry = new Map();

/* ============================================================================
 * Event delegation
 * ==========================================================================*/

/**
 * Simple event delegation helper.
 * Listens on a root element and, when the event target or an ancestor matches
 * the selector, calls the provided handler with the matched element.
 *
 * @param {HTMLElement} root   Root container where the listener is attached.
 * @param {string} selector    CSS selector to match using Element.closest().
 * @param {string} type        Event type (e.g., "click").
 * @param {(ev: Event, el: HTMLElement) => void} handler  Callback invoked with the event and the matched element.
 * @returns {void}
 */
function on(root, selector, type, handler) {
  root.addEventListener(type, (ev) => {
    const origin = /** @type {Element} */(ev.target instanceof Element ? ev.target : root);
    const target = origin.closest(selector);
    if (!target || !root.contains(target)) {
      return;
    }
    handler(ev, /** @type {HTMLElement} */(target));
  });
}

/* ============================================================================
 * Button labels (localised)
 * ==========================================================================*/

/**
 * Monotonic token per button, used to discard stale asynchronous label updates.
 * @type {WeakMap<HTMLElement, number>}
 */
const labelTokens = new WeakMap();

/**
 * Bumps and returns the label token of a button.
 *
 * @param {HTMLElement} btn Button being relabelled.
 * @returns {number} The new token value.
 */
function nextLabelToken(btn) {
  const token = (labelTokens.get(btn) || 0) + 1;
  labelTokens.set(btn, token);
  return token;
}

/**
 * Replaces a button label with a localised local_socialcert string.
 *
 * get_string() returns a promise, so it must be awaited: assigning the promise itself to
 * textContent would print "[object Promise]". A token guard discards a late resolution when a
 * newer label change was requested in the meantime.
 *
 * @param {HTMLElement} btn Button whose label must be replaced.
 * @param {string} key Lang string key inside the local_socialcert component.
 * @returns {Promise<void>} Resolves once the label has been applied or discarded.
 */
async function setButtonLabel(btn, key) {
  const token = nextLabelToken(btn);
  let label;

  try {
    label = await getString(key, 'local_socialcert');
  } catch (e) {
    // Keep the current label when the string cannot be fetched.
    return;
  }

  if (labelTokens.get(btn) !== token) {
    return;
  }

  btn.textContent = label;
}

/**
 * Restores a previously captured plain text label, cancelling any pending async label update.
 *
 * @param {HTMLElement} btn Button whose label must be restored.
 * @param {string} label Label captured before the asynchronous work started.
 * @returns {void}
 */
function restoreButtonLabel(btn, label) {
  nextLabelToken(btn);
  btn.textContent = label;
}

/* ============================================================================
 * Handlers: open link / copy to clipboard
 * ==========================================================================*/

/**
 * Opens a link in a new tab (using element href or data-url) and briefly sets aria-busy for feedback.
 *
 * @param {MouseEvent} ev
 * @param {HTMLElement} el Element with an href or data-url attribute.
 * @returns {void}
 */
function handleOpenLink(ev, el) {

  ev.preventDefault();

  el.setAttribute('aria-busy', 'true');

  const url = el.getAttribute('href') || el.dataset.url;

  if (url) {
    window.open(url, '_blank', 'noopener');
  }

  setTimeout(() => el.removeAttribute('aria-busy'), 300);
}

/**
 * Selector of the copy button rendered by the Mustache template.
 * @type {string}
 */
const COPY_BUTTON_SELECTOR = '[data-action="copy-html"]';

/**
 * Character used as the transient "copied" confirmation mark.
 * @type {string}
 */
const COPY_CONFIRMATION_MARK = '✔';

/**
 * Shows a transient confirmation mark inside the copy button and then restores its
 * original children (the icon markup) untouched.
 *
 * The previous implementation stored innerHTML and restored it through textContent, which
 * would have printed the raw SVG source. Detaching and re-attaching the real nodes keeps the
 * icon intact and avoids any HTML string round-trip.
 *
 * @param {HTMLElement} btn Copy button to decorate.
 * @param {number} [delayMs] How long the confirmation stays visible, in milliseconds.
 * @returns {void}
 */
function showCopyConfirmation(btn, delayMs) {
  const originalChildren = document.createDocumentFragment();
  while (btn.firstChild) {
    originalChildren.appendChild(btn.firstChild);
  }

  btn.textContent = COPY_CONFIRMATION_MARK;

  setTimeout(() => {
    btn.textContent = '';
    btn.appendChild(originalChildren);
  }, delayMs || 1200);
}

/**
 * Copies the content of a target node to the clipboard.
 * - data-target: selector pointing to the container (e.g., "#ai-response").
 * - data-copy: "text" (default) or "html".
 *   - In both modes, temporary caret spans (.lsc-caret) are removed before copying.
 *
 * @param {MouseEvent} _ev
 * @param {HTMLElement} el Button element with data-target (and optional data-copy).
 * @returns {void}
 */
async function handleCopyHtml(_ev, el) {
  const sel = el.dataset.target || '';
  const node = sel ? document.querySelector(sel) : null;
  if (!node) {
    return;
  }

  const clone = node.cloneNode(true);
  const carets = clone.querySelectorAll('.lsc-caret');
  carets.forEach(c => c.remove());

  const mode = (el.dataset.copy === 'html') ? 'html' : 'text';
  const content = (mode === 'html')
    ? clone.innerHTML
    : (clone.innerText || clone.textContent || '');

  navigator.clipboard.writeText(content)
    .then(() => {

      // The delegated element IS the copy button, so looking for a descendant never matched it.
      const btn = el.matches(COPY_BUTTON_SELECTOR)
        ? el
        : el.querySelector(COPY_BUTTON_SELECTOR);

      if (!btn) {
 return;
}

      showCopyConfirmation(btn);
      return;
    })
    .catch(() => {
      // Clipboard permission denied or unavailable: nothing to report to the user.
    });
}

/* ============================================================================
 * Typewriter (stream mock) utilities
 * ==========================================================================*/

/**
 * Inserts a visual caret at the end of the target element and returns it.
 * Used to simulate live typing.
 *
 * @param {HTMLElement} el Target container where the caret is appended.
 * @returns {HTMLSpanElement} The inserted <span> with class "lsc-caret".
 */
function addCaret(el) {
  const caret = document.createElement('span');
  caret.className = 'lsc-caret';
  caret.textContent = ' ';
  el.appendChild(caret);
  return caret;
}

/**
 * Removes the visual caret (if present) from a given element.
 *
 * @param {HTMLElement} el Container to clean up.
 * @returns {void}
 */
function removeCaret(el) {
  const caret = el.querySelector('.lsc-caret');
  if (caret) {
 caret.remove();
}
}


/**
 * Maps an error message returned by the backend to a plugin lang key.
 *
 * Detection is case-insensitive and based on simple text matches:
 * - Contains "insufficient ai credits"  -> returns `errors[0]` (e.g., "tokenserror")
 * - Contains "your license is not allowed" or "manage credits" -> returns `errors[1]` (e.g., "licenseerror")
 * - Otherwise -> returns `errors[2]` (e.g., "genericerror")
 *
 * @param {string} message - Error text returned by the service (may include HTML).
 * @param {string[]} errors - Array of lang keys in the order:
 *   [0] = key for insufficient credits (e.g., "tokenserror"),
 *   [1] = key for not-allowed license (e.g., "licenseerror"),
 *   [2] = generic key (e.g., "genericerror").
 * @returns {string} The corresponding lang key based on the message content.
 */
function mapErrorToLangKey(message, errors) {
  const msg = String(message || '').toLowerCase();

  if (msg.includes('insufficient ai credits')) {
    return errors[0];
  }

  if (
    msg.includes('your license is not allowed') ||
    msg.includes('manage credits')
  ) {
    return errors[1];
  }

  return errors[2];
}

/**
 * Tracks active streams per target so we can stop/replace them.
 * @type {WeakMap<HTMLElement, {stop: Function}>}
 */
const streams = new WeakMap();

/* ============================================================================
 * Safe rendering of plugin owned messages
 * ==========================================================================*/

/**
 * URL protocols accepted for links rendered from plugin owned messages.
 * @type {string[]}
 */
const ALLOWED_LINK_PROTOCOLS = ['http:', 'https:'];

/**
 * Elements whose whole subtree is discarded while rebuilding a plugin owned message.
 * Their text content is intentionally NOT preserved.
 * @type {string[]}
 */
const DISCARDED_ELEMENTS = ['SCRIPT', 'STYLE', 'TEMPLATE', 'IFRAME', 'OBJECT', 'EMBED', 'NOSCRIPT'];

/**
 * Builds a brand new anchor for a link found in a plugin owned message.
 *
 * Only the href is carried over, and only when it resolves to an http(s) URL. Every other
 * attribute of the parsed anchor (including any event handler) is dropped, because the returned
 * element is created from scratch instead of being imported from the parsed tree.
 *
 * @param {Element} source Anchor element coming from the inert parsed document.
 * @returns {HTMLElement} A new <a> element, or a plain <span> when the href is not allowed.
 */
function buildSafeAnchor(source) {
  const href = source.getAttribute('href') || '';
  let url = null;

  try {
    url = new URL(href, window.location.href);
  } catch (e) {
    url = null;
  }

  if (!url || ALLOWED_LINK_PROTOCOLS.indexOf(url.protocol) === -1) {
    return document.createElement('span');
  }

  const anchor = document.createElement('a');
  anchor.setAttribute('href', url.href);
  anchor.setAttribute('target', '_blank');
  anchor.setAttribute('rel', 'noopener noreferrer');

  return anchor;
}

/**
 * Copies an inert node tree into a live destination keeping only an allowlist of nodes.
 *
 * Text nodes are recreated with document.createTextNode(). Anchors are recreated by
 * {@link buildSafeAnchor}. Any other element is skipped and only its textual content is kept,
 * except for the elements listed in {@link DISCARDED_ELEMENTS}, which are dropped entirely.
 * No node from the parsed document is ever adopted into the live document.
 *
 * @param {Node} source Node belonging to the inert parsed document.
 * @param {Node} destination Live node (element or fragment) receiving the rebuilt copy.
 * @returns {void}
 */
function appendAllowedNodes(source, destination) {
  Array.prototype.forEach.call(source.childNodes, (child) => {
    if (child.nodeType === Node.TEXT_NODE) {
      destination.appendChild(document.createTextNode(child.nodeValue));
      return;
    }

    if (child.nodeType !== Node.ELEMENT_NODE) {
      return;
    }

    const tag = String(child.tagName || '').toUpperCase();

    if (DISCARDED_ELEMENTS.indexOf(tag) !== -1) {
      return;
    }

    if (tag === 'A' && child.namespaceURI === 'http://www.w3.org/1999/xhtml') {
      const anchor = buildSafeAnchor(child);
      appendAllowedNodes(child, anchor);
      destination.appendChild(anchor);
      return;
    }

    if (tag === 'BR') {
      destination.appendChild(document.createElement('br'));
      return;
    }

    // Unknown element: keep its readable content, throw the element away.
    appendAllowedNodes(child, destination);
  });
}

/**
 * Renders a plugin owned message (a local_socialcert lang string) into an element.
 *
 * This path exists so the credits and license errors keep their clickable link to the shop.
 * It MUST only ever receive strings produced by this plugin; remote content goes through
 * {@link typewriter}, which is text only.
 *
 * The message is parsed with DOMParser into an inert document (no browsing context, so no
 * script runs and no resource is fetched there) and then rebuilt node by node from the
 * allowlist, so the live DOM only receives nodes created by this module.
 *
 * @param {HTMLElement} el Target element that receives the message.
 * @param {string} message Plugin owned message, possibly containing a single <a> link.
 * @returns {void}
 */
function renderPluginMessage(el, message) {
  const parsed = new DOMParser().parseFromString(String(message || ''), 'text/html');
  const fragment = document.createDocumentFragment();

  appendAllowedNodes(parsed.body, fragment);

  el.textContent = '';
  el.appendChild(fragment);
}

/**
 * Streams text into an element in "char" or "word" units with a configurable delay.
 *
 * The text is always inserted as TEXT, never as HTML: every unit is appended with
 * insertAdjacentText(), so markup contained in the value is displayed literally and can never
 * create elements nor register event handlers. This is the only path used for remote content.
 *
 * @param {HTMLElement} el   Target element to receive the text.
 * @param {string} text      Full text to stream. Treated as untrusted plain text.
 * @param {'char'|'word'} mode Unit size used while streaming.
 * @param {number} speedMs   Interval between units (ms).
 * @returns {{stop: Function, done: Promise<void>}} Control handle with a stop() method and a completion promise.
 */
export function typewriter(el, text, mode, speedMs) {
  const source = (text === null || text === undefined) ? '' : String(text);
  const caret = addCaret(el);
  const units = mode === 'char' ? source.split('') : source.split(/\s+/);
  let i = 0;
  let stopped = false;
  el.textContent = '';
  el.appendChild(caret);

  const done = new Promise((resolve) => {
    const timer = setInterval(() => {
      if (stopped) {
        clearInterval(timer);
        removeCaret(el);
        resolve();
        return;
      }
      if (i >= units.length) {
        clearInterval(timer);
        removeCaret(el);
        resolve();
        return;
      }
      const chunk = units[i++];
      caret.insertAdjacentText('beforebegin', mode === 'word' ? (chunk + ' ') : chunk);
    }, Math.max(10, speedMs || 30));
    streams.set(el, {stop: () => {
 stopped = true;
}});
  });

  return {stop() {
 const s = streams.get(el); if (s) {
 s.stop(); streams.delete(el);
}
}, done};
}

/* ============================================================================
 * AI request
 * ==========================================================================*/

/**
 * Fetches an AI-generated response for the given context using the
 * `local_socialcert_get_ai_response` web service.
 *
 * @function ai_response
 * @async
 * @param {string} certname   Certificate (or student) name used in the prompt.
 * @param {string} course     Course name used in the prompt.
 * @param {string} org        Issuing organization name.
 * @param {string} socialmedia Target social network (e.g., "LinkedIn").
 * @param {string[]} errorarray - Array of lang keys in the order:
 * @param {number} cmid - Course module ID.
 * @returns {Promise<{fulltext: string, done: boolean, plugintext: boolean}>} Resolves to the text to display,
 *   whether the generation succeeded, and whether the text is a plugin owned message (plugintext) or
 *   remote content that must be rendered as plain text.
 * @throws {SyntaxError} If the backend JSON is invalid.
 * @throws {Error} If the AJAX call fails (also reported via Notification.exception).
 *
 * @example
 * ai_response("Analytics Certificate", "BUEN DATA", "LinkedIn")
 *   .then(reply => console.log("AI reply:", reply))
 *   .catch(err => console.error("AI error:", err));
 */
function ai_response(certname, course, org, socialmedia, errorarray, cmid) {

  return new Promise((resolve) => {
    Ajax.call([{
      methodname: 'local_socialcert_get_ai_response',
      args: {
        body: {
          certname: certname,
          course: course,
          org: org,
          socialmedia: socialmedia,
        },
        cmid: cmid,
      },
    }])[0].then((response) => {
      if (response.json) {
        const parsed = JSON.parse(response.json);
        // Remote content: rendered as plain text only.
        return resolve({fulltext: parsed.reply, done: true, plugintext: false});
      } else {
        // The provider already returns a clear, localized message (with retry time) for the rate
        // limit, so show it as-is instead of the generic fallback. It still comes from the remote
        // provider, so it is rendered as plain text.
        if (response.errorcode === 'error_ratelimit_exceeded' && response.message) {
          return resolve({fulltext: response.message, done: false, plugintext: false});
        }
        // Plugin owned lang string (credits / license / generic): may contain the shop link.
        const errormsg = mapErrorToLangKey(response.message, errorarray);
        return resolve({fulltext: errormsg, done: false, plugintext: true});
      }
    }).catch(() => {
      return resolve({fulltext: errorarray[2], done: false, plugintext: true});
    });
  });
}

/* ============================================================================
 * Action: run AI
 * ==========================================================================*/

/**
 * Starts/stops the “AI” streaming flow.
 * Reads data attributes from the trigger button:
 *  - data-target: CSS selector for the output node.
 *  - data-mode: "char" | "word" (streaming unit).
 *  - data-speed: interval in ms.
 *
 * Also manages a loader, ARIA states, and reveals the Copy button when done.
 *
 * @param {number} cmid
 * @returns {(ev: MouseEvent, btn: HTMLElement) => void}
 */
export function runAiHandler(cmid) {
  return function(ev, btn) {
    ev.preventDefault();
    const sel = btn.dataset.target;
    let target = null;
    if (sel) {
      target = document.querySelector(sel);
    } else {
      const wrap = btn.closest('.lsc-response-wrap');
      target = wrap ? wrap.querySelector('.lsc-response') : null;
    }
    if (!target) {
 return;
}

    if (streams.has(target)) {
      const s = streams.get(target);
      if (s && s.stop) {
 s.stop();
}
      btn.disabled = false;
      setButtonLabel(btn, 'airesponsebtn');
      return;
    }

    const mode = (btn.dataset.mode === 'char') ? 'char' : 'word';
    const speed = parseInt(btn.dataset.speed || '40', 10);
    const certname = btn.dataset.certname || '';
    const course = btn.dataset.course || '';
    const org = btn.dataset.org || '';
    const socialmedia = btn.dataset.socialmedia || '';
    // Const id_servicio = btn.dataset.id_servicio || '';
    const original = btn.textContent;
    btn.disabled = true;
    setButtonLabel(btn, 'generating');
    target.setAttribute('aria-busy', 'true');
    target.setAttribute('role', 'status');

    const loader = document.getElementById('ai-card');
    const copyBtn = document.getElementById('copyBtn');
    const errorLicense = btn.dataset.errorlicense;
    const errorCredits = btn.dataset.errorcredits;
    const errorGeneric = btn.dataset.errorgeneric;
    const errorarray = [
      errorCredits,
      errorLicense,
      errorGeneric
    ];
    let streamtext = '';
    // True only when the text to display is a lang string owned by this plugin.
    let plugintext = false;

    copyBtn.hidden = true;

    ai_response(certname, course, org, socialmedia, errorarray, cmid).then((response) => {
      streamtext = response.fulltext;
      plugintext = response.plugintext === true;
      if (response.done) {
 copyBtn.hidden = false;
}
      return;
    }).catch(() => {
      streamtext = errorGeneric;
      plugintext = true;
    }).finally(() => {
      loader.classList.add('hidden');
      loader.setAttribute('aria-busy', 'false');

      if (plugintext) {
        // Plugin owned message: rebuilt from the allowlist so its shop link stays clickable.
        renderPluginMessage(target, streamtext);
        btn.disabled = false;
        restoreButtonLabel(btn, original);
        target.removeAttribute('aria-busy');
        streams.delete(target);
        return;
      }

      // Remote content: streamed as plain text, never as HTML.
      const stream = typewriter(target, streamtext, mode, speed);
      stream.done.then(() => {
        btn.disabled = false;
        restoreButtonLabel(btn, original);
        target.removeAttribute('aria-busy');
        streams.delete(target);
        return;
      }).catch(() => {
        // The completion promise never rejects; nothing to recover from.
      });
    });
  };
}


/* ============================================================================
 * Public API
 * ==========================================================================*/

/**
 * Registers an action handler that can be invoked via data-action="name".
 *
 * @param {string} name
 * @param {(ev: Event, el: HTMLElement) => void} fn
 * @returns {void}
 */
export function register(name, fn) {
 registry.set(name, fn);
}

/**
 * Entry point: registers base actions and sets up click delegation.
 * Called once when the AMD module is loaded.
 * @param {Object} cmid Initialization options.
 * @returns {void}
 */
export function init(cmid) {
  const root = document.querySelector('.local-socialcert');
  if (!root) {
    return;
  }

  register('open-link', handleOpenLink);
  register('copy-html', handleCopyHtml);
  register('run-ai', runAiHandler(cmid));

  on(root, '[data-action]', 'click', (ev, el) => {
    const action = el.dataset.action;
    const fn = registry.get(action);
    if (fn) {
      fn(ev, el);
    }
  });
}
