import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

export function CopyButton({
    text,
    label = 'Copy',
    className,
}: {
    text: string;
    label?: string;
    className?: string;
}) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), 1600);

        return () => window.clearTimeout(timer);
    }, [copied]);

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={text.trim() === ''}
            onClick={async () => setCopied(await copy(text))}
            className={cn('h-7 gap-1.5 px-2 text-xs', className)}
        >
            {copied ? <Check className="text-primary" /> : <Copy />}
            <span aria-live="polite">{copied ? 'Copied' : label}</span>
        </Button>
    );
}
