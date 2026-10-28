import { initializePaddle } from '@paddle/paddle-js';
import type { CheckoutOpenOptions, PaddleEventData } from '@paddle/paddle-js';
import type { PaddleConfig } from '@/types';

/**
 * Open Paddle's checkout overlay. Paddle.js is fetched from Paddle's CDN the
 * first time it's needed, so pages without a checkout never load it. Calling
 * this again (for example after navigating back) only updates the settings.
 * Resolves to false if Paddle.js couldn't be loaded.
 */
export async function openPaddleCheckout(
    config: PaddleConfig,
    options: CheckoutOpenOptions,
    onEvent: (event: PaddleEventData) => void,
): Promise<boolean> {
    const paddle = await initializePaddle({
        environment: config.environment,
        token: config.token,
        eventCallback: onEvent,
    });

    if (!paddle) {
        return false;
    }

    paddle.Checkout.open(options);

    return true;
}
