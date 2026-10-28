import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ImagePlus, RefreshCw, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import { ChoiceCard } from '@/components/choice-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn, plural } from '@/lib/utils';
import { index as creditsIndex } from '@/routes/credits';
import { create, store } from '@/routes/listings';
import type {
    Costs,
    LanguageOption,
    MarketplaceOption,
    PhotoStyleOption,
} from '@/types';

type Props = {
    marketplaces: MarketplaceOption[];
    languages: LanguageOption[];
    photoStyles: PhotoStyleOption[];
    costs: Costs;
    uploadLimits: { max_mb: number; min_dimension: number };
};

type ListingForm = {
    photo: File | null;
    name: string;
    notes: string;
    marketplaces: string[];
    languages: string[];
    photo_styles: string[];
};

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Toggle a value in a selection while keeping the options' display order.
 */
function toggle(
    selected: string[],
    value: string,
    on: boolean,
    order: { value: string }[],
): string[] {
    const next = on
        ? [...selected, value]
        : selected.filter((v) => v !== value);

    return order.map((o) => o.value).filter((v) => next.includes(v));
}

export default function CreateListing({
    marketplaces,
    languages,
    photoStyles,
    costs,
    uploadLimits,
}: Props) {
    const { credits } = usePage().props;
    const form = useForm<ListingForm>({
        photo: null,
        name: '',
        notes: '',
        marketplaces: ['trendyol'],
        languages: ['tr'],
        photo_styles: ['white_background'],
    });
    const { data, errors } = form;
    const serverErrors = errors as Record<string, string | undefined>;

    const fileInput = useRef<HTMLInputElement>(null);
    const previewUrl = useRef<string | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(
        () => () => {
            if (previewUrl.current) {
                URL.revokeObjectURL(previewUrl.current);
            }
        },
        [],
    );

    const copies = data.marketplaces.length * data.languages.length;
    const photos = data.photo_styles.length;
    const total = copies * costs.copy + photos * costs.photo;
    const balance = credits ?? 0;
    const canAfford = total <= balance;

    function choosePhoto(file: File | null | undefined) {
        form.clearErrors('photo');

        if (file && !ACCEPTED_TYPES.includes(file.type)) {
            form.setError('photo', 'Use a JPG, PNG or WebP image.');

            return;
        }

        if (file && file.size > uploadLimits.max_mb * 1024 * 1024) {
            form.setError(
                'photo',
                `The photo must be ${uploadLimits.max_mb} MB or smaller.`,
            );

            return;
        }

        if (previewUrl.current) {
            URL.revokeObjectURL(previewUrl.current);
        }

        const url = file ? URL.createObjectURL(file) : null;
        previewUrl.current = url;
        setPreview(url);
        form.setData('photo', file ?? null);
    }

    function onDrop(event: DragEvent<HTMLElement>) {
        event.preventDefault();
        setDragging(false);
        choosePhoto(event.dataTransfer.files[0]);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!data.photo) {
            form.setError('photo', 'Add a photo of your product.');

            return;
        }

        form.post(store.url(), { preserveScroll: true, forceFormData: true });
    }

    return (
        <>
            <Head title="New listing" />

            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-6xl gap-x-10 gap-y-8 p-4 md:p-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]"
                noValidate
            >
                <section
                    aria-labelledby="photo-heading"
                    className="lg:sticky lg:top-6 lg:self-start"
                >
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New listing
                    </h1>
                    <p
                        id="photo-heading"
                        className="mt-1 text-sm text-muted-foreground"
                    >
                        Start with one clear photo of the product.
                    </p>

                    <div
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={onDrop}
                        className={cn(
                            'relative mt-5 flex aspect-square w-full flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed bg-sweep text-center transition-colors',
                            dragging && 'border-primary bg-mint/50',
                            preview && 'border-solid border-transparent',
                            errors.photo && 'border-destructive/60',
                        )}
                    >
                        {preview ? (
                            <>
                                <img
                                    src={preview}
                                    alt="Selected product"
                                    className="size-full object-contain p-6"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="absolute right-3 bottom-3 shadow-sm"
                                    onClick={() => fileInput.current?.click()}
                                >
                                    <RefreshCw />
                                    Replace photo
                                </Button>
                            </>
                        ) : (
                            <button
                                type="button"
                                onClick={() => fileInput.current?.click()}
                                className="flex size-full flex-col items-center justify-center gap-3 rounded-2xl p-8 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <span className="flex size-14 items-center justify-center rounded-full bg-background text-primary shadow-sm">
                                    <ImagePlus className="size-6" />
                                </span>
                                <span className="font-medium">
                                    Drop a photo here or browse
                                </span>
                                <span className="max-w-64 text-sm text-muted-foreground">
                                    JPG, PNG or WebP up to {uploadLimits.max_mb}{' '}
                                    MB, at least {uploadLimits.min_dimension}px
                                    wide.
                                </span>
                            </button>
                        )}

                        {form.progress && (
                            <div className="absolute inset-x-0 bottom-0 h-1 bg-primary/15">
                                <div
                                    className="h-full bg-primary transition-[width]"
                                    style={{
                                        width: `${form.progress.percentage ?? 0}%`,
                                    }}
                                />
                            </div>
                        )}
                    </div>

                    <input
                        ref={fileInput}
                        type="file"
                        accept={ACCEPTED_TYPES.join(',')}
                        className="sr-only"
                        tabIndex={-1}
                        aria-label="Product photo"
                        onChange={(event) => {
                            choosePhoto(event.target.files?.[0]);
                            event.target.value = '';
                        }}
                    />
                    <InputError message={errors.photo} className="mt-2" />

                    <ul className="mt-4 space-y-1.5 text-sm text-muted-foreground">
                        <li>
                            One product per photo, filling most of the frame.
                        </li>
                        <li>Good light matters more than a good camera.</li>
                        <li>
                            Phone photos are fine. We straighten and resize
                            them.
                        </li>
                    </ul>
                </section>

                <div className="space-y-9">
                    <section className="space-y-4">
                        <SectionHeading
                            title="About the product"
                            description="Optional, but it makes the listings more accurate. The AI only states what it can see or what you tell it."
                        />

                        <div className="grid gap-2">
                            <Label htmlFor="name">Product name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="e.g. Linen summer shirt"
                                maxLength={120}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">
                                Details the photo can't show
                            </Label>
                            <textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={4}
                                maxLength={2000}
                                placeholder="Brand, material, sizes, capacity, what's in the box."
                                className="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </section>

                    <fieldset className="space-y-4">
                        <SectionHeading
                            as="legend"
                            title="Where do you sell?"
                            description="Each marketplace gets its own title length, bullet points and keyword rules."
                        />
                        <div className="grid gap-2.5 sm:grid-cols-2">
                            {marketplaces.map((marketplace) => (
                                <ChoiceCard
                                    key={marketplace.value}
                                    name="marketplaces[]"
                                    value={marketplace.value}
                                    checked={data.marketplaces.includes(
                                        marketplace.value,
                                    )}
                                    onChange={(on) =>
                                        form.setData(
                                            'marketplaces',
                                            toggle(
                                                data.marketplaces,
                                                marketplace.value,
                                                on,
                                                marketplaces,
                                            ),
                                        )
                                    }
                                >
                                    <span className="block text-sm font-medium">
                                        {marketplace.label}
                                    </span>
                                    {marketplace.hint && (
                                        <span className="block text-xs text-muted-foreground">
                                            {marketplace.hint}
                                        </span>
                                    )}
                                </ChoiceCard>
                            ))}
                        </div>
                        <InputError message={errors.marketplaces} />
                    </fieldset>

                    <fieldset className="space-y-4">
                        <SectionHeading
                            as="legend"
                            title="Languages"
                            description="Written natively for each market, not translated word for word."
                        />
                        <div className="grid grid-cols-3 gap-2.5">
                            {languages.map((language) => (
                                <ChoiceCard
                                    key={language.value}
                                    name="languages[]"
                                    value={language.value}
                                    checked={data.languages.includes(
                                        language.value,
                                    )}
                                    onChange={(on) =>
                                        form.setData(
                                            'languages',
                                            toggle(
                                                data.languages,
                                                language.value,
                                                on,
                                                languages,
                                            ),
                                        )
                                    }
                                >
                                    <span
                                        dir={language.dir}
                                        lang={language.value}
                                        className="inline-block text-sm font-medium"
                                    >
                                        {language.label}
                                    </span>
                                </ChoiceCard>
                            ))}
                        </div>
                        <InputError message={errors.languages} />
                    </fieldset>

                    <fieldset className="space-y-4">
                        <SectionHeading
                            as="legend"
                            title="New photos"
                            description={`Made from your photo. ${plural(costs.photo, 'credit')} each.`}
                        />
                        <div className="grid gap-2.5 sm:grid-cols-2">
                            {photoStyles.map((style) => (
                                <ChoiceCard
                                    key={style.value}
                                    name="photo_styles[]"
                                    value={style.value}
                                    checked={data.photo_styles.includes(
                                        style.value,
                                    )}
                                    onChange={(on) =>
                                        form.setData(
                                            'photo_styles',
                                            toggle(
                                                data.photo_styles,
                                                style.value,
                                                on,
                                                photoStyles,
                                            ),
                                        )
                                    }
                                >
                                    <span className="block text-sm font-medium">
                                        {style.label}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {style.description}
                                    </span>
                                </ChoiceCard>
                            ))}
                        </div>
                        <InputError message={errors.photo_styles} />
                    </fieldset>

                    <div className="sticky bottom-0 -mx-4 border-t bg-background/95 px-4 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <p className="text-sm">
                                <span className="text-base font-semibold tabular-nums">
                                    {plural(total, 'credit')}
                                </span>
                                <span className="block text-muted-foreground">
                                    {plural(copies, 'listing')} × {costs.copy} +{' '}
                                    {plural(photos, 'photo')} × {costs.photo}.
                                    You have {balance}.
                                </span>
                            </p>

                            <Button
                                type="submit"
                                size="lg"
                                disabled={
                                    form.processing ||
                                    !canAfford ||
                                    copies === 0
                                }
                            >
                                {form.processing ? <Spinner /> : <Sparkles />}
                                Generate listings
                            </Button>
                        </div>

                        {!canAfford && (
                            <p className="mt-2 text-sm text-destructive-foreground">
                                This needs{' '}
                                {plural(total - balance, 'more credit')}. Remove
                                an option or{' '}
                                <Link
                                    href={creditsIndex()}
                                    className="underline underline-offset-4"
                                >
                                    buy more credits
                                </Link>
                                .
                            </p>
                        )}
                        <InputError
                            message={serverErrors.credits}
                            className="mt-2"
                        />
                    </div>
                </div>
            </form>
        </>
    );
}

function SectionHeading({
    title,
    description,
    as = 'h2',
}: {
    title: string;
    description: string;
    as?: 'h2' | 'legend';
}) {
    const Tag = as;

    return (
        <Tag className="mb-4 block">
            <span className="block text-base font-semibold">{title}</span>
            <span className="mt-0.5 block text-sm font-normal text-muted-foreground">
                {description}
            </span>
        </Tag>
    );
}

CreateListing.layout = {
    breadcrumbs: [
        {
            title: 'New listing',
            href: create(),
        },
    ],
};
