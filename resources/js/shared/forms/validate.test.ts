import { describe, expect, it } from 'vitest';
import { emailError, firstErrors, hasErrors, newPasswordErrors, safeNext, totpError } from './validate';

describe('form validation', () => {
    it('checks emails', () => {
        expect(emailError('')).toBe('Enter an email address.');
        expect(emailError('joy@')).toMatch(/valid email/);
        expect(emailError(' joy@uptms.com ')).toBeUndefined();
    });

    it('mirrors the server password rule', () => {
        expect(newPasswordErrors('short1', 'short1').password).toBe('Use at least 12 characters.');
        expect(newPasswordErrors('123456789012', '123456789012').password).toBe('Include at least one letter.');
        expect(newPasswordErrors('abcdefghijkl', 'abcdefghijkl').password).toBe('Include at least one number.');
        expect(newPasswordErrors('abcdefghijk1', 'abcdefghijk2')).toEqual({ password: undefined, password_confirmation: 'The passwords don’t match.' });
        expect(hasErrors(newPasswordErrors('abcdefghijk1', 'abcdefghijk1'))).toBe(false);
    });

    it('accepts six-digit codes with spaces', () => {
        expect(totpError('123 456')).toBeUndefined();
        expect(totpError('12345')).toBeDefined();
    });

    it('takes the first server message per field', () => {
        expect(firstErrors({ email: ['Taken.', 'Other'] })).toEqual({ email: 'Taken.' });
    });

    it('never follows an off-site next', () => {
        expect(safeNext('/users?page=2')).toBe('/users?page=2');
        expect(safeNext('//evil.example')).toBe('/');
        expect(safeNext('https://evil.example')).toBe('/');
        expect(safeNext(null)).toBe('/');
    });
});
