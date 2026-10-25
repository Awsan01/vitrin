import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A checkbox styled as a selectable card. It stays a real checkbox, so it
 * works with the keyboard and screen readers.
 */
export function ChoiceCard({
    name,
    value,
    checked,
    onChange,
    children,
    className,
}: {
    name: string;
    value: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    children: ReactNode;
    className?: string;
}) {
    return (
        <label
            className={cn(
                'relative flex cursor-pointer items-start gap-3 rounded-lg border bg-background p-3 transition-colors',
                'hover:border-foreground/25 has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                checked && 'border-primary bg-mint/40 hover:border-primary',
                className,
            )}
        >
            <input
                type="checkbox"
                name={name}
                value={value}
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="sr-only"
            />
            <span
                aria-hidden="true"
                className={cn(
                    'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-[4px] border transition-colors',
                    checked
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-input bg-background',
                )}
            >
                {checked && <Check className="size-3" strokeWidth={3} />}
            </span>
            <span className="min-w-0 flex-1">{children}</span>
        </label>
    );
}
