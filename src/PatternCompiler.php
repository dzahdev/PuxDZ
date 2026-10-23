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
 *   literally in front of the variable. A variable at the start of the pattern or of a group, or
 *   right after another token, has no separator (":id", "(:id)").
 * - Text in parentheses is an optional group; groups may follow one another and nest.
 * - Anything else is literal text, white space included. A colon not followed by a name
 *   character is text.
 * - An unclosed "(" or a ")" without its "(" throws PatternSyntax, and so does a 'require'
 *   expression that makes the compiled expression invalid.
 *
 * The default expression of a variable is a negated character class of the characters next to
 * it: the first character of the text right before it (or its own separator when there is no
 * text), the leading character of the next variable or group on the same level, and the
 * character right after its name. A variable with none of these (":id" alone) excludes "/".
 * The 'require' option replaces the default expression; the 'default' option makes the trailing
 * variables of a level optional.
 *
 * @phpstan-type Token array{0: self::TOKEN_TYPE_OPTIONAL, 1: string, 2: string}|array{0: self::TOKEN_TYPE_VARIABLE, 1: string, 2: string, 3: string}|array{0: self::TOKEN_TYPE_TEXT, 1: string}
 * @phpstan-type CompiledPattern array{variables: list<string>, regex: string, tokens: list<Token>, compiled: string, pattern: string, ...<string, mixed>}
 * @phpstan-type TextNode array{0: 'text', 1: non-empty-string}
 * @phpstan-type VariableNode array{0: 'variable', 1: string, 2: string, 3: ?string}
 * @phpstan-type GroupNode array{0: 'group', 1: string, 2: string, 3: list<string>, 4: ?string}
 * @phpstan-type Node TextNode|VariableNode|GroupNode
 */
final class PatternCompiler
{
    final public const TOKEN_TYPE_OPTIONAL = 1;

    final public const TOKEN_TYPE_VARIABLE = 2;

    final public const TOKEN_TYPE_TEXT = 3;

    private const NAME_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    /** White space that the x flag ignores, mapped to escape sequences PCRE matches literally. */
    private const WHITE_SPACE_ESCAPES = [' ' => '\\ ', "\t" => '\\t', "\n" => '\\n', "\r" => '\\r', "\f" => '\\f', "\v" => '\\x0B'];

    /**
     * Compiles a pattern into an expression anchored at both ends.
     *
     * @param array<string, mixed> $options route options; 'require' and 'default' are read
     *
     * @return CompiledPattern the options plus 'variables', 'regex', 'tokens', 'compiled' and 'pattern'
     *
     * @throws PatternSyntax when the pattern has an unbalanced parenthesis or a 'require'
     *                       expression makes the result invalid
     */
    public static function compile(string $pattern, array $options = []): array
    {
        return self::compileAnchored($pattern, $options, '#^%s$#xs');
    }

