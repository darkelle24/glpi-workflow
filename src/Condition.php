<?php
declare(strict_types=1);
namespace WorkflowBuilder;

/** A bounded data interpreter, shared by preview and execution. Never executes code. */
final class Condition
{
    public const OPERATORS = ['eq', 'ne', 'contains', 'not_contains', 'gt', 'lt', 'empty', 'not_empty', 'true', 'false'];

    public static function validate(mixed $node, array $variables, int $depth = 0): void
    {
        if ($node === null) {
            return;
        }
        if (!is_array($node) || $depth > 6) {
            throw new ValidationException('Condition invalide ou trop profondément imbriquée.');
        }
        if (isset($node['all']) || isset($node['any'])) {
            $key = isset($node['all']) ? 'all' : 'any';
            if (count($node) !== 1 || !is_array($node[$key]) || count($node[$key]) < 1 || count($node[$key]) > 20) {
                throw new ValidationException('Un groupe ET / OU doit contenir entre 1 et 20 conditions.');
            }
            foreach ($node[$key] as $child) {
                self::validate($child, $variables, $depth + 1);
            }
            return;
        }
        if (array_diff(array_keys($node), ['field', 'op', 'value'])
            || !is_string($node['field'] ?? null)
            || !in_array($node['field'], $variables, true)
            || !in_array($node['op'] ?? null, self::OPERATORS, true)
            || (isset($node['value']) && !is_scalar($node['value']))) {
            throw new ValidationException('Variable, opérateur ou valeur de condition invalide.');
        }
    }

    public static function evaluate(?array $node, array $values): bool
    {
        if ($node === null) {
            return true;
        }
        foreach (['all', 'any'] as $group) {
            if (isset($node[$group])) {
                foreach ($node[$group] as $child) {
                    $result = self::evaluate($child, $values);
                    if ($group === 'all' && !$result) {
                        return false;
                    }
                    if ($group === 'any' && $result) {
                        return true;
                    }
                }
                return $group === 'all';
            }
        }
        $actual = $values[$node['field']] ?? null;
        $expected = $node['value'] ?? null;
        $empty = $actual === null || $actual === '' || $actual === [];
        $a = self::scalar($actual);
        $b = self::scalar($expected);
        return match ($node['op']) {
            'eq' => !is_array($actual) && $a === $b,
            'ne' => is_array($actual) || $a !== $b,
            'contains' => is_array($actual)
                ? in_array($b, array_map(self::scalar(...), $actual), true)
                : str_contains($a, $b),
            'not_contains' => is_array($actual)
                ? !in_array($b, array_map(self::scalar(...), $actual), true)
                : !str_contains($a, $b),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'empty' => $empty,
            'not_empty' => !$empty,
            'true' => in_array($actual, [true, 1, '1', 'true'], true),
            'false' => in_array($actual, [false, 0, '0', 'false'], true),
            default => false,
        };
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === true || $value === 'true' => '1',
            $value === false || $value === 'false' => '0',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
