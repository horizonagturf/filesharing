import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/de';
import 'dayjs/locale/fr';
import 'dayjs/locale/en';

dayjs.extend(relativeTime);

const localeMap = {
    en: 'en',
    de: 'de',
    fr: 'fr',
    kr: 'en',
};

const locale = localeMap[typeof APP_LOCALE !== 'undefined' ? APP_LOCALE : 'en'] ?? 'en';
dayjs.locale(locale);

export default dayjs;
