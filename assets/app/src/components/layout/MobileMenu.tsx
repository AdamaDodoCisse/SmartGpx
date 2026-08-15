import { Menu } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

interface ToolLink {
    href: string;
    label: string;
}

interface MobileMenuProps {
    convertHref: string;
    tools: ToolLink[];
    guidesHref: string;
    pricingHref: string;
    chromeExtensionHref: string;
    isAuthenticated: boolean;
    loginHref: string;
    signupHref: string;
    creditsHref: string;
    extensionsHref: string;
    logoutHref: string;
}

/**
 * Seul élément interactif du header en Phase 1 (voir ADR-004) : la barre de navigation
 * "desktop" reste du Twig statique et indexable, ce composant ne gère que le menu mobile.
 * Les liens viennent tous de Twig (path()) plutôt que d'être codés en dur ici — la version
 * précédente pointait vers des chemins anglais fixes (/pricing, /guides…) même en contexte
 * français, et n'affichait que 6 liens plats sans les 9 outils accessibles depuis le menu
 * "Tools" du header desktop.
 */
export function MobileMenu({
    convertHref,
    tools,
    guidesHref,
    pricingHref,
    chromeExtensionHref,
    isAuthenticated,
    loginHref,
    signupHref,
    creditsHref,
    extensionsHref,
    logoutHref,
}: MobileMenuProps) {
    const { t } = useTranslation();

    return (
        <div className="lg:hidden">
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label={t('nav.open_menu')}>
                        <Menu className="h-5 w-5" aria-hidden="true" />
                    </Button>
                </SheetTrigger>
                <SheetContent closeLabel={t('nav.close_menu')}>
                    <SheetTitle className="mb-6 text-lg font-semibold">SmartGPX</SheetTitle>
                    <nav className="flex flex-col gap-4">
                        <a href={convertHref} className="text-base font-medium">
                            {t('nav.convert')}
                        </a>

                        <div>
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{t('nav.tools')}</p>
                            <div className="mt-2 flex flex-col gap-3 border-l border-border pl-3">
                                {tools.map((tool) => (
                                    <a key={tool.href} href={tool.href} className="text-sm">
                                        {tool.label}
                                    </a>
                                ))}
                                <a href={chromeExtensionHref} className="border-t border-border pt-3 text-sm">
                                    {t('nav.chrome_extension')}
                                </a>
                            </div>
                        </div>

                        <a href={guidesHref} className="text-base font-medium">
                            {t('nav.guides')}
                        </a>
                        <a href={pricingHref} className="text-base font-medium">
                            {t('nav.pricing')}
                        </a>

                        {isAuthenticated ? (
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{t('nav.account')}</p>
                                <div className="mt-2 flex flex-col gap-3 border-l border-border pl-3">
                                    <a href={creditsHref} className="text-sm">
                                        {t('nav.credits')}
                                    </a>
                                    <a href={extensionsHref} className="text-sm">
                                        {t('nav.extensions')}
                                    </a>
                                    <a href={logoutHref} className="text-sm">
                                        {t('nav.logout')}
                                    </a>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                <a href={loginHref} className="text-base font-medium">
                                    {t('nav.login')}
                                </a>
                                <a
                                    href={signupHref}
                                    className="inline-flex h-9 items-center justify-center rounded-md border border-primary px-4 text-sm font-medium text-primary"
                                >
                                    {t('nav.signup')}
                                </a>
                            </div>
                        )}
                    </nav>
                </SheetContent>
            </Sheet>
        </div>
    );
}
