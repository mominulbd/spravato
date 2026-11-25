import { createHmac } from 'node:crypto';

/** RFC 6238 code for a base32 secret, matching Uptms\Auth\Services\Totp. */
export function totp(secret: string, offsetSteps = 0): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const bits = secret
        .replace(/\s+/g, '')
        .toUpperCase()
        .split('')
        .map((c) => alphabet.indexOf(c).toString(2).padStart(5, '0'))
        .join('');
    const key = Buffer.from((bits.match(/.{8}/g) ?? []).map((byte) => parseInt(byte, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000) + offsetSteps));
    const hash = createHmac('sha1', key).update(counter).digest();
    const offset = (hash[19] ?? 0) & 0x0f;

    return ((hash.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).toString().padStart(6, '0');
}
