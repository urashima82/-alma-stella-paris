<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class JsonContainsFunction extends FunctionNode
{
    private Node $jsonDoc;
    private Node $jsonVal;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            'JSON_CONTAINS(%s, %s)',
            $this->jsonDoc->dispatch($sqlWalker),
            $this->jsonVal->dispatch($sqlWalker),
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonDoc = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->jsonVal = $parser->InputParameter();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
