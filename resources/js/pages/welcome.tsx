import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { charLength } from '@/components/char-count';
import { TeaGlass, TeaGlassTop } from '@/components/marketing/tea-glass';
import { Button } from '@/components/ui/button';
import { cn, plural } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import type { Costs } from '@/types';

type Sample = {
    market: string;
    language: string;
    lang: string;
    dir: 'ltr' | 'rtl';
    max: number;
    title: string;
    keywords: string[];
};

const samples: Sample[] = [
    {
        market: 'Trendyol',
        language: 'Türkçe',
        lang: 'tr',
        dir: 'ltr',
        max: 100,
        title: "İnce Belli Çay Bardağı 6'lı Set, Tabaklı, El Yapımı Cam",
        keywords: ['çay bardağı', 'ince belli', "6'lı set"],
    },
    {
        market: 'noon',
        language: 'العربية',
        lang: 'ar',
        dir: 'rtl',
        max: 160,
        title: 'طقم أكواب شاي تركية زجاجية 6 قطع مع صحون',
        keywords: ['أكواب شاي', 'شاي تركي', 'طقم ضيافة'],
    },
    {
        market: 'Amazon',
        language: 'English',
        lang: 'en',
        dir: 'ltr',
        max: 75,
        title: 'Turkish Tea Glasses Set of 6 with Saucers, Hand-Blown Glass',
        keywords: ['tea glasses', 'turkish tea set', 'glass cups'],
    },
];

const marketplaceRules: [string, string][] = [
    [
        'Amazon',
        'Titles of 75 characters or fewer, plus a 125-character item highlights line.',
    ],
    [
        'noon',
        'No brand or color in the title, and between 20 and 160 characters.',
    ],
    [
        'Hepsiburada',
        'Brand, product, model, feature and quantity, in that order.',
    ],
    ['Trendyol', 'The first 30 characters say exactly what the product is.'],
    ['Etsy', 'Thirteen tags of up to 20 characters each.'],
    [
        'Your own store',
        'An SEO title and meta description that fit in search results.',
    ],
];

const steps: [string, string][] = [
    [
        'Upload one photo',
        'Any clear photo of a single product. A phone photo is fine.',
    ],
    [
        'Choose where you sell',
        'Pick marketplaces and languages: Turkish, Arabic, English or all three.',
    ],
    [
        'Copy and publish',
        'Every field has a copy button, or export everything as a CSV.',
    ],
];

type Frame = {
    label: string;
    className: string;
    style?: CSSProperties;
    image: ReactNode;
};

const frames: Frame[] = [
    {
        label: 'White background',
        className: 'border bg-white',
        image: <TeaGlass className="h-[78%]" aria-hidden="true" />,
    },
    {
        label: 'Lifestyle scene',
        className: 'bg-[#EDE3D6]',
        style: {
            backgroundImage:
                'radial-gradient(circle at 78% 18%, rgba(255,255,255,0.85), transparent 38%), linear-gradient(to bottom, transparent 64%, #B98A5C 64%, #8F653F 100%)',
        },
        image: (
            <TeaGlass
                className="h-[62%] translate-y-[12%]"
                aria-hidden="true"
            />
        ),
    },
    {
        label: 'Close-up detail',
        className: 'bg-[#F4F5F4]',
        image: (
            <TeaGlass
                viewBox="62 28 116 145"
                preserveAspectRatio="xMidYMid slice"
                className="size-full"
                aria-hidden="true"
            />
        ),
    },
    {
        label: 'New angle',
        className: 'bg-[#E9ECEA]',
        image: <TeaGlassTop className="h-[86%]" aria-hidden="true" />,
    },
];

