import { Menu } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

interface NavLink {
    href: string;
    labelKey: string;
}

const NAV_LINKS: NavLink[] = [
    { href: '/#free-tools', labelKey: 'nav.tools' },
    { href: '/guides', labelKey: 'nav.guides' },
    { href: '/pricing', labelKey: 'nav.pricing' },
    { href: '/login', labelKey: 'nav.login' },
];

/**
 * Seul élément interactif du header en Phase 1 (voir ADR-004) : la barre de navigation
 * "desktop" reste du Twig statique et indexable, ce composant ne gère que le menu mobile.
 */
export function MobileMenu() {
    const { t } = useTranslation();

    return (
        <div className="md:hidden">
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label={t('nav.open_menu')}>
                        <Menu className="h-5 w-5" aria-hidden="true" />
                    </Button>
                </SheetTrigger>
                <SheetContent closeLabel={t('nav.close_menu')}>
                    <SheetTitle className="mb-6 text-lg font-semibold">SmartGPX</SheetTitle>
                    <nav className="flex flex-col gap-4">
                        {NAV_LINKS.map((link) => (
                            <a key={link.href} href={link.href} className="text-base font-medium">
                                {t(link.labelKey)}
                            </a>
                        ))}
                    </nav>
                </SheetContent>
            </Sheet>
        </div>
    );
}
