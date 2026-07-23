import assert from 'node:assert/strict';
import test from 'node:test';
import {
    classifyReloadObservations,
    observeDeployment,
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

test('reload checks only reload the already loaded deployment', () => {
    assert.equal(classifyReloadObservations('deploy-old', '2026-07-23T01:00:00.000Z', {
        ready: true,
        deployment_id: 'deploy-old',
        built_at: '2026-07-23T01:00:00.000Z',
    }), 'reload');

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
