import Dropzone from 'dropzone';
import EasyMDE from 'easymde';
import 'dropzone/dist/dropzone.css';
import 'easymde/dist/easymde.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import axios from './lib/http.js';
import dayjs from './lib/dayjs-config.js';
import { registerUploadWizard } from './pages/upload-wizard.js';

window.Dropzone = Dropzone;
window.EasyMDE = EasyMDE;
window.axios = axios;
window.dayjs = dayjs;

document.addEventListener('alpine:init', () => {
    registerUploadWizard();
});
