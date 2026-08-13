<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows;

use Illuminate\Support\Arr;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Evaluates the logical/mathematical expressions a user can type into a
 * variable assignment, an exclusive gate's edge condition, a timer's
 * reference date, or an email/form template — using Symfony's
 * ExpressionLanguage (comparisons, arithmetic, boolean logic, string
 * concatenation, `in`/`matches`, and property/method access on any
 * object passed in the context, e.g. `entity.nome_cliente`).
 */
class WorkflowExpressionEvaluator
{
    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new ExpressionLanguage;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(?string $expression, array $context = []): mixed
    {
        if ($expression === null || trim($expression) === '') {
            return null;
        }

        return $this->language->evaluate($expression, $context);
    }

    /**
     * Renders a `{{ path.to.value }}` template (email subject/body, user
     * task labels) by resolving each placeholder against the context
     * with dot-notation, the same syntax used everywhere else in
     * variable references.
     *
     * @param  array<string, mixed>  $context
     */
    public function renderTemplate(string $template, array $context = []): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $matches) use ($context) {
            $value = Arr::get($context, $matches[1]);

            return match (true) {
                $value === null => '',
                is_bool($value) => $value ? '1' : '0',
                is_scalar($value) => (string) $value,
                $value instanceof \Stringable => (string) $value,
                default => json_encode($value),
            };
        }, $template) ?? $template;
    }
}
