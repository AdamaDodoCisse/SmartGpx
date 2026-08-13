// A small, standalone dictionary — not cross-imported from assets/app/src/i18n, since the
// extension is a separate build with its own bundling. Kept in manual parity with the
// convert.* / extension.* keys there for anything user-facing that overlaps in meaning.
const translations = {
    en: {
        'connect.prompt': 'Connect your SmartGPX account to export routes.',
        'connect.cta': 'Connect account',
        'not_on_maps': 'Open a Google Maps route to export it.',
        'export.cta': 'Export to GPX',
        'export.loading': 'Exporting…',
        'export.success': 'Saved to your downloads.',
        'export.another': 'Export another route',
        'credit.free': '1 free conversion available',
        'credit.remaining_one': '{{count}} credit remaining',
        'credit.remaining_other': '{{count}} credits remaining',
        'credit.get_more': 'Get more credits',
        'error.generic': 'Something went wrong. Please try again.',
        'error.reconnect': 'Your connection expired. Please reconnect.',
        'loading': 'Loading…',
    },
    fr: {
        'connect.prompt': 'Connectez votre compte SmartGPX pour exporter vos itinéraires.',
        'connect.cta': 'Connecter le compte',
        'not_on_maps': 'Ouvrez un itinéraire Google Maps pour l’exporter.',
        'export.cta': 'Exporter en GPX',
        'export.loading': 'Export en cours…',
        'export.success': 'Enregistré dans vos téléchargements.',
        'export.another': 'Exporter un autre itinéraire',
        'credit.free': '1 conversion gratuite disponible',
        'credit.remaining_one': '{{count}} crédit restant',
        'credit.remaining_other': '{{count}} crédits restants',
        'credit.get_more': 'Obtenir plus de crédits',
        'error.generic': 'Une erreur est survenue. Merci de réessayer.',
        'error.reconnect': 'Votre connexion a expiré. Merci de vous reconnecter.',
        'loading': 'Chargement…',
    },
} as const;

export type TranslationKey = keyof (typeof translations)['en'];

function resolveLocale(): keyof typeof translations {
    return chrome.i18n.getUILanguage().toLowerCase().startsWith('fr') ? 'fr' : 'en';
}

export function t(key: TranslationKey, params?: Record<string, string | number>): string {
    const template: string = translations[resolveLocale()][key];

    if (undefined === params) {
        return template;
    }

    return Object.entries(params).reduce<string>(
        (result, [name, value]) => result.replaceAll(`{{${name}}}`, String(value)),
        template,
    );
}

export function creditLine(creditBalance: number, hasEverConverted: boolean): string {
    if (0 === creditBalance) {
        return t('credit.remaining_other', { count: 0 });
    }

    if (!hasEverConverted) {
        return t('credit.free');
    }

    return t(1 === creditBalance ? 'credit.remaining_one' : 'credit.remaining_other', { count: creditBalance });
}
