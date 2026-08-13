import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en/common.json';
import fr from './locales/fr/common.json';

const htmlLocale = document.documentElement.lang;
const initialLocale = 'fr' === htmlLocale ? 'fr' : 'en';

void i18n
    .use(initReactI18next)
    .init({
        resources: {
            en: { common: en },
            fr: { common: fr },
        },
        lng: initialLocale,
        fallbackLng: 'en',
        defaultNS: 'common',
        interpolation: { escapeValue: false },
    });

export default i18n;
