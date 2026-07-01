import axios from 'axios';

const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export function isAuthFailure(errorOrStatus) {
    if (typeof errorOrStatus === 'number') {
        return errorOrStatus === 401 || errorOrStatus === 419;
    }

    return errorOrStatus?.response?.status === 401 || errorOrStatus?.response?.status === 419;
}

export function redirectToLogin() {
    const returnTo = window.location.pathname + window.location.search;
    window.location.href = BASE_URL + '/login?redirect=' + encodeURIComponent(returnTo);
}

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (isAuthFailure(error)) {
            redirectToLogin();

            return Promise.reject(error);
        }

        return Promise.reject(error);
    },
);

export default axios;
