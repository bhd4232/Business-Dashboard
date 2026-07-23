import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import {
    mkdtempSync,
    mkdirSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import {
    createDeploymentMetadata,
    hashSourceTree,
    hashIdentity,
    resolveCommit,
    writeDeploymentMetadata,
} from '../../scripts/write-deployment-metadata.mjs';

test('runtime environment commit uses the documented precedence', () => {
    assert.equal(resolveCommit({
        SOURCE_COMMIT: ' source-sha ',
        COOLIFY_GIT_COMMIT_SHA: 'coolify-sha',
        GIT_COMMIT: 'git-sha',
    }, 'repository-sha'), 'source-sha');

    assert.equal(resolveCommit({
        COOLIFY_GIT_COMMIT_SHA: ' coolify-sha ',
        GIT_COMMIT: 'git-sha',
    }, 'repository-sha'), 'coolify-sha');

    assert.equal(resolveCommit({}, ' repository-sha '), 'repository-sha');
});

test('metadata identity combines commit, source, and built assets', () => {
    const withCommit = createDeploymentMetadata({
        commit: 'ABCDEF0123456789',
        sourceId: 'source-123',
        assetsId: 'assets-123',
        builtAt: '2026-07-23T01:02:03.000Z',
    });
    const withoutCommit = createDeploymentMetadata({
        commit: null,
        sourceId: 'source-123',
        assetsId: 'assets-123',
        builtAt: '2026-07-23T01:02:03.000Z',
    });

    assert.equal(
        withCommit.deployment_id,
        hashIdentity('artifact', [
            'abcdef0123456789',
            'source-123',
            'assets-123',
        ].join('\0')),
    );
    assert.equal(withCommit.short_commit, 'ABCDEF012345');
    assert.equal(
        withoutCommit.deployment_id,
        hashIdentity('artifact', [
            '',
            'source-123',
            'assets-123',
        ].join('\0')),
    );
    assert.equal(withoutCommit.commit, null);
    assert.equal(withoutCommit.ready, true);
});

test('writer hashes the Vite manifest and writes reproducible metadata', (context) => {
    const root = mkdtempSync(join(tmpdir(), 'deployment-metadata-'));
    const buildDirectory = join(root, 'public', 'build');
    const manifest = '{"resources/js/app.js":{"file":"assets/app-123.js"}}\n';

    context.after(() => rmSync(root, { recursive: true, force: true }));

    mkdirSync(buildDirectory, { recursive: true });
    mkdirSync(join(root, 'app'), { recursive: true });
    writeFileSync(join(buildDirectory, 'manifest.json'), manifest, 'utf8');
    writeFileSync(join(root, 'app', 'Example.php'), '<?php return "one";', 'utf8');

    const { metadata, outputPath } = writeDeploymentMetadata({
        root,
        environment: {
            SOURCE_COMMIT: '1234567890abcdef',
            SOURCE_DATE_EPOCH: '1784772000',
        },
        gitCommit: 'ignored-repository-sha',
    });
    const written = JSON.parse(readFileSync(outputPath, 'utf8'));

    assert.deepEqual(written, metadata);
    assert.equal(
        metadata.assets_id,
        createHash('sha256').update(manifest).digest('hex'),
    );
    assert.equal(metadata.source_id, hashSourceTree(root));
    assert.equal(metadata.commit, '1234567890abcdef');
    assert.equal(metadata.built_at, '2026-07-23T02:00:00.000Z');
    assert.equal(metadata.ready, true);
});

test('backend-only source changes produce a new deployment identity without a git commit', (context) => {
    const root = mkdtempSync(join(tmpdir(), 'deployment-source-'));
    const appDirectory = join(root, 'app');

    context.after(() => rmSync(root, { recursive: true, force: true }));

    mkdirSync(appDirectory, { recursive: true });
    writeFileSync(join(appDirectory, 'Service.php'), '<?php return "first";', 'utf8');
    const first = hashSourceTree(root);

    writeFileSync(join(appDirectory, 'Service.php'), '<?php return "second";', 'utf8');
    const second = hashSourceTree(root);

    assert.notEqual(first, second);
});

test('same-commit asset changes produce a new deployment identity', () => {
    const first = createDeploymentMetadata({
        commit: 'abcdef0123456789',
        sourceId: 'source-123',
        assetsId: 'assets-one',
        builtAt: '2026-07-23T01:02:03.000Z',
    });
    const second = createDeploymentMetadata({
        commit: 'abcdef0123456789',
        sourceId: 'source-123',
        assetsId: 'assets-two',
        builtAt: '2026-07-23T01:03:03.000Z',
    });

    assert.notEqual(first.deployment_id, second.deployment_id);
});
