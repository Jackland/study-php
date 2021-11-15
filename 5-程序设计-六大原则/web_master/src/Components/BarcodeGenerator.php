<?php

namespace App\Components;

use LogicException;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorJPG;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeGenerator
{
    /**
     * @var BarcodeGeneratorHTML|BarcodeGeneratorPNG|BarcodeGeneratorJPG|BarcodeGeneratorSVG
     */
    private $generator;
    /**
     * @var string
     */
    private $type = \Picqer\Barcode\BarcodeGenerator::TYPE_CODABAR;
    /**
     * @var int
     */
    private $width = 2;
    /**
     * @var int
     */
    private $height = 30;
    /**
     * @var string
     */
    private $color = '#000000';

    public function html(): self
    {
        $this->generator = new BarcodeGeneratorHTML();
        return $this;
    }

    public function png(): self
    {
        $this->generator = new BarcodeGeneratorPNG();
        return $this;
    }

    public function jpg(): self
    {
        $this->generator = new BarcodeGeneratorJPG();
        return $this;
    }

    public function svg(): self
    {
        $this->generator = new BarcodeGeneratorSVG();
        return $this;
    }

    /**
     * 设置类型，详见: \Picqer\Barcode\BarcodeGenerator::TYPE_
     * 默认：Picqer\Barcode\BarcodeGenerator::TYPE_CODABAR
     * @param string $const
     * @return $this
     */
    public function type(string $const): self
    {
        $this->type = $const;
        return $this;
    }

    /**
     * 设置单个竖线的宽
     * 默认：2
     * @param int $width
     * @return $this
     */
    public function width(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * 设置高度
     * 默认：30
     * @param int $height
     * @return $this
     */
    public function height(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    /**
     * 设置颜色，16进制（#000000）的形式
     * 默认：#000000
     * @param string $color
     * @return $this
     */
    public function color(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    /**
     * 生成条形码
     * @param $content
     * @return string
     */
    public function generate($content): string
    {
        if (!$this->generator) {
            throw new LogicException('请先调用 html() 或 png() 或 jpg() 或 svg()');
        }

        if ($this->generator instanceof BarcodeGeneratorJPG || $this->generator instanceof BarcodeGeneratorPNG) {
            $this->color = $this->hex2rgb($this->color) ?: $this->color;
        }

        return $this->generator->getBarcode((string)$content, $this->type, $this->width, $this->height, $this->color);
    }

    /**
     * 生成可用于 html img 标签的值
     * @param $content
     * @return string
     */
    public function generateForImgSrc($content): string
    {
        $png = $this->png()->generate($content);
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * 十六进制转 rgb
     * @param string $color 例如：#CC00FF
     * @return false|array 例如：[204, 0, 255]
     */
    public function hex2rgb(string $color)
    {
        $hexColor = str_replace('#', '', $color);
        $lens = strlen($hexColor);
        if ($lens != 3 && $lens != 6) {
            return false;
        }
        $newColor = '';
        if ($lens == 3) {
            for ($i = 0; $i < $lens; $i++) {
                $newColor .= $hexColor[$i] . $hexColor[$i];
            }
        } else {
            $newColor = $hexColor;
        }
        $hex = str_split($newColor, 2);
        $rgb = [];
        foreach ($hex as $vls) {
            $rgb[] = hexdec($vls);
        }
        return $rgb;
    }
}
