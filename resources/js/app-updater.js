const REQUIRED_CONFIRMATIONS = 2;

export function isDeploymentNewer(
    loadedDeploymentId,
    loadedBuiltAt,
    observedDeploymentId,
    observedBuiltAt,
) {
    if (!observedDeploymentId || observedDeploymentId === loadedDeploymentId) {
        return false;
    }

    const loadedTimestamp = Date.parse(loadedBuiltAt ?? '');
    const observedTimestamp = Date.parse(observedBuiltAt ?? '');

    return Number.isFinite(loadedTimestamp)
        && Number.isFinite(observedTimestamp)
        && observedTimestamp > loadedTimestamp;
}

export function observeDeployment(
    state,
    observedDeploymentId,
    observedBuiltAt,
) {
    if (state.isAvailable) {
        return {
            ...state,
            isAvailable: true,
        };
    }

    if (!isDeploymentNewer(
        state.loadedDeploymentId,
        state.loadedBuiltAt,
        observedDeploymentId,
        observedBuiltAt,
    )) {
        return {
            ...state,
            candidateDeploymentId: null,
            candidateHits: 0,
            isAvailable: false,
        };
    }

    const isSameCandidate = state.candidateDeploymentId === observedDeploymentId;
    const candidateHits = isSameCandidate ? state.candidateHits + 1 : 1;

    return {
        ...state,
        candidateDeploymentId: observedDeploymentId,
        candidateHits,
        isAvailable: candidateHits >= REQUIRED_CONFIRMATIONS,
    };
}

export function classifyReloadObservations(
    loadedDeploymentId,
    loadedBuiltAt,
    firstObservation,
    secondObservation = null,
) {
    if (!firstObservation?.ready || !firstObservation.deployment_id) {
        return 'wait';
    }

    if (firstObservation.deployment_id === loadedDeploymentId) {
        return 'reload';
    }

    if (!isDeploymentNewer(
        loadedDeploymentId,
        loadedBuiltAt,
        firstObservation.deployment_id,
        firstObservation.built_at,
    )) {
        return 'wait';
    }

    if (secondObservation === null) {
        return 'confirm';
    }

    if (
        !secondObservation?.ready
        || secondObservation.deployment_id !== firstObservation.deployment_id
    ) {
        return 'wait';
    }

    return 'upgrade';
}

