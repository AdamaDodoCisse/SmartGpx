import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

/** Chrome interne partagé par chaque îlot outil : le bandeau « reste sur votre appareil » + un espacement cohérent. */
export function ToolPageLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <div className="mx-auto mt-6 max-w-xl">
            <p className="mb-4 text-xs text-muted-foreground">{t('tools.files_stay_on_device')}</p>
            {children}
        </div>
    );
}
