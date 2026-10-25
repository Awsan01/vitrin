<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The upload can't be turned into a product photo: it isn't a readable
 * JPEG, PNG or WebP image, or it has too many pixels to process safely.
 * The message is written for the seller.
 */
class UnsupportedImageException extends RuntimeException {}
