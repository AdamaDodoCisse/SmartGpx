import { mountIsland } from '@/lib/mountIsland';
import { MobileMenu } from '@/components/layout/MobileMenu';

mountIsland('nav-mobile-menu-root', (props) => (
    <MobileMenu
        convertHref={props.convertHref ?? '/'}
        tools={JSON.parse(props.tools ?? '[]') as { href: string; label: string }[]}
        guidesHref={props.guidesHref ?? '/'}
        pricingHref={props.pricingHref ?? '/'}
        chromeExtensionHref={props.chromeExtensionHref ?? '/'}
        isAuthenticated={'1' === props.isAuthenticated}
        loginHref={props.loginHref ?? '/'}
        signupHref={props.signupHref ?? '/'}
        creditsHref={props.creditsHref ?? '/'}
        extensionsHref={props.extensionsHref ?? '/'}
        logoutHref={props.logoutHref ?? '/'}
    />
));
