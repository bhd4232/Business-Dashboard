import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    readFileSync,
    readdirSync,
    renameSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

export function hashIdentity(kind, value) {
    return `deploy_${createHash('sha256')
        .update(`${String(kind).trim()}\0${String(value).trim()}`)
        .digest('hex')}`;
}

export function resolveCommit(environment = process.env, gitCommit = null) {
    for (const key of ['SOURCE_COMMIT', 'COOLIFY_GIT_COMMIT_SHA', 'GIT_COMMIT']) {
        const value = normalize(environment[key]);

        if (value) {
            return value;
        }
    }

    return normalize(gitCommit);
}

const SOURCE_DIRECTORIES = [
    'app',
    'bootstrap',
    'config',
    'database',
    'lang',
    'public',
    'resources',
    'routes',
    'scripts',
];

const SOURCE_FILES = [
    'artisan',
    'CHANGELOG.md',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
];

export function createDeploymentMetadata({ commit, sourceId, assetsId, builtAt }) {
    const normalizedCommit = normalize(commit);
    const normalizedSourceId = normalize(sourceId);
    const normalizedAssetsId = normalize(assetsId);

    if (!normalizedAssetsId) {
        throw new Error('A Vite asset manifest hash is required.');
    }

    if (!normalizedSourceId) {
        throw new Error('An application source hash is required.');
    }

    return {
        schema_version: 1,
        deployment_id: hashIdentity('artifact', [
            normalizedCommit?.toLowerCase() ?? '',
            normalizedSourceId,
            normalizedAssetsId,
        ].join('\0')),
        commit: normalizedCommit,
        short_commit: normalizedCommit
            ? normalizedCommit.slice(0, 12)
            : null,
        built_at: normalize(builtAt),
        source_id: normalizedSourceId,
        assets_id: normalizedAssetsId,
        ready: true,
    };
}

export function hashSourceTree(root = process.cwd()) {
    const paths = [];

    for (const directory of SOURCE_DIRECTORIES) {
        collectFiles(
            resolve(root, directory),
            paths,
            directory === 'public' ? new Set(['build', 'hot', 'storage']) : new Set(),
        );
    }

    for (const file of SOURCE_FILES) {
        const path = resolve(root, file);

        if (existsSync(path)) {
            paths.push(path);
        }
    }

    const hash = createHash('sha256');

    for (const path of [...new Set(paths)].sort()) {
        const normalizedPath = relative(root, path).split(sep).join('/');
        hash.update(normalizedPath);
        hash.update('\0');
        hash.update(readFileSync(path));
        hash.update('\0');
    }

    return hash.digest('hex');
}

export function writeDeploymentMetadata({
    root = process.cwd(),
    environment = process.env,
    now = () => new Date(),
    gitCommit,
} = {}) {
    const buildDirectory = resolve(root, 'public', 'build');
    const assetManifestPath = resolve(buildDirectory, 'manifest.json');

    if (!existsSync(assetManifestPath)) {
        throw new Error(`Vite manifest not found at ${assetManifestPath}`);
    }

    const assetManifest = readFileSync(assetManifestPath);
    const assetsId = createHash('sha256').update(assetManifest).digest('hex');
    const sourceId = hashSourceTree(root);
    const resolvedGitCommit = gitCommit === undefined
        ? readGitCommit(root)
        : gitCommit;
    const commit = resolveCommit(environment, resolvedGitCommit);
    const metadata = createDeploymentMetadata({
        commit,
        sourceId,
        assetsId,
        builtAt: resolveBuiltAt(environment, now),
    });
    const outputPath = resolve(buildDirectory, 'deployment.json');
    const temporaryPath = `${outputPath}.tmp`;

    mkdirSync(dirname(outputPath), { recursive: true });
    writeFileSync(temporaryPath, `${JSON.stringify(metadata, null, 2)}\n`, 'utf8');

    try {
        renameSync(temporaryPath, outputPath);
    } catch (error) {
        if (!['EEXIST', 'EPERM'].includes(error?.code)) {
            throw error;
        }

        rmSync(outputPath, { force: true });
        renameSync(temporaryPath, outputPath);
    }

    return { metadata, outputPath };
}

function collectFiles(directory, paths, ignoredNames = new Set()) {
    if (!existsSync(directory)) {
        return;
    }

    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        if (ignoredNames.has(entry.name)) {
            continue;
        }

        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            collectFiles(path, paths, ignoredNames);
        } else if (entry.isFile()) {
            paths.push(path);
        }
    }
}

function resolveBuiltAt(environment, now) {
    const configured = normalize(environment.APP_DEPLOYMENT_BUILT_AT);

    if (configured) {
        return configured;
    }

    const sourceDateEpoch = normalize(environment.SOURCE_DATE_EPOCH);

    if (sourceDateEpoch && /^\d+$/.test(sourceDateEpoch)) {
        return new Date(Number(sourceDateEpoch) * 1000).toISOString();
    }

    return now().toISOString();
}

function readGitCommit(root) {
    try {
        return execFileSync('git', ['rev-parse', 'HEAD'], {
            cwd: root,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
        });
    } catch {
        return null;
    }
}

function normalize(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const normalized = String(value).trim();

    return normalized || null;
}

const isDirectExecution = process.argv[1]
    && resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isDirectExecution) {
    const { metadata, outputPath } = writeDeploymentMetadata();

    process.stdout.write(
        `Deployment metadata written to ${outputPath} (${metadata.deployment_id}).\n`,
    );
}
