<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PublicRepositoryUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || ! preg_match('~\Ahttps://(?:github\.com|gitlab\.com|bitbucket\.org)/[a-z0-9._-]+/[a-z0-9._-]+/?\z~iD', $value)) {
            $fail('Use an HTTPS repository URL on github.com, gitlab.com, or bitbucket.org in owner/repository form.');

            return;
        }

        $path = trim((string) parse_url(self::normalize($value), PHP_URL_PATH), '/');

        if (count(explode('/', $path)) !== 2) {
            $fail('The repository URL must contain a valid owner and repository name.');

            return;
        }

        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                $fail('The repository URL must contain a valid owner and repository name.');

                return;
            }
        }
    }

    public static function normalize(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        $path = preg_replace('/\.git$/i', '', $path);

        return 'https://'.$host.$path;
    }
}
