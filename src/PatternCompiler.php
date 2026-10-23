<?php

declare(strict_types=1);

namespace Pux;

use Pux\Exception\PatternSyntax;

/**
 * Compiles a route pattern into a PCRE expression.
 *
 *   /blog/:year/:month
 *   /blog/item/:id
 *   /blog/item/:id(.:format)
 *
 * Syntax, read left to right by a scanner that tracks parenthesis depth:
 *
 * - ":name" is a variable; the name is one or more of [A-Za-z0-9_]. The character right before
 *   the colon is the variable separator ("/" in "/:id", "-" in "-:month"): it is matched
 *   literally in front of the variable. A colon without a character before it is text.
 * - "(" ... ")" is an optional group; groups may follow one another and nest.
 * - Anything else is literal text. A colon not followed by a name character is text.
 * - An unclosed "(" or a ")" without its "(" throws PatternSyntax.
 *
 * The default expression of a variable is a negated character class of the characters next to
 * it: the first character of the text right before it (or its own separator when there is no
 * text), the leading character of the next variable or group on the same level, and the
 * character right after its name.
 * The 'require' option replaces the default expression; the 'default' option makes the trailing
 * variables of a level optional.
 */
class PatternCompiler
{
    final public const TOKEN_TYPE_OPTIONAL = 1;

    final public const TOKEN_TYPE_VARIABLE = 2;

    final public const TOKEN_TYPE_TEXT = 3;

    private const NAME_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    /**
     * Compiles a pattern into an expression anchored at both ends.
     *
     * @param array<string, mixed> $options route options; 'require' and 'default' are read
     *
     * @return array<string, mixed> the options plus 'variables', 'regex', 'tokens', 'compiled' and 'pattern'
     *
     * @throws PatternSyntax when the pattern has an unbalanced parenthesis
     */
    public static function compile(string $pattern, array $options = []): array
    {
        $route = self::compilePattern($pattern, $options);
        $route['compiled'] = sprintf('#^%s$#xs', $route['regex']);
        $route['pattern'] = $pattern;

        return $route;
    }

