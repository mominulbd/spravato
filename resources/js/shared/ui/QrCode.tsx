import { useMemo } from 'react';
import qrcode from 'qrcode-generator';

/** A QR code (error correction M) drawn as one SVG path, with the quiet zone scanners need. */
export function qrPath(text: string, quietZone = 4): { size: number; path: string } {
    const code = qrcode(0, 'M');
    code.addData(text);
    code.make();

    const count = code.getModuleCount();
    let path = '';

    for (let row = 0; row < count; row++) {
        for (let col = 0; col < count; col++) {
            if (code.isDark(row, col)) path += `M${col + quietZone} ${row + quietZone}h1v1h-1z`;
        }
    }

    return { size: count + quietZone * 2, path };
}

export function QrCode({ value, label, size = 184 }: { value: string; label: string; size?: number }) {
    const symbol = useMemo(() => qrPath(value), [value]);

    return (
        <svg role="img" aria-label={label} viewBox={`0 0 ${symbol.size} ${symbol.size}`} width={size} height={size} shapeRendering="crispEdges" className="rounded-control border border-line">
            <rect width={symbol.size} height={symbol.size} fill="#fff" />
            <path d={symbol.path} fill="#0e304e" />
        </svg>
    );
}
