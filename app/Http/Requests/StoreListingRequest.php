<?php

namespace App\Http\Requests;

use App\Data\NewListing;
use App\Enums\Language;
use App\Enums\Marketplace;
use App\Enums\PhotoStyle;
use App\Services\ListingGenerator;
use App\Support\ProductImage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $min = config('vitrin.uploads.min_dimension');
        $maxMegapixels = config('vitrin.uploads.max_megapixels');

        return [
            'photo' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(config('vitrin.uploads.max_kb'))
                    ->dimensions(Rule::dimensions()->minWidth($min)->minHeight($min)),
                function (string $attribute, mixed $value, Closure $fail) use ($maxMegapixels) {
                    $pixels = $value instanceof UploadedFile ? ProductImage::pixelCount($value->getRealPath()) : null;

                    if ($pixels !== null && $pixels > $maxMegapixels * 1_000_000) {
                        $fail("The photo should be at most {$maxMegapixels} megapixels.");
                    }
                },
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'marketplaces' => ['required', 'array', 'min:1'],
            'marketplaces.*' => ['distinct', Rule::enum(Marketplace::class)],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['distinct', Rule::enum(Language::class)],
            'photo_styles' => ['nullable', 'array'],
            'photo_styles.*' => ['distinct', Rule::enum(PhotoStyle::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Add a photo of your product.',
            'photo.dimensions' => 'The photo should be at least :min_width × :min_height pixels.',
            'marketplaces.required' => 'Pick at least one marketplace.',
            'languages.required' => 'Pick at least one language.',
        ];
    }

    /**
     * Check the user can afford the generation once the input is valid.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cost = $this->cost();
                $credits = $this->user()->credits;

                if ($cost > $credits) {
                    $validator->errors()->add('credits', "This needs {$cost} credits but you have {$credits}.");
                }
            },
        ];
    }

    public function cost(): int
    {
        return ListingGenerator::cost(
            count($this->enums('marketplaces', Marketplace::class)) * count($this->enums('languages', Language::class)),
            count($this->enums('photo_styles', PhotoStyle::class)),
        );
    }

    /**
     * The validated input as a typed generation request.
     */
    public function toNewListing(): NewListing
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->value() : null;

        return new NewListing(
            photo: $this->file('photo'),
            name: $text('name'),
            notes: $text('notes'),
            marketplaces: array_values($this->enums('marketplaces', Marketplace::class)),
            languages: array_values($this->enums('languages', Language::class)),
            photoStyles: array_values($this->enums('photo_styles', PhotoStyle::class)),
        );
    }
}
