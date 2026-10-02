import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            portalUrl: string;
            auth: Auth;
            impersonator: { name: string; email: string } | null;
            canReviewTrials: boolean;
            locale: 'id' | 'en' | 'ko';
            translations: Record<string, string>;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
