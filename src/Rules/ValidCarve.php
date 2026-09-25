<?php

namespace JeffersonGoncalves\Carve\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JeffersonGoncalves\Carve\ConverterFactory;
use JeffersonGoncalves\Carve\Facades\Carve;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\ProfileViolation;
use Throwable;

/**
 * Validates Carve source.
 *
 *     'body' => ['required', new ValidCarve],
 *     'comment' => ['required', ValidCarve::preset('comment')->maxLength(5000)],
 *     'docs' => ['required', (new ValidCarve)->strict()->lint()],
 */
final class ValidCarve implements ValidationRule
{
    private ?string $preset = null;

    private bool $strict = false;

    private bool $lint = false;

    private ?int $maxLength = null;

    /**
     * Fail when the source uses markup the carve preset (full, article,
     * comment, minimal) does not allow, instead of silently degrading it.
     */
    public static function preset(string $preset): self
    {
        $rule = new self;
        $rule->preset = $preset;

        return $rule;
    }

    /**
     * Fail on parse warnings, such as undefined references.
     */
    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * Fail on lint findings, such as Markdown habits (**bold**).
     */
    public function lint(bool $lint = true): self
    {
        $this->lint = $lint;

        return $this;
    }

    /**
     * Maximum length in characters.
     */
    public function maxLength(int $characters): self
    {
        $this->maxLength = $characters;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('carve::validation.string')->translate();

            return;
        }

        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            $fail('carve::validation.max')->translate(['max' => (string) $this->maxLength]);

            return;
        }

        $converter = new CarveConverter(
            warnings: $this->strict,
            profile: (new ConverterFactory)->preset($this->preset),
        );

        try {
            $converter->parse($value);
        } catch (Throwable $e) {
            $fail('carve::validation.invalid')->translate(['message' => $e->getMessage()]);

            return;
        }

        if ($converter->hasProfileViolations()) {
            $features = array_unique(array_map(fn (ProfileViolation $violation): string => $violation->nodeType, $converter->getProfileViolations()));
            $fail('carve::validation.disallowed')->translate(['features' => implode(', ', $features)]);

            return;
        }

        if ($this->strict && $converter->hasWarnings()) {
            $warning = $converter->getWarnings()[0];
            $fail('carve::validation.warning')->translate(['message' => $warning->getMessage(), 'line' => (string) $warning->getLine()]);

            return;
        }

        if ($this->lint && ($findings = Carve::lint($value)) !== []) {
            $fail('carve::validation.lint')->translate(['message' => $findings[0]->message, 'line' => (string) $findings[0]->line]);
        }
    }
}
