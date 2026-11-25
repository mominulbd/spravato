import { useState } from 'react';
import { Copy, Download } from 'lucide-react';
import { Button } from '@shared/ui';

/** Recovery codes, shown once: copy or download, then confirm they are saved. */
export function RecoveryCodes({ codes }: { codes: string[] }) {
    const [copied, setCopied] = useState(false);
    const text = codes.join('\n');

    return (
        <div className="flex flex-col gap-4">
            <ul aria-label="Recovery codes" className="grid grid-cols-2 gap-2 rounded-control border border-line bg-paper p-4 font-mono text-[14px] text-ink">
                {codes.map((code) => (
                    <li key={code}>{code}</li>
                ))}
            </ul>
            <div className="flex flex-wrap gap-3">
                <Button
                    variant="secondary"
                    size="sm"
                    icon={<Copy className="size-4" aria-hidden="true" />}
                    onClick={() => {
                        void navigator.clipboard?.writeText(text).then(() => setCopied(true));
                    }}
                >
                    {copied ? 'Copied' : 'Copy'}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    icon={<Download className="size-4" aria-hidden="true" />}
                    onClick={() => {
                        const url = URL.createObjectURL(new Blob([`UPTMS admin recovery codes\nEach code works once.\n\n${text}\n`], { type: 'text/plain' }));
                        const link = Object.assign(document.createElement('a'), { href: url, download: 'uptms-recovery-codes.txt' });
                        link.click();
                        URL.revokeObjectURL(url);
                    }}
                >
                    Download
                </Button>
            </div>
        </div>
    );
}