function createAppUpdater() {
    const config = document.querySelector('#zz-app-updater-config');

    if (!config) {
        return null;
    }

    const pollInterval = Math.max(Number(config.dataset.pollInterval) || 15000, 5000);
    const initialUpgradeAvailable = config.dataset.initialUpgradeAvailable === 'true';
    let state = {
        loadedDeploymentId: config.dataset.loadedDeployment,
        loadedBuiltAt: config.dataset.loadedBuiltAt,
        candidateDeploymentId: null,
        candidateHits: 0,
        isAvailable: initialUpgradeAvailable,
    };
    let latestDeployment = null;
    let pollInFlight = false;
    let synchronizedDeploymentId = null;

    const upgradeActions = () => document.querySelectorAll('[data-zz-app-upgrade-action]');

    const announce = (message) => {
        const status = document.querySelector('[data-zz-app-update-status]');

        if (status) {
            status.textContent = message;
        }
    };

    const revealUpgrade = (deployment = null) => {
        latestDeployment = deployment ?? latestDeployment;
        state.isAvailable = true;
        window.__zzAppUpgradePending = true;

        upgradeActions().forEach((action) => {
            action.removeAttribute('hidden');
            action.setAttribute('aria-hidden', 'false');
        });

        const version = latestDeployment?.published_version ?? latestDeployment?.version;

        if (version) {
            document.querySelectorAll('[data-zz-update-version]').forEach((label) => {
                label.textContent = `v${version}`;
            });
        }

        if (latestDeployment?.deployment_id) {
            document.querySelectorAll('[data-zz-app-upgrade-deployment]').forEach((input) => {
                input.value = latestDeployment.deployment_id;
            });
        }

        announce(
            version
                ? `App update version ${version} is available. Use Upgrade App from the profile menu when ready.`
                : 'An app update is available. Use Upgrade App from the profile menu when ready.',
        );
    };

    const fetchVersion = async () => {
        const url = new URL(config.dataset.versionUrl, window.location.origin);
        url.searchParams.set('_deployment_check', Date.now().toString());

        const response = await fetch(url, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Version check failed with HTTP ${response.status}.`);
        }

        return response.json();
    };

    const synchronizeNotification = async (deploymentId) => {
        if (!deploymentId || synchronizedDeploymentId === deploymentId) {
            return;
        }

        synchronizedDeploymentId = deploymentId;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            const response = await fetch(config.dataset.syncUrl, {
                method: 'POST',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    deployment_id: deploymentId,
                }),
            });

            if (!response.ok) {
                synchronizedDeploymentId = null;
            }
        } catch {
            synchronizedDeploymentId = null;
        }
    };

    const inspect = (deployment) => {
        if (!deployment?.ready || !deployment.deployment_id) {
            return false;
        }

        if (state.isAvailable) {
            return true;
        }

        state = observeDeployment(
            state,
            deployment.deployment_id,
            deployment.built_at,
        );

        if (!state.isAvailable) {
            return false;
        }

        revealUpgrade(deployment);
        void synchronizeNotification(deployment.deployment_id);

        return true;
    };

    const checkNow = async () => {
        if (pollInFlight) {
            return false;
        }

        pollInFlight = true;

        try {
            return inspect(await fetchVersion());
        } catch {
            return false;
        } finally {
            pollInFlight = false;
        }
    };

    const openUpgradeDialog = () => {
        const returnInput = document.querySelector('[data-zz-app-upgrade-return]');

        if (returnInput) {
            returnInput.value = window.location.href;
        }

        window.dispatchEvent(new CustomEvent('open-modal', {
            detail: { id: 'app-upgrade-confirmation' },
        }));
    };

    const reloadIfCurrent = async () => {
        if (window.__zzAppUpgradePending) {
            openUpgradeDialog();

            return false;
        }

        try {
            const firstObservation = await fetchVersion();
            const firstDecision = classifyReloadObservations(
                state.loadedDeploymentId,
                state.loadedBuiltAt,
                firstObservation,
            );

            if (firstDecision === 'wait') {
                return false;
            }

            if (firstDecision === 'reload') {
                window.location.reload();

                return true;
            }

            await new Promise((resolve) => window.setTimeout(resolve, 1000));

            const secondObservation = await fetchVersion();
            const secondDecision = classifyReloadObservations(
                state.loadedDeploymentId,
                state.loadedBuiltAt,
                firstObservation,
                secondObservation,
            );

            if (secondDecision === 'upgrade') {
                state = {
                    ...state,
                    candidateDeploymentId: secondObservation.deployment_id,
                    candidateHits: REQUIRED_CONFIRMATIONS,
                    isAvailable: true,
                };
                revealUpgrade(secondObservation);
                void synchronizeNotification(secondObservation.deployment_id);
            }

            return false;
        } catch {
            // Failing closed prevents a normal refresh from silently crossing
            // into an unconfirmed deployment while the network is unstable.
            return false;
        }
    };

    if (initialUpgradeAvailable) {
        revealUpgrade();
    } else {
        window.__zzAppUpgradePending = false;
    }

    window.setTimeout(() => void checkNow(), 0);
    window.setTimeout(() => void checkNow(), 3000);
    window.setInterval(() => void checkNow(), pollInterval);

    window.addEventListener('focus', () => void checkNow());
    window.addEventListener('online', () => void checkNow());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void checkNow();
        }
    });
    document.addEventListener('livewire:navigated', () => {
        if (state.isAvailable) {
            revealUpgrade(latestDeployment);
        }
    });

    return {
        checkNow,
        openUpgradeDialog,
        reloadIfCurrent,
    };
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    if (!window.ZamZamAppUpdater) {
        window.ZamZamAppUpdater = createAppUpdater();
    }
}
