<?php

namespace Framework\Foundation\HttpClient;

use InvalidArgumentException;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * 支持 Symfony http-client 4.4 版本同名文件参数上传
 * @link https://github.com/symfony/symfony/issues/38258
 * 5.2 之后的版本原生支持
 * @link https://symfony.com/doc/current/http_client.html#uploading-data
 *
 * 支持中文名（官方bug，暂未解决）
 * @link https://github.com/symfony/symfony/issues/41249
 *
 * 由于原代码是 final 的，所以直接复制修改
 */
final class FormDataPart extends AbstractMultipartPart
{
    private $fields = [];

    /**
     * @param (string|array|DataPart)[] $fields
     */
    public function __construct(array $fields = [])
    {
        parent::__construct();

        foreach ($fields as $name => $value) {
            if (!\is_string($value) && !\is_array($value) && !$value instanceof TextPart) {
                throw new InvalidArgumentException(sprintf('A form field value can only be a string, an array, or an instance of TextPart ("%s" given).', \is_object($value) ? \get_class($value) : \gettype($value)));
            }

            $this->fields[$name] = $value;
        }
        // HTTP does not support \r\n in header values
        $this->getHeaders()->setMaxLineLength(PHP_INT_MAX);
    }

    public function getMediaSubtype(): string
    {
        return 'form-data';
    }

    public function getParts(): array
    {
        return $this->prepareFields($this->fields);
    }

    private function prepareFields(array $fields): array
    {
        $values = [];

        $prepare = function ($item, $key, $root = null) use (&$values, &$prepare) {
            // 修改此处
            if (\is_int($key) && \is_array($item)) {
                if (1 !== \count($item)) {
                    throw new InvalidArgumentException(sprintf('Form field values with integer keys can only have one array element, the key being the field name and the value being the field value, %d provided.', \count($item)));
                }

                $key = key($item);
                $item = $item[$key];
            }

            $fieldName = null !== $root ? sprintf('%s[%s]', $root, $key) : $key;

            if (\is_array($item)) {
                array_walk($item, $prepare, $fieldName);

                return;
            }

            $values[] = $this->preparePart($fieldName, $item);
        };

        array_walk($fields, $prepare);

        return $values;
    }

    private function preparePart(string $name, $value): TextPart
    {
        if (\is_string($value)) {
            return $this->configurePart($name, new TextPart($value, 'utf-8', 'plain', '8bit'));
        }

        return $this->configurePart($name, $value);
    }

    private function configurePart(string $name, TextPart $part): TextPart
    {
        static $r;

        if (null === $r) {
            $r = new \ReflectionProperty(TextPart::class, 'encoding');
            $r->setAccessible(true);
        }

        $part->setDisposition('form-data');
        $part->setName($name);
        // HTTP does not support \r\n in header values
        $part->getHeaders()->setMaxLineLength(PHP_INT_MAX);
        $r->setValue($part, '8bit');

        return $part;
    }

    /**
     * 修改支持传递中文文件名
     * @inheritDoc
     */
    public function bodyToIterable(): iterable
    {
        foreach (parent::bodyToIterable() as $iterable) {
            if (strpos($iterable, 'filename*=utf-8\'\'') !== false) {
                preg_match('/filename\*=utf-8\'\'"(.*?)"/i', $iterable, $matches);
                if ($matches && count($matches) === 2) {
                    $realName = urldecode($matches[1]);
                    $iterable = preg_replace('/filename\*=utf-8\'\'"(.*?)"/i', 'filename="'. $realName .'"; filename\*=utf-8\'\'"$1"', $iterable);
                }
            }
            yield $iterable;
        }
    }
}
