export function registerAlpineComponents() {
    Alpine.data('bundle', () => ({
        bundles: [],
        drafts: [],
        awaitingApproval: [],
        denied: [],
        active: [],
        expired: [],
        currentBundle: null,
        ownerToken: null,
        loading: false,

        parseExpiresAt(expiresAt) {
            if (expiresAt == null || expiresAt === '') {
                return null;
            }

            const parsed = dayjs(expiresAt);
            return parsed.isValid() ? parsed : null;
        },

        init() {
            this.ownerToken = localStorage.getItem('owner_token');
            if (this.ownerToken === null) {
                this.ownerToken = this.generateStr(15);
                localStorage.setItem('owner_token', this.ownerToken);
            }

            this.bundles = this.bundlesFromWindow();

            if (this.bundles.length > 0) {
                this.bundles.forEach((bundle) => {
                    bundle.label = ! bundle.title ? 'untitled' : bundle.title;

                    if (bundle.status_label) {
                        bundle.label += ' [' + bundle.status_label + ']';
                    }

                    const expiresAt = this.parseExpiresAt(bundle.expires_at);
                    if (expiresAt != null && expiresAt.isValid() && expiresAt.isBefore(dayjs())) {
                        this.expired.push(bundle);
                    } else if (bundle.status === 'pending_approval') {
                        this.awaitingApproval.push(bundle);
                    } else if (bundle.status === 'denied') {
                        this.denied.push(bundle);
                    } else if (bundle.status === 'approved' || bundle.status === 'sent' || bundle.completed === true) {
                        this.active.push(bundle);
                    } else {
                        this.drafts.push(bundle);
                    }

                    bundle.label += ' - ' + window.__createdAtLabel + ' ' + dayjs(bundle.created_at).fromNow();
                });
            }
        },

        newBundle() {
            this.loading = true;

            axios({
                url: BASE_URL + '/new',
                method: 'POST',
            })
                .then((response) => {
                    if (response.data.result === true) {
                        window.location.href = response.data.redirect;
                    }
                })
                .catch((error) => {
                    if (window.isAuthFailure?.(error)) {
                        return;
                    }

                    this.loading = false;
                });
        },

        generateStr(length) {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';

            for (let i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }

            return result;
        },

        openBundle(slug) {
            if (slug) {
                window.location.href = BASE_URL + '/upload/' + slug;
            }
        },

        statusVariant(status) {
            const map = {
                draft: 'gray',
                pending_approval: 'warning',
                denied: 'danger',
                approved: 'success',
                sent: 'success',
            };

            return map[status] ?? 'primary';
        },

        allGrouped() {
            return [
                { key: 'drafts', label: window.__pendingLabel, items: this.drafts, variant: 'gray' },
                { key: 'awaitingApproval', label: window.__pendingApprovalLabel, items: this.awaitingApproval, variant: 'warning' },
                { key: 'denied', label: window.__deniedLabel, items: this.denied, variant: 'danger' },
                { key: 'active', label: window.__activeLabel, items: this.active, variant: 'success' },
                { key: 'expired', label: window.__expiredLabel, items: this.expired, variant: 'gray' },
            ].filter((group) => group.items.length > 0);
        },

        bundlesFromWindow() {
            const raw = typeof window.__bundles !== 'undefined' ? window.__bundles : [];

            if (Array.isArray(raw)) {
                return raw;
            }

            return Array.isArray(raw?.data) ? raw.data : [];
        },

        hasBundles() {
            return this.bundles.length > 0;
        },
    }));
}
