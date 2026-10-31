<?php
namespace App\Localization;

use App\Core\Session;

class Translator {
    protected static array $dictionaries = [];
    protected static string $currentLocale = 'en';

    public static function setLocale(string $locale): void {
        if (in_array($locale, ['en', 'or'])) {
            self::$currentLocale = $locale;
            Session::set('locale', $locale);
        }
    }

    public static function getLocale(): string {
        $sessionLocale = Session::get('locale');
        if (!empty($sessionLocale) && in_array($sessionLocale, ['en', 'or'])) {
            self::$currentLocale = $sessionLocale;
        }
        return self::$currentLocale;
    }

    public static function get(string $key, array $replacements = []): string {
        $locale = self::getLocale();

        if (!isset(self::$dictionaries[$locale])) {
            $file = __DIR__ . "/{$locale}.php";
            self::$dictionaries[$locale] = file_exists($file) ? require $file : [];
        }

        $translation = self::$dictionaries[$locale][$key] ?? null;

        // Fallback to English if not found
        if ($translation === null && $locale !== 'en') {
            if (!isset(self::$dictionaries['en'])) {
                $enFile = __DIR__ . '/en.php';
                self::$dictionaries['en'] = file_exists($enFile) ? require $enFile : [];
            }
            $translation = self::$dictionaries['en'][$key] ?? $key;
        }

        $translation = $translation ?? $key;

        foreach ($replacements as $k => $v) {
            $translation = str_replace(':' . $k, (string)$v, $translation);
        }

        return $translation;
    }
}
