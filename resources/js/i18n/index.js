import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './en.json';
import ms from './ms.json';

const KEY = 'cas:locale';

const stored = () => {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
};

i18n.use(initReactI18next).init({
    resources: { en: { translation: en }, ms: { translation: ms } },
    lng: stored() === 'ms' ? 'ms' : 'en',
    fallbackLng: 'en',
    interpolation: { escapeValue: false },
});

export const setLocale = (lng) => {
    i18n.changeLanguage(lng);
    try {
        localStorage.setItem(KEY, lng);
    } catch {
        // storage unavailable: the language still switches for this session
    }
};

export default i18n;
