export function registerAlpineComponents() {
    Alpine.data('download', () => ({
        metadata: window.__bundle ?? {},
        created_at: null,
        expires_at: null,
        password: '',
        unlocking: false,
        unlockError: null,

        init() {
            this.updateTimes();
        },

        get downloadsUnlocked() {
            return ! this.metadata.password_required || this.metadata.password_unlocked;
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
                const expiresAt = this.parseExpiresAt(this.metadata.expires_at);
                this.expires_at = expiresAt != null && expiresAt.isValid() ? expiresAt.fromNow() : null;
            }
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
            if (! this.downloadsUnlocked) {
                return;
            }

            window.location.href = this.metadata.download_link;
        },

        downloadFile(file) {
            if (! this.downloadsUnlocked || ! file?.download_url) {
                return;
            }

            window.location.href = file.download_url;
        },

        unlock() {
            if (! this.metadata.unlock_url || this.unlocking) {
                return;
            }

            this.unlocking = true;
            this.unlockError = null;

            axios.post(this.metadata.unlock_url, { password: this.password })
                .then((response) => {
                    if (response.data?.result === true) {
                        this.metadata.password_unlocked = true;
                        this.password = '';
                    }
                })
                .catch((error) => {
                    this.unlockError = error.response?.data?.message
                        || window.__bundlePasswordIncorrect
                        || 'Incorrect password';
                })
                .finally(() => {
                    this.unlocking = false;
                });
        },
    }));
}
