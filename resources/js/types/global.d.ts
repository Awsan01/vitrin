import type { Auth } from '@/types/auth';
import type { Business, CheckoutRequest } from '@/types/billing';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            checkout?: CheckoutRequest;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            credits: number | null;
            business: Business;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
