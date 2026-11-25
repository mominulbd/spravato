import '@fontsource-variable/source-serif-4';
import '@fontsource-variable/source-serif-4/wght-italic.css';
import '@fontsource-variable/nunito';
import '@fontsource-variable/nunito-sans';
import '../../css/app.css';
import { createQueryClient } from '@shared/api/queryClient';
import { ToastProvider } from '@shared/ui';
import { QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { AuthProvider } from './app/AuthProvider';
import { handleApiError } from './app/handleApiError';
import { notifier } from './app/Root';
import { router } from './app/router';

const queryClient = createQueryClient((error, source) => handleApiError(error, source, notifier));

const root = document.getElementById('app');

if (root === null) {
    throw new Error('The #app element is missing from the page.');
}

createRoot(root).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <ToastProvider>
                <AuthProvider>
                    <RouterProvider router={router} />
                </AuthProvider>
            </ToastProvider>
        </QueryClientProvider>
    </StrictMode>,
);
