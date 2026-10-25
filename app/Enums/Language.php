<?php

namespace App\Enums;

enum Language: string
{
    case English = 'en';
    case Turkish = 'tr';
    case Arabic = 'ar';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Turkish => 'Türkçe',
            self::Arabic => 'العربية',
        };
    }

    /**
     * The language's name in English, used inside prompts.
     */
    public function englishName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Turkish => 'Turkish',
            self::Arabic => 'Arabic',
        };
    }

    public function direction(): string
    {
        return $this === self::Arabic ? 'rtl' : 'ltr';
    }

    /**
     * Writing guidance that makes the copy read as native, not translated.
     */
    public function styleGuide(): string
    {
        return match ($this) {
            self::English => 'Write in clear, natural international English.',
            self::Turkish => 'Write natural Turkish e-commerce copy the way experienced Trendyol and Hepsiburada sellers write it, using the search terms Turkish shoppers actually type. Use correct Turkish characters (ç, ğ, ı, İ, ö, ş, ü) everywhere, keywords included; marketplace search already matches "cift" to "çift". Where shoppers use both a loanword and a Turkish word (mouse and fare, laptop and dizüstü bilgisayar), use the more common one in the title and the other in the keywords.',
            self::Arabic => 'Write in Modern Standard Arabic suited to Gulf shoppers (Saudi Arabia, UAE), using the product terms people in the Gulf actually search for. Use Western digits (0-9) for sizes, quantities and measurements. Keep model numbers and units in Latin characters. Where shoppers use both an English loanword and an Arabic term (ماوس and فأرة), use the more common one in the title and the other in the keywords.',
        };
    }

    /**
     * @return list<array{value: string, label: string, dir: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $l) => [
            'value' => $l->value,
            'label' => $l->label(),
            'dir' => $l->direction(),
        ], self::cases());
    }
}
