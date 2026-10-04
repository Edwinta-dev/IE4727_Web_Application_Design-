import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { test } from 'node:test';
import { chromium } from 'playwright';
import { prepareStaticAssets } from './lib.mjs';

test('static transport retries reset GETs without hiding HTTP/image errors or retrying POSTs', async () => {
  const counts = new Map();
  let active = 0;
  let maxActive = 0;
  let slowStarted;
  const slowRequest = new Promise(resolve => { slowStarted = resolve; });
  const pixel = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5WQAAAAASUVORK5CYII=', 'base64');
  const server = createServer((request, response) => {
    const key = `${request.method} ${request.url}`;
    counts.set(key, (counts.get(key) ?? 0) + 1);
    if (request.url.startsWith('/clinic-base/assets/') && request.method === 'GET') {
      active++;
      maxActive = Math.max(maxActive, active);
      response.once('close', () => active--);
    }
    if (request.url === '/clinic-base/assets/reset.png' && counts.get(key) === 1) {
      request.socket.destroy();
      return;
    }
    if (request.url === '/clinic-base/assets/truncated.png' && counts.get(key) === 1) {
      response.writeHead(200, { 'Content-Type': 'image/png', 'Content-Length': pixel.length }).end(pixel.subarray(0, 10));
      return;
    }
    if (request.url === '/clinic-base/assets/missing.png') {
      response.writeHead(404).end('missing');
      return;
    }
    if (request.url === '/clinic-base/assets/corrupt.png') {
      response.writeHead(200, { 'Content-Type': 'image/png' }).end('corrupt');
      return;
    }
    if (request.method === 'POST') {
      response.writeHead(503).end('Mutation refused');
      return;
    }
    if (request.url.startsWith('/clinic-base/assets/')) {
      if (request.url === '/clinic-base/assets/slow.png') slowStarted();
      setTimeout(() => response.writeHead(200, { 'Content-Type': 'image/png' }).end(pixel), request.url.endsWith('/slow.png') ? 150 : 20);
      return;
    }
    response.writeHead(200, { 'Content-Type': 'text/html' }).end('<!doctype html><title>Static transport fixture</title>');
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}/clinic-base/`;
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await prepareStaticAssets(page, base);
    await page.goto(base);
    const decode = name => page.evaluate(async src => {
      const image = new Image();
      document.body.append(image);
      image.src = src;
      await image.decode();
      return image.naturalWidth;
    }, `${base}assets/${name}.png`);
    const read = name => page.evaluate(async src => {
      const response = await fetch(src);
      return { status: response.status, body: await response.text() };
    }, `${base}assets/${name}.png`);
    const recoveredResponse = page.waitForResponse(`${base}assets/reset.png`);
    assert.equal(await decode('reset'), 1);
    assert.deepEqual(await (await recoveredResponse).body(), pixel, 'Returned image bytes are unchanged');
    assert.equal(counts.get('GET /clinic-base/assets/reset.png'), 2);
    assert.equal(await decode('truncated'), 1);
    assert.equal(counts.get('GET /clinic-base/assets/truncated.png'), 2);
    assert.deepEqual(await Promise.all([decode('one'), decode('two')]), [1, 1]);
    assert.equal(maxActive, 1, 'Static requests are serialized for the single-thread server');
    await assert.rejects(decode('missing'), /cannot be decoded/);
    assert.deepEqual(await read('missing'), { status: 404, body: 'missing' });
    assert.equal(counts.get('GET /clinic-base/assets/missing.png'), 2, 'One decode and one byte/status inspection; no HTTP retries');
    await assert.rejects(decode('corrupt'), /cannot be decoded/);
    assert.deepEqual(await read('corrupt'), { status: 200, body: 'corrupt' });
    assert.equal(counts.get('GET /clinic-base/assets/corrupt.png'), 2, 'One decode and one byte/status inspection; no HTTP retries');
    const post = await page.evaluate(async () => {
      const response = await fetch('assets/mutation', { method: 'POST' });
      return { status: response.status, body: await response.text() };
    });
    assert.deepEqual(post, { status: 503, body: 'Mutation refused' });
    assert.equal(counts.get('POST /clinic-base/assets/mutation'), 1);
    await page.evaluate(() => {
      const image = new Image();
      image.src = 'assets/slow.png';
      document.body.append(image);
    });
    await slowRequest;
    await page.close();
    await new Promise(resolve => setTimeout(resolve, 200));
    assert.equal(counts.get('GET /clinic-base/assets/slow.png'), 1, 'Closing a page does not retry its abandoned asset');
  } finally {
    await browser.close();
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
  }
});
