import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createQueryClient } from '@shared/api/queryClient';
import { ToastProvider } from '@shared/ui';
import { AuthProvider } from '@admin/app/AuthProvider';
import { LoginPage } from './LoginPage';

const user = {
    id: 1, uuid: 'u', name: 'Joy Kiongo', email: 'joy@uptms.test', role: { slug: 'staff', name: 'Staff' }, is_super_admin: false,
    permissions: ['admin.access'], two_factor: { available: true, enabled: true, required: true }, session: { idle_timeout_minutes: 30 },
};

function json(status: number, body: unknown) {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const ok = (data: unknown) => json(200, { success: true, message: 'OK', data, meta: {} });

function renderLogin() {
    const router = createMemoryRouter(
        [
            { path: '/login', element: <LoginPage /> },
            { path: '/users', element: <p>Users page</p> },
        ],
        { initialEntries: ['/login?next=%2Fusers'] },
    );

    render(
        <QueryClientProvider client={createQueryClient()}>
            <ToastProvider>
                <AuthProvider>
                    <RouterProvider router={router} />
                </AuthProvider>
            </ToastProvider>
        </QueryClientProvider>,
    );
}

/** Routes fetch calls by URL; /auth/me starts signed out. */
function mockApi(handlers: Record<string, () => Response>) {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
        const url = String(input);
        if (url.includes('csrf-cookie')) return new Response(null, { status: 204 });
        if (url.endsWith('/auth/me')) return json(401, { success: false, message: 'x', error_code: 'UNAUTHENTICATED', errors: {}, meta: {} });
        const key = Object.keys(handlers).find((path) => url.endsWith(path));

        return key ? (handlers[key] as () => Response)() : json(404, {});
    });
}

beforeEach(() => vi.restoreAllMocks());
afterEach(() => vi.restoreAllMocks());

describe('LoginPage', () => {
    it('validates before sending', async () => {
        mockApi({});
        renderLogin();

        await userEvent.click(await screen.findByRole('button', { name: 'Sign in' }));

        expect(screen.getByLabelText('Email address')).toHaveAccessibleDescription('Enter an email address.');
        expect(screen.getByLabelText('Password')).toHaveAttribute('aria-invalid', 'true');
        expect(fetch).not.toHaveBeenCalledWith('/api/v1/auth/login', expect.anything());
    });

    it('asks for the 2FA code, then follows ?next', async () => {
        mockApi({
            '/auth/login': () => ok({ two_factor: true }),
            '/auth/two-factor-challenge': () => ok({ two_factor: false, user }),
        });
        renderLogin();

        await userEvent.type(await screen.findByLabelText('Email address'), 'joy@uptms.test');
        await userEvent.type(screen.getByLabelText('Password'), 'password');
        await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        await userEvent.type(await screen.findByLabelText('Authentication code'), '123456');
        await userEvent.click(screen.getByRole('button', { name: 'Verify and sign in' }));

        expect(await screen.findByText('Users page')).toBeInTheDocument();
    });

    it('shows a neutral message for wrong credentials', async () => {
        mockApi({ '/auth/login': () => json(422, { success: false, message: 'x', error_code: 'INVALID_CREDENTIALS', errors: {}, meta: {} }) });
        renderLogin();

        await userEvent.type(await screen.findByLabelText('Email address'), 'joy@uptms.test');
        await userEvent.type(screen.getByLabelText('Password'), 'nope');
        await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('That email and password don’t match an account.');
    });

    it('offers a recovery code instead of the app code', async () => {
        mockApi({ '/auth/login': () => ok({ two_factor: true }) });
        renderLogin();

        await userEvent.type(await screen.findByLabelText('Email address'), 'joy@uptms.test');
        await userEvent.type(screen.getByLabelText('Password'), 'password');
        await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));
        await userEvent.click(await screen.findByRole('button', { name: 'Use a recovery code' }));

        expect(screen.getByLabelText('Recovery code')).toBeInTheDocument();
    });
});