    /**
     * Compiles a pattern into an expression anchored at the start only (a mount point prefix).
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @throws PatternSyntax when the pattern has an unbalanced parenthesis
     */
    public static function compilePrefix(string $pattern, array $options = []): array
    {
        $route = self::compilePattern($pattern, $options);
        $route['compiled'] = sprintf('#^%s#xs', $route['regex']);
        $route['pattern'] = $pattern;

        return $route;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> the options plus 'variables', 'regex' (no delimiters) and 'tokens'
     *
     * @throws PatternSyntax when the pattern has an unbalanced parenthesis
     */
    public static function compilePattern(string $pattern, array $options = []): array
    {
        $offset = 0;
        $nodes = self::parseLevel($pattern, $offset, null);
        [$regex, $tokens, $variables] = self::emitLevel($nodes, $options);

        $options['variables'] = $variables;
        $options['regex'] = $regex;
        $options['tokens'] = $tokens;

        return $options;
    }

    /**
     * Reads one level of the pattern from $offset: up to the end of the pattern (top level) or
     * up to the ")" that closes the group opened at $groupOffset, which is consumed.
     *
     * Node shapes:
     *   ['text', string $text]
     *   ['variable', string $separator, string $name, ?string $nextCharacter]
     *   ['group', string $content, list<array> $children]
     *
     * @return list<array<int, mixed>>
     */
    private static function parseLevel(string $pattern, int &$offset, ?int $groupOffset): array
    {
        $length = strlen($pattern);
        $nodes = [];
        $text = '';

        while ($offset < $length) {
            $character = $pattern[$offset];

            if ($character === '(') {
                $nodes = self::appendText($nodes, $text);
                $text = '';
                $open = $offset;
                ++$offset;
                $children = self::parseLevel($pattern, $offset, $open);
                $nodes[] = ['group', substr($pattern, $open + 1, $offset - $open - 2), $children];

                continue;
            }

            if ($character === ')') {
                if ($groupOffset === null) {
                    throw PatternSyntax::unopenedGroup($pattern, $offset);
                }
                ++$offset;

                return self::appendText($nodes, $text);
            }

            if ($character !== ':' && self::startsVariable($pattern, $offset + 1)) {
                $separator = $character;
                $nameOffset = $offset + 2;
            } else {
                $text .= $character;
                ++$offset;

                continue;
            }

            $nodes = self::appendText($nodes, $text);
            $text = '';
            $nameLength = strspn($pattern, self::NAME_CHARACTERS, $nameOffset);
            $offset = $nameOffset + $nameLength;
            $next = $offset < $length && $pattern[$offset] !== ')' ? $pattern[$offset] : null;
            $nodes[] = ['variable', $separator, substr($pattern, $nameOffset, $nameLength), $next];
        }

        if ($groupOffset !== null) {
            throw PatternSyntax::unclosedGroup($pattern, $groupOffset);
        }

        return self::appendText($nodes, $text);
    }

    private static function startsVariable(string $pattern, int $colonOffset): bool
    {
        return ($pattern[$colonOffset] ?? '') === ':' && self::isNameCharacter($pattern, $colonOffset + 1);
    }

    private static function isNameCharacter(string $pattern, int $offset): bool
    {
        return $offset < strlen($pattern) && strspn($pattern, self::NAME_CHARACTERS, $offset, 1) === 1;
    }

    /**
     * @param list<array<int, mixed>> $nodes
     *
     * @return list<array<int, mixed>>
     */
    private static function appendText(array $nodes, string $text): array
    {
        if ($text !== '') {
            $nodes[] = ['text', $text];
        }

        return $nodes;
    }

    /**
     * Builds the expression of one level.
     *
     * @param list<array<int, mixed>> $nodes
     * @param array<string, mixed>    $options
     *
     * @return array{string, list<array<int, mixed>>, list<string>} expression, tokens, variable names
     */
    private static function emitLevel(array $nodes, array $options): array
    {
        $tokens = [];
        $variables = [];

        foreach ($nodes as $index => $node) {
            switch ($node[0]) {
                case 'text':
                    $tokens[] = [self::TOKEN_TYPE_TEXT, $node[1]];
                    break;
                case 'group':
                    [$groupRegex, , $groupVariables] = self::emitLevel($node[2], [
                        'default' => $options['default'] ?? null,
                        'require' => $options['require'] ?? null,
                    ]);
                    $tokens[] = [self::TOKEN_TYPE_OPTIONAL, $node[1] === '' ? '' : $node[1][0], $groupRegex];
                    array_push($variables, ...$groupVariables);
                    break;
                default:
                    $name = $node[2];
                    $required = $options['require'][$name] ?? null;
                    $regex = is_string($required) && $required !== ''
                        ? $required
                        : self::defaultVariableRegex($nodes, $index);
                    $tokens[] = [self::TOKEN_TYPE_VARIABLE, $node[1], $regex, $name];
                    $variables[] = $name;
            }
        }

        return [self::assemble($tokens, $options), $tokens, $variables];
    }

    /**
     * @param list<array<int, mixed>> $nodes
     */
    private static function defaultVariableRegex(array $nodes, int $index): string
    {
        $node = $nodes[$index];
        $previous = $nodes[$index - 1] ?? null;
        $excluded = [];

        if ($previous !== null && $previous[0] === 'text') {
            $excluded[] = $previous[1][0];
        } elseif ($node[1] !== '') {
            $excluded[] = $node[1];
        }

        for ($i = $index + 1; isset($nodes[$i]); ++$i) {
            if ($nodes[$i][0] === 'text') {
                continue;
            }
            $leading = self::leadingCharacter($nodes[$i]);
            if ($leading !== null) {
                $excluded[] = $leading;
            }
            break;
        }

        if ($node[3] !== null) {
            $excluded[] = $node[3];
        }

        return sprintf('[^%s]+', preg_quote(implode('', array_unique($excluded)), '#'));
    }

    /**
     * The character a variable or a group starts with: the separator of a variable, the first
     * character of the content of a group (of its first child for a group that opens with
     * another group); null when there is none.
     *
     * @param array<int, mixed> $node
     */
    private static function leadingCharacter(array $node): ?string
    {
        if ($node[0] === 'variable') {
            return $node[1] !== '' ? $node[1] : null;
        }

        $first = $node[2][0] ?? null;
        if ($first === null) {
            return null;
        }

        return match ($first[0]) {
            'text' => $first[1][0],
            'variable' => $first[1] !== '' ? $first[1] : null,
            default => $first[1] !== '' ? $first[1][0] : null,
        };
    }

    /**
     * Joins the tokens of one level into an expression for the x (extended) flag: one token per
     * line, indented four spaces per nesting level. Trailing variables that have a 'default'
     * become nested optional groups.
     *
     * @param list<array<int, mixed>> $tokens
     * @param array<string, mixed>    $options
     */
    private static function assemble(array $tokens, array $options): string
    {
        $firstOptional = null;
        for ($i = count($tokens) - 1; $i >= 0; --$i) {
            if ($tokens[$i][0] !== self::TOKEN_TYPE_VARIABLE || !isset($options['default'][$tokens[$i][3]])) {
                break;
            }
            $firstOptional = $i;
        }

        $regex = '';
        $indent = 1;

        if (count($tokens) === 1 && $firstOptional === 0) {
            $token = $tokens[0];
            ++$indent;
            $regex .= self::indent($indent) . preg_quote($token[1], '#') . "(?:\n";
            $regex .= self::indent($indent) . sprintf("(?P<%s>%s)\n", $token[3], $token[2]);
        } else {
            foreach ($tokens as $i => $token) {
                switch ($token[0]) {
                    case self::TOKEN_TYPE_TEXT:
                        $regex .= self::indent($indent) . preg_quote($token[1], '#') . "\n";
                        break;
                    case self::TOKEN_TYPE_OPTIONAL:
                        $regex .= self::indent($indent) . "(?:\n" . $token[2] . self::indent($indent) . ")?\n";
                        break;
                    default:
                        if ($firstOptional !== null && $i >= $firstOptional) {
                            $regex .= self::indent($indent) . "(?:\n";
                            ++$indent;
                        }
                        $regex .= self::indent($indent) . sprintf("%s(?P<%s>%s)\n", preg_quote($token[1], '#'), $token[3], $token[2]);
                }
            }
        }

        while (--$indent) {
            $regex .= self::indent($indent) . ")?\n";
        }

        return $regex;
    }

    private static function indent(int $level): string
    {
        return str_repeat(' ', $level * 4);
    }
}
