import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const script = readFileSync(new URL('../../resources/js/chat.js', import.meta.url), 'utf8');

test('admin inbox refreshes abort and ignore stale conversation requests', () => {
    const loadStart = script.indexOf('async function loadConversations()');
    const loadEnd = script.indexOf('\n    async function markRead', loadStart);

    assert.notEqual(loadStart, -1);
    assert.notEqual(loadEnd, -1);

    const implementation = script.slice(loadStart, loadEnd);
    const abortPosition = implementation.indexOf('conversationsController.abort()');
    const requestPosition = implementation.indexOf('requestJson(root.dataset.conversationsUrl, { signal: controller.signal })');
    const staleGuardPosition = implementation.indexOf('requestVersion !== conversationsRequestVersion');
    const renderPosition = implementation.indexOf('renderConversations(payload.data || [])');
    const cleanupPosition = implementation.indexOf('if (conversationsController === controller) conversationsController = null');

    assert.match(script, /let conversationsController = null;/);
    assert.match(script, /let conversationsRequestVersion = 0;/);
    assert.notEqual(abortPosition, -1);
    assert.notEqual(requestPosition, -1);
    assert.notEqual(staleGuardPosition, -1);
    assert.notEqual(renderPosition, -1);
    assert.notEqual(cleanupPosition, -1);
    assert.ok(abortPosition < requestPosition);
    assert.ok(requestPosition < staleGuardPosition);
    assert.ok(staleGuardPosition < renderPosition);
    assert.ok(renderPosition < cleanupPosition);
});
