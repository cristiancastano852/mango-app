import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import es from '@/locales/es.json';

export type MessageSchema = typeof es;

const i18n = createI18n<[MessageSchema], 'es' | 'en'>({
    legacy: false,
    locale: 'es',
    fallbackLocale: 'en',
    messages: {
        es,
        en,
    },
});

export default i18n;
