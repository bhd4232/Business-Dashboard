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
        return 'current';
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

export function updateSignalDeploymentId(signal) {
    if (typeof signal === 'string') {
        return signal.trim() || null;
    }

    const deploymentId = signal?.deployment_id
        ?? signal?.data?.deployment_id
        ?? signal?.notification?.data?.deployment_id;

    return typeof deploymentId === 'string' && deploymentId.trim()
        ? deploymentId.trim()
        : null;
}

export function shouldBlockAdminNavigation(
    updatePending,
    currentLocation,
    destination,
) {
    if (!updatePending || !destination) {
        return false;
    }

    try {
        const currentUrl = new URL(currentLocation);
        const destinationUrl = destination instanceof URL
            ? destination
            : new URL(destination, currentUrl);

        if (destinationUrl.origin !== currentUrl.origin) {
            return false;
        }

        if (
            destinationUrl.pathname !== '/admin'
            && !destinationUrl.pathname.startsWith('/admin/')
        ) {
            return false;
        }

        // Fragment navigation does not ask the server for a different app
        // shell, so it is safe to keep in-page anchors working.
        if (
            destinationUrl.pathname === currentUrl.pathname
            && destinationUrl.search === currentUrl.search
            && destinationUrl.hash
            && destinationUrl.hash !== currentUrl.hash
        ) {
            return false;
        }

        return true;
    } catch {
        return false;
    }
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
    let allowConfirmedUpgradeNavigation = false;

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

    const confirmNewDeployment = async (
        expectedDeploymentId = null,
        firstObservation = null,
    ) => {
        const first = firstObservation ?? await fetchVersion();

        if (
            !first?.ready
            || !first.deployment_id
            || (
                expectedDeploymentId
                && first.deployment_id !== expectedDeploymentId
            )
            || !isDeploymentNewer(
                state.loadedDeploymentId,
                state.loadedBuiltAt,
                first.deployment_id,
                first.built_at,
            )
        ) {
            return null;
        }

        await new Promise((resolve) => window.setTimeout(resolve, 1000));

        const second = await fetchVersion();

        if (
            expectedDeploymentId
            && second?.deployment_id !== expectedDeploymentId
        ) {
            return null;
        }

        if (classifyReloadObservations(
            state.loadedDeploymentId,
            state.loadedBuiltAt,
            first,
            second,
        ) !== 'upgrade') {
            return null;
        }

        state = {
            ...state,
            candidateDeploymentId: second.deployment_id,
            candidateHits: REQUIRED_CONFIRMATIONS,
            isAvailable: true,
        };
        revealUpgrade(second);
        void synchronizeNotification(second.deployment_id);

        return second;
    };

    const receiveUpdateNotification = async (signal = null) => {
        const expectedDeploymentId = updateSignalDeploymentId(signal);

        if (
            state.isAvailable
            && (
                !expectedDeploymentId
                || latestDeployment?.deployment_id === expectedDeploymentId
            )
        ) {
            openUpgradeDialog();

            return true;
        }

        try {
            const deployment = await confirmNewDeployment(expectedDeploymentId);

            if (!deployment) {
                return false;
            }

            // A foreground push only verifies and presents the update. The
            // signed Upgrade POST remains the sole acknowledgement path.
            openUpgradeDialog();

            return true;
        } catch {
            return false;
        }
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

            if (firstDecision === 'current') {
                // Save notifications and pull-to-refresh used to reload here.
                // A deployment can switch between this check and the reload,
                // so even a "same build" observation cannot safely authorize
                // an automatic document replacement.
                return false;
            }

            const deployment = await confirmNewDeployment(
                null,
                firstObservation,
            );

            if (deployment) {
                openUpgradeDialog();
            }

            return false;
        } catch {
            // Failing closed prevents a normal refresh from silently crossing
            // into an unconfirmed deployment while the network is unstable.
            return false;
        }
    };

    const blockPendingNavigation = (event, destination) => {
        if (!shouldBlockAdminNavigation(
            state.isAvailable,
            window.location.href,
            destination,
        )) {
            return false;
        }

        event.preventDefault();
        openUpgradeDialog();

        return true;
    };

    if (initialUpgradeAvailable) {
        revealUpgrade({
            deployment_id: state.loadedDeploymentId,
            built_at: state.loadedBuiltAt,
        });
    } else {
        window.__zzAppUpgradePending = false;
    }

    window.setTimeout(() => void checkNow(), 0);
    window.setTimeout(() => void checkNow(), 3000);
    window.setInterval(() => void checkNow(), pollInterval);

    window.addEventListener('focus', () => void checkNow());
    window.addEventListener('online', () => void checkNow());
    window.addEventListener('zz:app-update-available', (event) => {
        void receiveUpdateNotification(event.detail);
    });

    if (window.__zzPendingAppUpdateSignal) {
        const pendingUpdateSignal = window.__zzPendingAppUpdateSignal;

        delete window.__zzPendingAppUpdateSignal;
        void receiveUpdateNotification(pendingUpdateSignal);
    }
    window.addEventListener('keydown', (event) => {
        const reloadShortcut = event.key === 'F5'
            || (
                (event.ctrlKey || event.metaKey)
                && event.key.toLowerCase() === 'r'
            );

        if (!state.isAvailable || !reloadShortcut) {
            return;
        }

        event.preventDefault();
        openUpgradeDialog();
    }, true);
    window.addEventListener('beforeunload', (event) => {
        if (!state.isAvailable || allowConfirmedUpgradeNavigation) {
            return;
        }

        // Browsers and Android WebViews decide whether to show this native
        // warning. It cannot stop an OS-forced restart, and the deployed PHP
        // backend is already active; it only protects the loaded DOM/assets.
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('submit', (event) => {
        if (event.target?.matches?.('[data-zz-app-upgrade-form]')) {
            allowConfirmedUpgradeNavigation = true;
        }
    }, true);
    document.addEventListener('click', (event) => {
        if (
            event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
        ) {
            return;
        }

        const link = event.target?.closest?.('a[href]');

        if (
            !link
            || link.hasAttribute('download')
            || !['', '_self'].includes(link.getAttribute('target') ?? '')
        ) {
            return;
        }

        if (blockPendingNavigation(event, link.href)) {
            event.stopImmediatePropagation();
        }
    }, true);
    document.addEventListener('livewire:navigate', (event) => {
        blockPendingNavigation(event, event.detail?.url);
    });
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
        receiveUpdateNotification,
        reloadIfCurrent,
    };
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    if (!window.ZamZamAppUpdater) {
        window.ZamZamAppUpdater = createAppUpdater();
    }
}
