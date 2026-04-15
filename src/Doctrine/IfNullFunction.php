<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class IfNullFunction extends FunctionNode
{
    private Node $expr1;
    private Node $expr2;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            'IFNULL(%s, %s)',
            $this->expr1->dispatch($sqlWalker),
            $this->expr2->dispatch($sqlWalker),
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->expr1 = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->expr2 = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
