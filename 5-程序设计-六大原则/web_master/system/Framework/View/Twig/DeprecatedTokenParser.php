<?php

namespace Framework\View\Twig;

use Twig_Token as Token;
use Twig_TokenParser as AbstractTokenParser;

class DeprecatedTokenParser extends AbstractTokenParser
{
    /**
     * @inheritDoc
     */
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new DeprecatedNode($expr, $token->getLine(), $this->getTag());
    }

    /**
     * @inheritDoc
     */
    public function getTag()
    {
        return 'deprecated';
    }
}
