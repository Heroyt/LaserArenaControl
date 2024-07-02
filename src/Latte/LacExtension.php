<?php

namespace App\Latte;

use Latte\ContentType;
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
    ) {}

    public function getFilters() : array {
        return [
          'escapeJs' => [$this, 'escapeJs'],
          'json'     => [$this, 'filterJson'],
          'xml'      => [$this, 'filterXml'],
          'csv'      => [$this, 'csvSerialize'],
        ];
    }

    public function getFunctions() : array {
        return [
          'json' => [$this, 'jsonSerialize'],
          'xml'  => [$this, 'xmlSerialize'],
          'csv'  => [$this, 'csvSerialize'],
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