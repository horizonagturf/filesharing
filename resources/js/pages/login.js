import axios from '../lib/http.js';

export function registerAlpineComponents() {
    Alpine.data('login', () => ({
        user: {
            login: null,
            password: null,
        },
        error: null,
        loading: false,

        loginUser() {
            let errors = false;
            document.getElementById('user-login').setCustomValidity('');
            document.getElementById('user-password').setCustomValidity('');

            if (! this.user.login) {
                document.getElementById('user-login').setCustomValidity('Field is required');
                errors = true;
            }

            if (! this.user.password) {
                document.getElementById('user-password').setCustomValidity('Field is required');
                errors = true;
            }

            if (errors) {
                return false;
            }

            this.loading = true;
            this.error = null;

            axios({
                url: BASE_URL + '/login',
                method: 'POST',
                data: {
                    login: this.user.login,
                    password: this.user.password,
                },
            })
                .then((response) => {
                    if (response.data.result === true) {
                        const params = new URLSearchParams(window.location.search);
                        const redirect = params.get('redirect');

                        if (redirect && redirect.startsWith('/') && ! redirect.startsWith('//')) {
                            window.location.href = BASE_URL + redirect;
                        } else {
                            window.location.href = BASE_URL + '/';
                        }
                    }
                })
                .catch((error) => {
                    this.error = error.response?.data?.message ?? 'Login failed';
                })
                .finally(() => {
                    this.loading = false;
                });
        },
    }));
}