    /**
     * Compiles a pattern into an expression anchored at the start only (a mount point prefix).
     *
     * @param array<string, mixed> $options
     *
     * @return CompiledPattern
     *
     * @throws PatternSyntax when the pattern has an unbalanced parenthesis or a 'require'
     *                       expression makes the result invalid
     */
    public static function compilePrefix(string $pattern, array $options = []): array
    {
        return self::compileAnchored($pattern, $options, '#^%s#xs');
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{variables: list<string>, regex: string, tokens: list<Token>, ...<string, mixed>} the options plus 'variables', 'regex' (no delimiters) and 'tokens'
     *
     * @throws PatternSyntax when the pattern has an unbalanced parenthesis
     */
    public static function compilePattern(string $pattern, array $options = []): array
    {
        $offset = 0;
        $require = self::arrayOption($options, 'require');
        $default = self::arrayOption($options, 'default');
        [$regex, $tokens, $variables] = self::emitLevel(self::parseLevel($pattern, $offset, null, $require, $default), $require, $default);

        $options['variables'] = $variables;
        $options['regex'] = $regex;
        $options['tokens'] = $tokens;

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return CompiledPattern
     *
     * @throws PatternSyntax
     */
    private static function compileAnchored(string $pattern, array $options, string $format): array
    {
        $route = self::compilePattern($pattern, $options);
        $route['compiled'] = self::assertCompiles($pattern, sprintf($format, $route['regex']));
        $route['pattern'] = $pattern;

        return $route;
    }

    /**
     * The option 'require' or 'default': a map of variable name to value. An option that is
     * absent or not an array names no variable.
     *
     * @param array<string, mixed> $options
     *
     * @return array<array-key, mixed>
     */
    private static function arrayOption(array $options, string $key): array
    {
        $value = $options[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * Returns $compiled after checking that PCRE compiles it: a 'require' expression is inserted
     * as given, so it can break the result.
     *
     * @throws PatternSyntax
     */
    private static function assertCompiles(string $pattern, string $compiled): string
    {
        try {
            Pcre::match($compiled, '');
        } catch (PatternSyntax $e) {
            throw PatternSyntax::invalidRoutePattern($pattern, $e);
        }

        return $compiled;
    }

    /**
     * Reads one level of the pattern from $offset: up to the end of the pattern (top level) or
     * up to the ")" that closes the group opened at $groupOffset, which is consumed. A group is
     * compiled when it closes, so its node holds its expression instead of its children.
     *
     * Node shapes:
     *   ['text', string $text]
     *   ['variable', string $separator, string $name, ?string $nextCharacter]
     *   ['group', string $content, string $regex, list<string> $variables, ?string $leadingCharacter]
     *
     * @param array<array-key, mixed> $require the option 'require'
     * @param array<array-key, mixed> $default the option 'default'
     *
     * @return list<Node>
     */
    private static function parseLevel(string $pattern, int &$offset, ?int $groupOffset, array $require, array $default): array
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
                $children = self::parseLevel($pattern, $offset, $open, $require, $default);
                [$groupRegex, , $groupVariables] = self::emitLevel($children, $require, $default);
                $nodes[] = ['group', substr($pattern, $open + 1, $offset - $open - 2), $groupRegex, $groupVariables, self::firstCharacter($children)];

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
            } elseif ($character === ':' && self::isNameCharacter($pattern, $offset + 1)) {
                $separator = '';
                $nameOffset = $offset + 1;
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
     * @param list<Node> $nodes
     *
     * @return list<Node>
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
     * @param list<Node>              $nodes
     * @param array<array-key, mixed> $require the option 'require'
     * @param array<array-key, mixed> $default the option 'default'
     *
     * @return array{string, list<Token>, list<string>} expression, tokens, variable names
     */
    private static function emitLevel(array $nodes, array $require, array $default): array
    {
        $tokens = [];
        $variables = [];

        foreach ($nodes as $index => $node) {
            switch ($node[0]) {
                case 'text':
                    $tokens[] = [self::TOKEN_TYPE_TEXT, $node[1]];
                    break;
                case 'group':
                    $tokens[] = [self::TOKEN_TYPE_OPTIONAL, $node[1] === '' ? '' : $node[1][0], $node[2]];
                    array_push($variables, ...$node[3]);
                    break;
                default:
                    $name = $node[2];
                    $required = $require[$name] ?? null;
                    $regex = is_string($required) && $required !== ''
                        ? $required
                        : self::defaultVariableRegex($nodes, $index, $node);
                    $tokens[] = [self::TOKEN_TYPE_VARIABLE, $node[1], $regex, $name];
                    $variables[] = $name;
            }
        }

        return [self::assemble($tokens, $default), $tokens, $variables];
    }

    /**
     * @param list<Node>   $nodes
     * @param VariableNode $node  the variable at $index
     */
    private static function defaultVariableRegex(array $nodes, int $index, array $node): string
    {
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

        if ($excluded === []) {
            $excluded[] = '/';
        }

        return sprintf('[^%s]+', preg_quote(implode('', array_unique($excluded)), '#'));
    }

    /**
     * The character a variable or a group starts with: the separator of a variable, the leading
     * character of the children of a group (see firstCharacter()); null when there is none.
     *
     * @param VariableNode|GroupNode $node
     */
    private static function leadingCharacter(array $node): ?string
    {
        if ($node[0] === 'variable') {
            return $node[1] !== '' ? $node[1] : null;
        }

        return $node[4];
    }

    /**
     * The character the content of a group starts with, read from its first child: the first
     * character of a text, the separator of a variable, the first character of the content of
     * a nested group; null for an empty group or a first child without one.
     *
     * @param list<Node> $children
     */
    private static function firstCharacter(array $children): ?string
    {
        $first = $children[0] ?? null;
        if ($first === null) {
            return null;
        }

        return match ($first[0]) {
            'text' => $first[1][0],
            'variable' => $first[1] !== '' ? $first[1] : null,
            'group' => $first[1] !== '' ? $first[1][0] : null,
        };
    }

    /**
     * Joins the tokens of one level into an expression for the x (extended) flag: one token per
     * line, indented four spaces per nesting level. Trailing variables that have a 'default'
     * become nested optional groups.
     *
     * @param list<Token>             $tokens
     * @param array<array-key, mixed> $default the option 'default'
     */
    private static function assemble(array $tokens, array $default): string
    {
        $firstOptional = null;
        for ($i = count($tokens) - 1; $i >= 0; --$i) {
            if ($tokens[$i][0] !== self::TOKEN_TYPE_VARIABLE || !isset($default[$tokens[$i][3]])) {
                break;
            }
            $firstOptional = $i;
        }

        $regex = '';
        $indent = 1;

        $single = count($tokens) === 1 && $firstOptional === 0 ? $tokens[0] : null;
        if ($single !== null && $single[0] === self::TOKEN_TYPE_VARIABLE) {
            $token = $single;
            ++$indent;
            $regex .= self::indent($indent) . self::quote($token[1]) . "(?:\n";
            $regex .= self::indent($indent) . sprintf("(?P<%s>%s)\n", $token[3], $token[2]);
        } else {
            foreach ($tokens as $i => $token) {
                switch ($token[0]) {
                    case self::TOKEN_TYPE_TEXT:
                        $regex .= self::indent($indent) . self::quote($token[1]) . "\n";
                        break;
                    case self::TOKEN_TYPE_OPTIONAL:
                        $regex .= self::indent($indent) . "(?:\n" . $token[2] . self::indent($indent) . ")?\n";
                        break;
                    default:
                        if ($firstOptional !== null && $i >= $firstOptional) {
                            $regex .= self::indent($indent) . "(?:\n";
                            ++$indent;
                        }
                        $regex .= self::indent($indent) . sprintf("%s(?P<%s>%s)\n", self::quote($token[1]), $token[3], $token[2]);
                }
            }
        }

        while (--$indent) {
            $regex .= self::indent($indent) . ")?\n";
        }

        return $regex;
    }

    /**
     * Quotes literal text for the x flag: preg_quote() leaves white space as it is, and the x
     * flag ignores unescaped white space outside character classes, so each white space
     * character is replaced with its escape sequence as well.
     */
    private static function quote(string $text): string
    {
        return strtr(preg_quote($text, '#'), self::WHITE_SPACE_ESCAPES);
    }

    private static function indent(int $level): string
    {
        return str_repeat(' ', $level * 4);
    }
}
