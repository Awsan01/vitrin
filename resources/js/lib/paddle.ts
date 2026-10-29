import { initializePaddle } from '@paddle/paddle-js';
import type {
    CheckoutEventsData,
    CheckoutOpenOptions,
    PaddleEventData,
} from '@paddle/paddle-js';
import type { PaddleConfig } from '@/types';

// Paddle.js keeps the event callback it was first initialized with, so it
// always calls this one, which forwards to the checkout opened last. That
// way a page that was left and visited again still gets the events.
let listener: ((event: PaddleEventData) => void) | null = null;

/**
 * Open Paddle's checkout overlay. Paddle.js is fetched from Paddle's CDN the
 * first time it's needed, so pages without a checkout never load it.
 * Resolves to false if Paddle.js couldn't be loaded.
 */
export async function openPaddleCheckout(
    config: PaddleConfig,
    options: CheckoutOpenOptions,
    onEvent: (event: PaddleEventData) => void,
): Promise<boolean> {
    listener = onEvent;

    const paddle = await initializePaddle({
        environment: config.environment,
        token: config.token,
        eventCallback: (event) => listener?.(event),
    });

    if (!paddle) {
        return false;
    }

    paddle.Checkout.open(options);

    return true;
}

/**
 * The Vitrin purchase a checkout event is about, from the custom data the
 * checkout was opened with.
 */
export function purchaseIdOf(data: CheckoutEventsData): string | null {
    const custom = data.custom_data;
    const id: unknown =
        custom && 'purchase_id' in custom ? custom.purchase_id : null;

    return typeof id === 'string' ? id : null;
}
