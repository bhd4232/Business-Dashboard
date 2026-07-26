import assert from 'node:assert/strict';
import test from 'node:test';
import {
    classifyReloadObservations,
    observeDeployment,
    shouldBlockAdminNavigation,
    updateSignalDeploymentId,
} from '../../resources/js/app-updater.js';

test('requires two matching observations before an update is available', () => {
    const initial = {
        loadedDeploymentId: 'deploy-old',
        loadedBuiltAt: '2026-07-23T01:00:00.000Z',
        candidateDeploymentId: null,
        candidateHits: 0,
        isAvailable: false,
    };

    const first = observeDeployment(initial, 'deploy-new', '2026-07-23T02:00:00.000Z');
    const second = observeDeployment(first, 'deploy-new', '2026-07-23T02:00:00.000Z');

    assert.equal(first.isAvailable, false);
    assert.equal(first.candidateHits, 1);
    assert.equal(second.isAvailable, true);
    assert.equal(second.candidateHits, 2);
});

test('a changing candidate restarts confirmation', () => {
    const state = {
        loadedDeploymentId: 'deploy-old',
        loadedBuiltAt: '2026-07-23T01:00:00.000Z',
        candidateDeploymentId: 'deploy-a',
        candidateHits: 1,
        isAvailable: false,
    };

    const result = observeDeployment(state, 'deploy-b', '2026-07-23T03:00:00.000Z');

    assert.equal(result.candidateDeploymentId, 'deploy-b');
    assert.equal(result.candidateHits, 1);
    assert.equal(result.isAvailable, false);
});

test('the loaded deployment clears an unconfirmed candidate', () => {
    const state = {
        loadedDeploymentId: 'deploy-old',
        loadedBuiltAt: '2026-07-23T01:00:00.000Z',
        candidateDeploymentId: 'deploy-new',
        candidateHits: 1,
        isAvailable: false,
    };

    const result = observeDeployment(state, 'deploy-old', '2026-07-23T01:00:00.000Z');

    assert.equal(result.candidateDeploymentId, null);
    assert.equal(result.candidateHits, 0);
    assert.equal(result.isAvailable, false);
});

test('a confirmed update remains available when an old node is observed later', () => {
    const state = {
        loadedDeploymentId: 'deploy-old',
        loadedBuiltAt: '2026-07-23T01:00:00.000Z',
        candidateDeploymentId: 'deploy-new',
        candidateHits: 2,
        isAvailable: true,
    };

    const result = observeDeployment(state, 'deploy-old', '2026-07-23T01:00:00.000Z');

    assert.equal(result.candidateDeploymentId, 'deploy-new');
    assert.equal(result.candidateHits, 2);
    assert.equal(result.isAvailable, true);
});

test('refresh checks recognize the loaded deployment without crossing builds', () => {
    assert.equal(classifyReloadObservations('deploy-old', '2026-07-23T01:00:00.000Z', {
        ready: true,
        deployment_id: 'deploy-old',
        built_at: '2026-07-23T01:00:00.000Z',
    }), 'current');

    assert.equal(classifyReloadObservations('deploy-old', '2026-07-23T01:00:00.000Z', {
        ready: false,
        deployment_id: 'deploy-new',
        built_at: '2026-07-23T02:00:00.000Z',
    }), 'wait');

    assert.equal(classifyReloadObservations('deploy-old', '2026-07-23T01:00:00.000Z', {
        ready: true,
        deployment_id: 'deploy-new',
        built_at: '2026-07-23T02:00:00.000Z',
    }), 'confirm');

    assert.equal(classifyReloadObservations(
        'deploy-old',
        '2026-07-23T01:00:00.000Z',
        { ready: true, deployment_id: 'deploy-new', built_at: '2026-07-23T02:00:00.000Z' },
        { ready: true, deployment_id: 'deploy-other', built_at: '2026-07-23T03:00:00.000Z' },
    ), 'wait');

    assert.equal(classifyReloadObservations(
        'deploy-old',
        '2026-07-23T01:00:00.000Z',
        { ready: true, deployment_id: 'deploy-new', built_at: '2026-07-23T02:00:00.000Z' },
        { ready: true, deployment_id: 'deploy-new', built_at: '2026-07-23T02:00:00.000Z' },
    ), 'upgrade');
});

test('an older rolling node is never treated as an update', () => {
    const state = {
        loadedDeploymentId: 'deploy-new',
        loadedBuiltAt: '2026-07-23T02:00:00.000Z',
        candidateDeploymentId: null,
        candidateHits: 0,
        isAvailable: false,
    };

    const result = observeDeployment(
        state,
        'deploy-old',
        '2026-07-23T01:00:00.000Z',
    );

    assert.equal(result.isAvailable, false);
    assert.equal(result.candidateDeploymentId, null);
    assert.equal(classifyReloadObservations(
        state.loadedDeploymentId,
        state.loadedBuiltAt,
        {
            ready: true,
            deployment_id: 'deploy-old',
            built_at: '2026-07-23T01:00:00.000Z',
        },
    ), 'wait');
});

test('pending updates block same-origin admin navigation only', () => {
    const current = 'https://app.example.com/admin/orders?status=open';

    assert.equal(shouldBlockAdminNavigation(
        true,
        current,
        'https://app.example.com/admin/settings/release-notes',
    ), true);
    assert.equal(shouldBlockAdminNavigation(
        true,
        current,
        '/admin',
    ), true);
    assert.equal(shouldBlockAdminNavigation(
        false,
        current,
        '/admin/products',
    ), false);
    assert.equal(shouldBlockAdminNavigation(
        true,
        current,
        'https://docs.example.com/admin/help',
    ), false);
    assert.equal(shouldBlockAdminNavigation(
        true,
        current,
        '/contact',
    ), false);
});

test('pending updates allow in-page fragment navigation without a server request', () => {
    assert.equal(shouldBlockAdminNavigation(
        true,
        'https://app.example.com/admin/orders?status=open',
        'https://app.example.com/admin/orders?status=open#order-42',
    ), false);

    assert.equal(shouldBlockAdminNavigation(
        true,
        'https://app.example.com/admin/orders?status=open',
        'https://app.example.com/admin/orders?status=closed#order-42',
    ), true);
});

test('push update signals expose only a candidate deployment id', () => {
    assert.equal(updateSignalDeploymentId(' deploy-new '), 'deploy-new');
    assert.equal(updateSignalDeploymentId({
        deployment_id: 'deploy-direct',
    }), 'deploy-direct');
    assert.equal(updateSignalDeploymentId({
        data: {
            deployment_id: 'deploy-data',
        },
    }), 'deploy-data');
    assert.equal(updateSignalDeploymentId({
        notification: {
            data: {
                deployment_id: 'deploy-native',
            },
        },
    }), 'deploy-native');
    assert.equal(updateSignalDeploymentId({ data: {} }), null);
});
