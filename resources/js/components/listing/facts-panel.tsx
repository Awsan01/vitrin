import { CircleAlert, Lightbulb } from 'lucide-react';
import type { ProductFacts } from '@/types';

/**
 * What the AI found in the photo, so the seller can check it before
 * publishing. Attributes it couldn't confirm are called out explicitly.
 */
export function FactsPanel({ facts }: { facts: ProductFacts }) {
    const rows: [string, string | null][] = [
        ['Brand', facts.brand],
        ['Colors', facts.colors.join(', ') || null],
        ['Materials', facts.materials.join(', ') || null],
        ['Size', facts.size_or_dimensions],
        ['Quantity', facts.quantity],
        ['For', facts.target_audience],
    ];

    return (
        <section aria-labelledby="facts-heading" className="space-y-4">
            <div>
                <h2 id="facts-heading" className="font-semibold">
                    What we found in the photo
                </h2>
                <p className="text-sm text-muted-foreground">
                    Every listing is written from these facts.
                </p>
            </div>

            <div className="rounded-xl border p-4">
                <p className="font-medium first-letter:uppercase">
                    {facts.product_type}
                </p>
                <p className="text-sm text-muted-foreground">
                    {facts.category_path}
                </p>

                <dl className="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    {rows
                        .filter(([, value]) => value)
                        .map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="first-letter:uppercase">
                                    {value}
                                </dd>
                            </div>
                        ))}
                </dl>

                {facts.key_features.length > 0 && (
                    <ul className="mt-4 list-disc space-y-1 ps-5 text-sm marker:text-muted-foreground">
                        {facts.key_features.map((feature) => (
                            <li
                                key={feature}
                                className="first-letter:uppercase"
                            >
                                {feature}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {facts.needs_confirmation.length > 0 && (
                <div className="rounded-xl bg-saffron-wash p-4 text-sm">
                    <p className="flex items-center gap-2 font-medium text-saffron">
                        <CircleAlert className="size-4" />
                        Check before publishing
                    </p>
                    <p className="mt-1 text-foreground/80">
                        The photo can't prove these, so the listings leave them
                        out unless you mentioned them:
                    </p>
                    <ul className="mt-2 list-disc space-y-0.5 ps-5">
                        {facts.needs_confirmation.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}

            {facts.photo_tips.length > 0 && (
                <div className="rounded-xl border border-dashed p-4 text-sm">
                    <p className="flex items-center gap-2 font-medium">
                        <Lightbulb className="size-4 text-saffron" />
                        For a better result next time
                    </p>
                    <ul className="mt-2 list-disc space-y-0.5 ps-5 text-muted-foreground">
                        {facts.photo_tips.map((tip) => (
                            <li key={tip}>{tip}</li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
