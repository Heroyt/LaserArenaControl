<?php

namespace App\Latte;

use App\Latte\Nodes\IconNode;
use App\Services\FontAwesomeManager;
use Latte\ContentType;
use Latte\Engine;
use Latte\Extension;
use Latte\Runtime\FilterInfo;
use Latte\Runtime\HtmlStringable;
use Symfony\Component\Serializer\SerializerInterface;

/**
 *
 */
class LacExtension extends Extension
{
    public function __construct(
      private readonly SerializerInterface $serializer,
      private readonly FontAwesomeManager  $fontAwesomeManager,
    ) {}

    public function beforeCompile(Engine $engine) : void {
        $this->fontAwesomeManager->resetIcons();
        parent::beforeCompile($engine);
    }

    public function getTags() : array {
        return [
          'fa'      => [IconNode::class, 'create'],
          'faSolid' => [IconNode::class, 'createSolid'],
          'faRegular' => [IconNode::class, 'createRegular'],
          'faBrand' => [IconNode::class, 'createBrand'],
        ];
    }

    public function getFilters() : array {
        return [
          'escapeJs'    => $this->escapeJs(...),
          'json'        => $this->filterJson(...),
          'xml'         => $this->filterXml(...),
          'csv'         => $this->csvSerialize(...),
          'splitGroups' => $this->filterSplitGroups(...),
        ];
    }

    /**
     * @template T
     * @param  T[]  $elements
     * @param  int<1, max>  $min
     * @param  int<1, max>  $max
     * @return T[][]
     */
    public function filterSplitGroups(array $elements, int $min = 1, int $max = 10) : array {
        $total = count($elements);
        if ($total < $max) {
            return [$elements]; // The elements fit into one group
        }

        // Try to split the groups evenly
        $maxRemainder = 0;
        $maxGroupSize = $max;
        for ($i = $max; $i >= $min; $i--) {
            $remainder = $total % $i;
            if ($remainder === 0) {
                return array_chunk($elements, $i);
            }
            if ($remainder > $maxRemainder) {
                $maxRemainder = $remainder;
                $maxGroupSize = $i;
            }
        }

        // If elements cannot be split evenly, split them into group that have the largest remainder
        return array_chunk($elements, $maxGroupSize);
    }

    public function getFunctions() : array {
        return [
          'json'     => [$this, 'jsonSerialize'],
          'xml'      => [$this, 'xmlSerialize'],
          'csv'      => [$this, 'csvSerialize'],
          'faSolid'  => [$this->fontAwesomeManager, 'solid'],
          'faRegular' => [$this->fontAwesomeManager, 'regular'],
          'faBrands' => [$this->fontAwesomeManager, 'brands'],
          'fa'       => [$this->fontAwesomeManager, 'icon'],
        ];
    }

    public function filterJson(FilterInfo $info, mixed $data) : string {
        $info->contentType = ContentType::JavaScript;
        return str_replace([']]>', '<!', '</'], [']]\u003E', '\u003C!', '<\/'], $this->jsonSerialize($data));
    }

    public function jsonSerialize(mixed $s) : string {
        return $this->serializer->serialize($s, 'json');
    }

    public function filterXml(FilterInfo $info, mixed $data) : string {
        $info->contentType = ContentType::Xml;
        return $this->xmlSerialize($data);
    }

    public function xmlSerialize(mixed $s) : string {
        return $this->serializer->serialize($s, 'xml');
    }

    public function csvSerialize(mixed $s) : string {
        return $this->serializer->serialize($s, 'csv');
    }

    /**
     * Escapes variables for use inside <script>.
     */
    public function escapeJs(mixed $s) : string {
        if ($s instanceof HtmlStringable) {
            $s = $s->__toString();
        }

        $json = $this->serializer->serialize($s, 'json');

        return str_replace([']]>', '<!', '</'], [']]\u003E', '\u003C!', '<\/'], $json);
    }
}
