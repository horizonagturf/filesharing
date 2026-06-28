export function registerAlpineComponents() {
    Alpine.data('download', () => ({
        metadata: window.__bundle ?? {},
        created_at: null,
        expires_at: null,
        expired: false,
        interval: null,

        init() {
            this.updateTimes();

            this.interval = window.setInterval(() => {
                this.updateTimes();
            }, 5000);
        },

        parseExpiresAt(expiresAt) {
            if (expiresAt == null || expiresAt === '') {
                return null;
            }

            const parsed = dayjs(expiresAt);
            return parsed.isValid() ? parsed : null;
        },

        updateTimes() {
            this.created_at = dayjs(this.metadata.created_at).fromNow();

            if (this.metadata.expiry) {
                if (! this.isExpired()) {
                    const expiresAt = this.parseExpiresAt(this.metadata.expires_at);
                    this.expires_at = expiresAt != null && expiresAt.isValid() ? expiresAt.fromNow() : null;
                }
            }
        },

        isExpired() {
            const expiresAt = this.parseExpiresAt(this.metadata.expires_at);
            if (expiresAt == null || ! expiresAt.isValid()) {
                this.expired = false;
                return false;
            }

            this.expired = dayjs().isAfter(expiresAt);
            return this.expired;
        },

        humanSize(val) {
            if (val >= 100000000) {
                return (val / 1000000000).toFixed(1) + ' Go';
            }
            if (val >= 1000000) {
                return (val / 1000000).toFixed(1) + ' Mo';
            }
            if (val >= 1000) {
                return (val / 1000).toFixed(1) + ' Ko';
            }

            return val + ' o';
        },

        downloadAll() {
            window.location.href = this.metadata.download_link;
        },
    }));
}
