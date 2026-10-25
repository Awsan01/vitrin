export type GenerationStatus =
    | 'pending'
    | 'processing'
    | 'completed'
    | 'failed';

export type ListingStatus =
    | 'queued'
    | 'analyzing'
    | 'generating'
    | 'completed'
    | 'partial'
    | 'failed';

export type TextDirection = 'ltr' | 'rtl';

export type MarketplaceOption = {
    value: string;
    label: string;
    hint: string | null;
};

export type LanguageOption = {
    value: string;
    label: string;
    dir: TextDirection;
};

export type PhotoStyleOption = {
    value: string;
    label: string;
    description: string;
};

export type Costs = {
    copy: number;
    photo: number;
};

export type ProductFacts = {
    is_product: boolean;
    rejection_reason: string | null;
    product_type: string;
    category_path: string;
    brand: string | null;
    colors: string[];
    materials: string[];
    key_features: string[];
    size_or_dimensions: string | null;
    quantity: string | null;
    target_audience: string | null;
    visible_text: string[];
    needs_confirmation: string[];
    photo_tips: string[];
};

export type ListingCopy = {
    id: number;
    marketplace: string;
    marketplace_label: string;
    language: string;
    language_label: string;
    dir: TextDirection;
    status: GenerationStatus;
    title: string | null;
    highlight: string | null;
    bullets: string[];
    description: string | null;
    description_html: string | null;
    keywords: string[];
    category: string | null;
    warnings: string[];
    error: string | null;
    limits: {
        title_max: number;
        highlight_label: string | null;
        highlight_max: number | null;
        keywords_label: string;
        keywords_separator: string;
    };
};

export type ListingPhoto = {
    id: number;
    style: string;
    style_label: string;
    status: GenerationStatus;
    url: string | null;
    download_url: string | null;
    error: string | null;
};

export type ListingDetail = {
    id: string;
    name: string;
    notes: string | null;
    status: ListingStatus;
    status_label: string;
    is_processing: boolean;
    error: string | null;
    can_retry: boolean;
    created_at: string | null;
    source_url: string;
    facts: ProductFacts | null;
    progress: { done: number; total: number };
    copies: ListingCopy[];
    photos: ListingPhoto[];
};

export type ListingCard = {
    id: string;
    name: string;
    status: ListingStatus;
    status_label: string;
    is_processing: boolean;
    created_at: string | null;
    source_url: string;
    marketplaces: string[];
    languages: string[];
    copies_count: number;
    photos_count: number;
};

export type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    prev_url: string | null;
    next_url: string | null;
};

export type CreditTransaction = {
    id: number;
    amount: number;
    balance_after: number;
    reason: 'signup' | 'generation' | 'refund' | 'grant' | string;
    description: string | null;
    listing_id: string | null;
    created_at: string | null;
};
