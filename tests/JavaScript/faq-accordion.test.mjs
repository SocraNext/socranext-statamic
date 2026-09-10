import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';

const template = new URL('../../resources/views/public/faq.blade.php', import.meta.url);
const html = fs.readFileSync(template, 'utf8');
const script = html.match(/<script>\s*([\s\S]*?)<\/script>/)[1];

// The browser reports content height before its opening padding transition has
// finished. Later the same answer needs its text plus the settled 32px padding.
function fixture() {
  const listeners = {}, attributes = {}, classes = new Set(['open']);
  const state = { textHeight: 51, paddingHeight: 0 };
  const answer = { style: { maxHeight: 'none' }, get scrollHeight() { return state.textHeight + state.paddingHeight; } };
  const item = {
    classList: { remove(v) { classes.delete(v); }, toggle(v) { if (classes.has(v)) { classes.delete(v); return false; } classes.add(v); return true; } },
    querySelector(selector) { assert.equal(selector, '.socranext-a'); return answer; },
  };
  const head = { dataset: {}, parentElement: item, setAttribute(key, value) { attributes[key] = value; },
    addEventListener(type, fn) { (listeners[type] ||= []).push(fn); } };
  const context = vm.createContext({ document: { querySelectorAll() { return head.dataset.snBound ? [] : [head]; } } });
  const mount = () => vm.runInContext(script, context);
  const click = () => listeners.click.forEach(fn => fn());
  const key = (key) => { const event = { key, prevented: false, preventDefault() { this.prevented = true; } }; listeners.keydown.forEach(fn => fn(event)); return event; };
  const answerVisibleHeight = () => answer.style.maxHeight === 'none' ? answer.scrollHeight : Math.min(answer.scrollHeight, parseFloat(answer.style.maxHeight) || 0);
  mount();
  return { state, answer, attributes, classes, mount, click, key, listeners, answerVisibleHeight };
}

test('opening remains fully visible when answer padding finishes animating', () => {
  const f = fixture(); f.click();
  assert.equal(f.attributes['aria-expanded'], 'true');
  f.state.paddingHeight = 32;
  assert.equal(f.answerVisibleHeight(), 83, '51px text plus 32px padding must fit');
});

test('an open answer adapts to narrower mobile layout and later style/font changes', () => {
  const f = fixture(); f.click();
  f.state.textHeight = 204; f.state.paddingHeight = 48;
  assert.equal(f.answerVisibleHeight(), 252, 'no stale pixel limit after reflow');
});

test('click, Enter and Space still toggle collapse and accessible expanded state', () => {
  const f = fixture();
  assert.equal(f.attributes['aria-expanded'], 'false');
  assert.equal(f.answerVisibleHeight(), 0);
  assert.equal(f.key('Enter').prevented, true);
  assert.equal(f.attributes['aria-expanded'], 'true');
  assert.equal(f.key(' ').prevented, true);
  assert.equal(f.attributes['aria-expanded'], 'false');
  assert.equal(f.answerVisibleHeight(), 0);
  assert.equal(f.key('Tab').prevented, false);
  assert.equal(f.attributes['aria-expanded'], 'false');
  f.click(); assert.equal(f.attributes['aria-expanded'], 'true');
  f.click(); assert.equal(f.attributes['aria-expanded'], 'false');
});

test('rendering another FAQ block never binds existing answers twice', () => {
  const f = fixture(); f.mount();
  assert.equal(f.listeners.click.length, 1);
  f.click(); assert.equal(f.attributes['aria-expanded'], 'true');
});
