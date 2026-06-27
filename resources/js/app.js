import Alpine from 'alpinejs';
window.Alpine = Alpine;

import axios from 'axios';
window.axios = axios;

import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/fr';

dayjs.extend(relativeTime);
dayjs.locale('fr');
window.dayjs = dayjs;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

window.axios.interceptors.response.use(function (response) {
	return response;
}, function (error) {
	if (error.response.status == 401) {
		window.location.href = BASE_URL+'/'
	}
	else {
		return Promise.reject(error);
	}
});

import Dropzone from "dropzone";
window.Dropzone = Dropzone;
import "dropzone/dist/dropzone.css";

import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';
window.EasyMDE = EasyMDE;

import.meta.glob([
  '../images/**',
]);

Alpine.start()
