export type CreditPack = {
    key: string;
    name: string;
    credits: number;
    /** Formatted by the server from Paddle's price, e.g. "$9.00"; null until known. */
    price: string | null;
    popular: boolean;
    purchasable: boolean;
};

export type CreditPurchase = {
    id: string;
    pack: string;
    credits: number;
    amount: string | null;
    status: 'completed' | 'partially_refunded' | 'refunded';
    status_label: string;
    completed_at: string | null;
    /** Whether Paddle issued an invoice that can be downloaded. */
    invoice: boolean;
};

/** What Paddle.js needs in the browser (a public, client-side token). */
export type PaddleConfig = {
    environment: 'sandbox' | 'production';
    token: string;
};

/** Flashed by the server once a purchase has been recorded. */
export type CheckoutRequest = {
    purchase_id: string;
    price_id: string;
    email: string;
};

export type Business = {
    legal_name: string;
    address: string | null;
    country: string | null;
    email: string | null;
};
