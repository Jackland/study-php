<?php

namespace Framework\Translation\Twig;

use Twig_Node as Node;
use Twig_Token as Token;
use Twig_TokenParser as AbstractTokenParser;

/**
 * 参考
 * Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser
 */
class TransDefaultCategoryTokenParser extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @return Node
     */
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new TransDefaultCategoryNode($expr, $token->getLine(), $this->getTag());
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'trans_default_category';
    }
}
