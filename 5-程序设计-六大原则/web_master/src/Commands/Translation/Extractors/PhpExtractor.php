<?php

namespace App\Commands\Translation\Extractors;

use Symfony\Component\Finder\SplFileInfo;

/**
 * 参考
 * Symfony\Component\Translation\Extractor\PhpExtractor
 */
class PhpExtractor extends BaseExtractor
{
    const MESSAGE_TOKEN = 300;
    const METHOD_ARGUMENTS_TOKEN = 1000;
    const CATEGORY_TOKEN = 1001;

    /**
     * The sequence that captures translation messages.
     *
     * @var array
     */
    protected $sequences = [
        [
            '__',
            '(',
            self::MESSAGE_TOKEN,
            ',',
            self::METHOD_ARGUMENTS_TOKEN,
            ',',
            self::CATEGORY_TOKEN,
        ],
        [
            '__',
            '(',
            self::MESSAGE_TOKEN,
        ],
        [
            '__choice',
            '(',
            self::MESSAGE_TOKEN,
            ',',
            self::METHOD_ARGUMENTS_TOKEN,
            ',',
            self::METHOD_ARGUMENTS_TOKEN,
            ',',
            self::CATEGORY_TOKEN,
        ],
        [
            '__choice',
            '(',
            self::MESSAGE_TOKEN,
        ],
    ];

    /**
     * @inheritDoc
     */
    public function extract(SplFileInfo $file): array
    {
        $content = file_get_contents($file->getRealPath());
        if (!$this->checkContentExistTransFn($content, ['__(', '__choice('])) {
            return [];
        }

        $tokens = token_get_all($content);

        $result = [];
        $tokenIterator = new \ArrayIterator($tokens);
        for ($key = 0; $key < $tokenIterator->count(); ++$key) {
            foreach ($this->sequences as $sequence) {
                $message = '';
                $category = $this->getDefaultCategory();
                $tokenIterator->seek($key);

                foreach ($sequence as $sequenceKey => $item) {
                    $this->seekToNextRelevantToken($tokenIterator);

                    if ($this->normalizeToken($tokenIterator->current()) === $item) {
                        $tokenIterator->next();
                        continue;
                    } elseif (self::MESSAGE_TOKEN === $item) {
                        $message = $this->getValue($tokenIterator);

                        if (\count($sequence) === ($sequenceKey + 1)) {
                            break;
                        }
                    } elseif (self::METHOD_ARGUMENTS_TOKEN === $item) {
                        $this->skipMethodArgument($tokenIterator);
                    } elseif (self::CATEGORY_TOKEN === $item) {
                        $categoryToken = $this->getValue($tokenIterator);
                        if ('' !== $categoryToken) {
                            $category = $categoryToken;
                        }

                        break;
                    } else {
                        break;
                    }
                }

                if ($message) {
                    $result[] = [
                        $message,
                        $category,
                    ];
                    break;
                }
            }
        }

        gc_mem_caches();

        return $result;
    }

    /**
     * Normalizes a token.
     *
     * @param mixed $token
     *
     * @return string
     */
    protected function normalizeToken($token)
    {
        if (isset($token[1]) && 'b"' !== $token) {
            return $token[1];
        }

        return $token;
    }

    /**
     * Seeks to a non-whitespace token.
     */
    private function seekToNextRelevantToken(\Iterator $tokenIterator)
    {
        for (; $tokenIterator->valid(); $tokenIterator->next()) {
            $t = $tokenIterator->current();
            if (T_WHITESPACE !== $t[0]) {
                break;
            }
        }
    }

    private function skipMethodArgument(\Iterator $tokenIterator)
    {
        $openBraces = 0;

        for (; $tokenIterator->valid(); $tokenIterator->next()) {
            $t = $tokenIterator->current();

            if ('[' === $t[0] || '(' === $t[0]) {
                ++$openBraces;
            }

            if (']' === $t[0] || ')' === $t[0]) {
                --$openBraces;
            }

            if ((0 === $openBraces && ',' === $t[0]) || (-1 === $openBraces && ')' === $t[0])) {
                break;
            }
        }
    }

    /**
     * Extracts the message from the iterator while the tokens
     * match allowed message tokens.
     */
    private function getValue(\Iterator $tokenIterator)
    {
        $message = '';
        $docToken = '';
        $docPart = '';

        for (; $tokenIterator->valid(); $tokenIterator->next()) {
            $t = $tokenIterator->current();
            if ('.' === $t) {
                // Concatenate with next token
                continue;
            }
            if (!isset($t[1])) {
                break;
            }

            switch ($t[0]) {
                case T_START_HEREDOC:
                    $docToken = $t[1];
                    break;
                case T_ENCAPSED_AND_WHITESPACE:
                case T_CONSTANT_ENCAPSED_STRING:
                    if ('' === $docToken) {
                        $message .= PhpStringTokenParser::parse($t[1]);
                    } else {
                        $docPart = $t[1];
                    }
                    break;
                case T_END_HEREDOC:
                    $message .= PhpStringTokenParser::parseDocString($docToken, $docPart);
                    $docToken = '';
                    $docPart = '';
                    break;
                case T_WHITESPACE:
                    break;
                default:
                    break 2;
            }
        }

        return $message;
    }
}
