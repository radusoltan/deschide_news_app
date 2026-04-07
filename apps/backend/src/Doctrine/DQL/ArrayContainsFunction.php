<?php

declare(strict_types=1);

namespace App\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL function: ARRAY_CONTAINS(field, value)
 * SQL output:   field @> ARRAY[value]::text[]
 *
 * Usage in DQL: WHERE ARRAY_CONTAINS(a.publishedLocales, :locale) = true
 */
final class ArrayContainsFunction extends FunctionNode
{
    private Node $field;

    private Node $value;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->field = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            '%s @> ARRAY[%s]::text[]',
            $this->field->dispatch($sqlWalker),
            $this->value->dispatch($sqlWalker),
        );
    }
}