export default function Welcome({
    signupCredits,
    costs,
}: {
    signupCredits: number;
    costs: Costs;
}) {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Marketplace listings from one product photo" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 md:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <AppLogoIcon className="size-7 fill-primary" />
                        <span className="text-lg font-semibold tracking-tight">
                            {name}
                        </span>
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>
                                    Open your listings
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto grid max-w-6xl items-center gap-12 px-5 pt-10 pb-20 md:px-8 md:pt-16 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-16">
                        <div>
                            <h1 className="text-[2.6rem] leading-[1.04] font-semibold tracking-[-0.035em] text-balance md:text-6xl">
                                One product photo. Listings for every
                                marketplace.
                            </h1>
                            <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground">
                                Upload a photo and choose where you sell. Vitrin
                                writes the title, bullet points, description and
                                keywords in Turkish, Arabic and English, sized
                                to each marketplace's rules, and creates new
                                photos of the product.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <Button size="lg" asChild>
                                    <Link
                                        href={
                                            auth.user ? dashboard() : register()
                                        }
                                    >
                                        {auth.user
                                            ? 'Open your listings'
                                            : `Start with ${signupCredits} free credits`}
                                    </Link>
                                </Button>
                                <p className="text-sm text-muted-foreground">
                                    No card needed.
                                </p>
                            </div>
                        </div>

                        <ListingDemo />
                    </section>

                    <section className="border-y bg-sweep/60">
                        <div className="mx-auto max-w-6xl px-5 py-16 md:px-8">
                            <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                Three steps, about a minute
                            </h2>
                            <ol className="mt-10 grid gap-10 md:grid-cols-3">
                                {steps.map(([title, text], index) => (
                                    <li key={title} className="flex gap-4">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums">
                                            {index + 1}
                                        </span>
                                        <div>
                                            <h3 className="font-semibold">
                                                {title}
                                            </h3>
                                            <p className="mt-1 text-muted-foreground">
                                                {text}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section className="mx-auto grid max-w-6xl gap-10 px-5 py-20 md:px-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                Written to each marketplace's rules
                            </h2>
                            <p className="mt-4 max-w-md text-muted-foreground">
                                A title that works on Trendyol gets cut off on
                                Amazon. Vitrin writes every listing for its
                                marketplace, then checks the limits before you
                                see it.
                            </p>
                        </div>
                        <table className="w-full text-left">
                            <thead className="sr-only">
                                <tr>
                                    <th>Marketplace</th>
                                    <th>Rule Vitrin follows</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y border-y">
                                {marketplaceRules.map(([market, rule]) => (
                                    <tr key={market} className="align-baseline">
                                        <th
                                            scope="row"
                                            className="w-36 py-4 pe-6 font-semibold whitespace-nowrap"
                                        >
                                            {market}
                                        </th>
                                        <td className="py-4 text-muted-foreground">
                                            {rule}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    <section className="mx-auto max-w-6xl px-5 pb-20 md:px-8">
                        <div className="max-w-2xl">
                            <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                New photos from the one you have
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                Choose the shots you need for your store. The
                                product stays exactly as it is in your photo.
                            </p>
                        </div>
                        <ul className="mt-10 grid grid-cols-2 gap-4 lg:grid-cols-4">
                            {frames.map((frame) => (
                                <li key={frame.label}>
                                    <figure>
                                        <div
                                            className={cn(
                                                'flex aspect-[4/5] items-center justify-center overflow-hidden rounded-2xl',
                                                frame.className,
                                            )}
                                            style={frame.style}
                                        >
                                            {frame.image}
                                        </div>
                                        <figcaption className="mt-3 text-sm font-medium">
                                            {frame.label}
                                        </figcaption>
                                    </figure>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="bg-primary text-primary-foreground">
                        <div className="mx-auto grid max-w-6xl gap-12 px-5 py-20 md:px-8 lg:grid-cols-2">
                            <div>
                                <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                    It only writes what is true
                                </h2>
                                <p className="mt-4 max-w-lg leading-relaxed text-primary-foreground/80">
                                    Vitrin describes what it can see in the
                                    photo and what you tell it. When it can't
                                    confirm something, like the exact material
                                    or size, it asks you to check instead of
                                    guessing. Fewer returns, fewer rejected
                                    listings.
                                </p>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                    Pay for what you generate
                                </h2>
                                <dl className="mt-6 divide-y divide-primary-foreground/20 border-y border-primary-foreground/20">
                                    <Price
                                        label="One listing, in one language"
                                        value={costs.copy}
                                    />
                                    <Price
                                        label="One new photo"
                                        value={costs.photo}
                                    />
                                    <Price
                                        label="Free when you sign up"
                                        value={signupCredits}
                                    />
                                </dl>
                                <p className="mt-4 text-sm text-primary-foreground/80">
                                    Anything that fails to generate is refunded
                                    automatically.
                                </p>
                                {!auth.user && (
                                    <Button
                                        size="lg"
                                        variant="secondary"
                                        className="mt-8"
                                        asChild
                                    >
                                        <Link href={register()}>
                                            Create your account
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm text-muted-foreground md:px-8">
                    <span className="flex items-center gap-2">
                        <AppLogoIcon className="size-4 fill-current" />
                        {name}
                    </span>
                    <span>
                        Listings for Trendyol, Hepsiburada, Amazon, noon, Etsy
                        and your own store.
                    </span>
                </footer>
            </div>
        </>
    );
}

function Price({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-3">
            <dt>{label}</dt>
            <dd className="font-semibold tabular-nums">
                {plural(value, 'credit')}
            </dd>
        </div>
    );
}

/**
 * The hero: one product photo and the three listings Vitrin wrote for it,
 * each with its character count against the marketplace limit.
 */
function ListingDemo() {
    return (
        <div className="relative grid gap-4 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] sm:items-center">
            <figure className="relative">
                <div
                    className="flex aspect-[4/3] items-center justify-center rounded-3xl bg-[#F2F4F3] sm:aspect-[4/5]"
                    style={{
                        backgroundImage:
                            'radial-gradient(ellipse at 50% 30%, #FFFFFF 0%, transparent 70%)',
                    }}
                >
                    <TeaGlass className="h-[74%] motion-safe:animate-rise" />
                </div>
                <figcaption className="mt-2 text-center text-xs text-muted-foreground">
                    The seller's photo
                </figcaption>
            </figure>

            <ol
                className="space-y-3"
                aria-label="Listings generated from the photo"
            >
                {samples.map((sample, index) => (
                    <li
                        key={sample.market}
                        className="rounded-xl border bg-card p-4 shadow-[0_1px_2px_rgba(14,42,36,0.06)] motion-safe:animate-rise"
                        style={{ animationDelay: `${250 + index * 140}ms` }}
                    >
                        <div className="flex items-center justify-between gap-3 text-xs">
                            <span className="flex items-center gap-2">
                                <span className="font-semibold">
                                    {sample.market}
                                </span>
                                <span
                                    lang={sample.lang}
                                    dir={sample.dir}
                                    className="rounded-full bg-muted px-2 py-0.5 text-muted-foreground"
                                >
                                    {sample.language}
                                </span>
                            </span>
                            <Counter text={sample.title} max={sample.max} />
                        </div>
                        <p
                            lang={sample.lang}
                            dir={sample.dir}
                            className="mt-2 leading-snug font-medium"
                        >
                            {sample.title}
                        </p>
                        <ul
                            lang={sample.lang}
                            dir={sample.dir}
                            className="mt-3 flex flex-wrap gap-1.5"
                        >
                            {sample.keywords.map((keyword) => (
                                <li
                                    key={keyword}
                                    className="rounded-md bg-mint px-2 py-0.5 text-xs text-mint-foreground"
                                >
                                    {keyword}
                                </li>
                            ))}
                        </ul>
                    </li>
                ))}
            </ol>
        </div>
    );
}

function Counter({ text, max }: { text: string; max: number }): ReactNode {
    return (
        <span
            className="text-primary tabular-nums"
            title="Characters used of the marketplace limit"
        >
            {charLength(text)}/{max}
        </span>
    );
}
