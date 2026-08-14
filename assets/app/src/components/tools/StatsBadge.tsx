import { useTranslation } from 'react-i18next';

interface StatsBadgeProps {
    before: number;
    after: number;
}

export function StatsBadge({ before, after }: StatsBadgeProps) {
    const { t } = useTranslation();
    const reduction = before > 0 ? Math.round((1 - after / before) * 100) : 0;

    return (
        <p className="mt-3 text-sm text-muted-foreground">
            {t('tools.gpx_simplify.stats', { before, after, reduction })}
        </p>
    );
}
