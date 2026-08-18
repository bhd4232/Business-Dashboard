<script>
    (() => {
        if (window.__zzCategoryIconSearchReady) {
            return;
        }

        window.__zzCategoryIconSearchReady = true;

        const inputSelector = '[data-zz-category-icon-search-input]';
        const optionSelector = '[data-zz-category-icon-option]';

        const filterIcons = (input) => {
            const browser = input.closest('[data-zz-category-icon-browser]');

            if (! browser) {
                return;
            }

            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;

            browser.querySelectorAll(optionSelector).forEach((option) => {
                const searchText = (option.dataset.zzCategoryIconSearch || '').toLowerCase();
                const isMatch = ! query || searchText.includes(query);

                option.hidden = ! isMatch;
                option.style.display = isMatch ? '' : 'none';
                option.setAttribute('aria-hidden', isMatch ? 'false' : 'true');

                if (isMatch) {
                    visibleCount += 1;
                }
            });

            const emptyState = browser.querySelector('[data-zz-category-icon-empty]');

            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }
        };

        const resetSearch = (browser) => {
            const input = browser?.querySelector(inputSelector);

            if (! input) {
                return;
            }

            input.value = '';
            filterIcons(input);
        };

        document.addEventListener('input', (event) => {
            if (event.target.matches(inputSelector)) {
                filterIcons(event.target);
            }
        });

        document.addEventListener('search', (event) => {
            if (event.target.matches(inputSelector)) {
                filterIcons(event.target);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || ! event.target.matches(inputSelector)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            filterIcons(event.target);
        });

        document.addEventListener('zz-reset-category-icon-search', (event) => {
            if (! event.detail?.id) {
                return;
            }

            resetSearch(document.getElementById(event.detail.id));
        });
    })();
</script>
