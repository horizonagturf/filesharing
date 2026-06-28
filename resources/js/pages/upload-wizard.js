export function registerUploadWizard() {
    const config = window.__uploadConfig ?? {};

    Alpine.data('upload', () => ({
        bundle: null,
        dropzone: null,
        easymde: null,
        completed: false,
        step: 0,
        maxFiles: config.maxFiles ?? 0,
        copynotify: {
            preview: false,
            direct_download: false,
        },
        modal: {
            show: false,
            text: '',
            callback: null,
        },
        steps: config.steps ?? [],

        init() {
            this.bundle = config.bundle;

            if (! this.bundle.share_mode) {
                this.bundle.share_mode = config.invitationMode ? 'invitation' : 'static_link';
            }

            if (config.invitationMode && ! this.bundle.recipients) {
                this.bundle.recipients = [];
            }

            if (config.invitationMode && this.bundle.recipients?.length > 0) {
                this.bundle.recipients_text = this.bundle.recipients.map((r) => r.email).join('\n');
            } else if (config.invitationMode) {
                this.bundle.recipients_text = '';
            }

            if (this.bundle.status === 'pending_approval' || this.bundle.status === 'approved' || this.bundle.status === 'sent' || (this.bundle.completed === true && this.bundle.status !== 'denied')) {
                this.step = 3;
            } else if (this.bundle.title) {
                this.step = 2;
                if (this.bundle.is_editable !== false) {
                    this.startDropzone();
                }
            } else {
                this.step = 1;
            }

            this.easymde = new EasyMDE({
                element: document.getElementById('upload-description'),
                maxHeight: '50px',
                forceSync: false,
                initialValue: this.bundle.description ?? '',
                spellChecker: false,
                status: false,
                autofocus: false,
                autoDownloadFontAwesome: false,
            });
        },

        isBlockedFilename(name) {
            const parts = name.split('.');
            if (parts.length <= 1) {
                return false;
            }

            parts.shift();
            const blocked = new Set((config.blockedExtensions ?? []).map((ext) => ext.toLowerCase()));

            return parts.some((part) => blocked.has(part.toLowerCase()));
        },

        isInvitationMode() {
            return this.bundle.share_mode === 'invitation';
        },

        setShareMode(mode) {
            this.bundle.share_mode = mode;
            config.invitationMode = mode === 'invitation';
            if (config.invitationMode && ! this.bundle.recipients) {
                this.bundle.recipients = [];
                this.bundle.recipients_text = '';
            }
        },

        uploadStep() {
            let errors = null;
            ['upload-title', 'upload-description', 'upload-expiry', 'upload-password', 'upload-max-downloads'].forEach((id) => {
                document.getElementById(id)?.setCustomValidity('');
            });

            if (this.isInvitationMode()) {
                document.getElementById('upload-recipients')?.setCustomValidity('');
                const emails = this.parseRecipients(this.bundle.recipients_text || '');
                if (emails.length === 0) {
                    document.getElementById('upload-recipients')?.setCustomValidity('Field is required');
                    errors = true;
                }
            }

            if (! this.bundle.title) {
                document.getElementById('upload-title')?.setCustomValidity('Field is required');
                errors = true;
            }

            if (this.bundle.expiry == null || this.bundle.expiry === '') {
                document.getElementById('upload-expiry')?.setCustomValidity('Field is required');
                errors = true;
            }

            if (this.bundle.max_downloads < 0 || this.bundle.max_downloads > 999) {
                document.getElementById('upload-max-downloads')?.setCustomValidity('Invalid number of max downloads');
                errors = true;
            }

            if (errors === true) {
                return false;
            }

            axios({
                url: BASE_URL + '/upload/' + this.bundle.slug,
                method: 'POST',
                data: {
                    expiry: this.bundle.expiry,
                    title: this.bundle.title,
                    description: this.easymde.value(),
                    max_downloads: this.bundle.max_downloads,
                    password: this.bundle.password,
                    recipients: this.isInvitationMode() ? this.parseRecipients(this.bundle.recipients_text || '') : [],
                    share_mode: this.bundle.share_mode,
                    auth: this.bundle.owner_token,
                },
            })
                .then((response) => {
                    this.syncData(response.data);
                    this.step = 2;
                    this.startDropzone();
                })
                .catch(() => {});
        },

        completeStep() {
            if (Object.keys(this.bundle.files).length === 0) {
                return false;
            }

            const confirmText = this.bundle.requires_approval
                ? config.confirmCompleteApproval
                : config.confirmCompleteDirect;

            this.showModal(confirmText, () => {
                axios({
                    url: BASE_URL + '/upload/' + this.bundle.slug + '/complete',
                    method: 'POST',
                    data: {
                        auth: this.bundle.owner_token,
                    },
                })
                    .then((response) => {
                        this.step = 3;
                        this.syncData(response.data);
                    })
                    .catch(() => {});
            });
        },

        back() {
            if (this.step > 1) {
                this.step--;
            }
        },

        startDropzone() {
            if (this.dropzone) {
                return;
            }

            this.maxFiles = this.maxFiles - this.countFilesOnServer() >= 0
                ? this.maxFiles - this.countFilesOnServer()
                : 0;

            this.dropzone = new Dropzone('#upload-frm', {
                url: BASE_URL + '/upload/' + this.bundle.slug + '/file',
                method: 'POST',
                headers: {
                    'X-Upload-Auth': this.bundle.owner_token,
                },
                createImageThumbnails: false,
                disablePreviews: true,
                clickable: true,
                paramName: 'file',
                maxFiles: config.maxFiles,
                maxFilesize: (config.maxFileSize / 1000000),
                parallelUploads: 1,
                dictMaxFilesExceeded: config.dictMaxFilesExceeded,
                dictFileTooBig: config.dictFileTooBig,
                dictDefaultMessage: config.dictDefaultMessage,
                dictResponseError: config.dictResponseError,
                accept: (file, done) => {
                    if (this.isBlockedFilename(file.name)) {
                        done(config.fileTypeBlockedMessage);
                    } else {
                        done();
                    }
                },
            });

            this.dropzone.on('addedfile', (file) => {
                file.uuid = this.uuid();

                this.bundle.files.unshift({
                    uuid: file.uuid,
                    original: file.name,
                    filesize: file.size,
                    fullpath: '',
                    filename: file.name,
                    created_at: dayjs().unix(),
                    status: 'uploading',
                });
            });

            this.dropzone.on('sending', (file, xhr, formData) => {
                formData.append('uuid', file.uuid);
                const csrf = document.head.querySelector('meta[name="csrf-token"]');
                if (csrf) {
                    formData.append('_token', csrf.content);
                }
            });

            this.dropzone.on('uploadprogress', (file, progress) => {
                const fileIndex = this.findFileIndex(file.uuid);
                if (fileIndex !== null) {
                    this.bundle.files[fileIndex].progress = Math.round(progress);
                }
            });

            this.dropzone.on('error', (file, message) => {
                const fileIndex = this.findFileIndex(file.uuid);
                if (fileIndex === null) {
                    return;
                }

                this.bundle.files[fileIndex].status = false;
                this.bundle.files[fileIndex].message = message?.message ?? message;
            });

            this.dropzone.on('complete', (file) => {
                const fileIndex = this.findFileIndex(file.uuid);
                if (fileIndex === null) {
                    return;
                }

                this.bundle.files[fileIndex].progress = 0;

                if (file.status === 'success') {
                    this.maxFiles--;
                    this.bundle.files[fileIndex].status = true;
                }
            });
        },

        deleteFile(file) {
            if (file.status === true) {
                this.showModal(config.confirmDelete, () => {
                    axios({
                        url: BASE_URL + '/upload/' + this.bundle.slug + '/file',
                        method: 'DELETE',
                        data: {
                            uuid: file.uuid,
                            auth: this.bundle.owner_token,
                        },
                    })
                        .then((response) => {
                            this.syncData(response.data);
                        })
                        .catch(() => {});
                });
            } else if (file.status === false) {
                const fileIndex = this.findFileIndex(file.uuid);
                if (fileIndex !== null) {
                    this.bundle.files.splice(fileIndex, 1);
                }
            }
        },

        deleteBundle() {
            this.showModal(config.confirmDeleteBundle, () => {
                axios({
                    url: BASE_URL + '/upload/' + this.bundle.slug + '/delete',
                    method: 'DELETE',
                    data: {
                        auth: this.bundle.owner_token,
                    },
                })
                    .then(() => {
                        window.location.href = '/';
                    })
                    .catch(() => {});
            });
        },

        findFileIndex(uuid) {
            for (const i in this.bundle.files) {
                if (this.bundle.files[i].uuid === uuid) {
                    return i;
                }
            }

            return null;
        },

        syncData(bundle) {
            if (Object.keys(bundle).length > 0) {
                this.bundle = bundle;
                if (bundle.share_mode) {
                    config.invitationMode = bundle.share_mode === 'invitation';
                }
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

        uuid() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        showModal(text, callback) {
            this.modal.text = text;
            this.modal.callback = callback;
            this.modal.show = true;
        },

        confirmModal() {
            this.modal.show = false;
            if (this.modal.callback) {
                this.modal.callback();
            }
        },

        selectCopy(el) {
            el.select();

            if (! navigator.clipboard) {
                return;
            }

            navigator.clipboard.writeText(el.value).then(() => {
                const key = el.id === 'copy-preview' ? 'preview' : 'direct_download';
                if (this.copynotify[key] === false) {
                    this.copynotify[key] = true;
                    setTimeout(() => {
                        this.copynotify[key] = false;
                    }, 2000);
                }
            });
        },

        parseExpiresAt(expiresAt) {
            if (expiresAt == null || expiresAt === '') {
                return null;
            }

            const parsed = dayjs(expiresAt);
            return parsed.isValid() ? parsed : null;
        },

        isBundleExpired() {
            const expiresAt = this.parseExpiresAt(this.bundle.expires_at);
            if (expiresAt == null || ! expiresAt.isValid()) {
                return false;
            }

            return expiresAt.isBefore(dayjs());
        },

        parseRecipients(text) {
            return text
                .split(/[\s,;]+/)
                .map((email) => email.trim().toLowerCase())
                .filter((email) => email.length > 0);
        },

        resendInvitation(recipient) {
            axios({
                url: BASE_URL + '/upload/' + this.bundle.slug + '/recipients/' + recipient.id + '/resend',
                method: 'POST',
                data: {
                    auth: this.bundle.owner_token,
                },
            })
                .then(() => {
                    this.showModal(config.invitationResent, () => {});
                });
        },

        countFilesOnServer() {
            let count = 0;

            if (this.bundle?.files && Object.keys(this.bundle.files).length > 0) {
                for (const i in this.bundle.files) {
                    if (this.bundle.files[i].status === true) {
                        count++;
                    }
                }
            }

            return count;
        },
    }));
}
