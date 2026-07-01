export function registerAlpineComponents() {
    Alpine.data('review', () => ({
        denyForm: false,
        reason: '',
        error: null,
        loading: false,
        modal: { show: false, text: '', action: null },

        showModal(text, action) {
            this.modal.text = text;
            this.modal.action = action;
            this.modal.show = true;
        },

        confirmModal() {
            this.modal.show = false;
            if (this.modal.action) {
                this.modal.action();
            }
        },

        approve() {
            this.showModal(window.__confirmApprove, () => {
                this.loading = true;
                this.error = null;

                axios.post(window.__approveUrl)
                    .then(() => {
                        window.location.href = window.__approvalIndexUrl;
                    })
                    .catch((error) => {
                        if (window.isAuthFailure?.(error)) {
                            return;
                        }

                        this.error = error.response?.data?.message ?? window.__unexpectedError;
                        this.loading = false;
                    });
            });
        },

        submitDeny() {
            if (! this.reason || this.reason.trim().length < 3) {
                this.error = window.__denyReasonRequired;
                return;
            }

            this.showModal(window.__confirmDeny, () => {
                this.loading = true;
                this.error = null;

                axios.post(window.__denyUrl, { reason: this.reason })
                    .then(() => {
                        window.location.href = window.__approvalIndexUrl;
                    })
                    .catch((error) => {
                        if (window.isAuthFailure?.(error)) {
                            return;
                        }

                        this.error = error.response?.data?.message ?? window.__unexpectedError;
                        this.loading = false;
                    });
            });
        },
    }));
}
