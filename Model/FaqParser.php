<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Model;

use Magento\Framework\Serialize\Serializer\Json;

/**
 * Parses the JSON content of the seoai_faq / seoai_howto attributes into
 * plain arrays safe for use in templates and JSON-LD graph builders.
 *
 * Fault-tolerant by design: invalid JSON, non-array roots or malformed
 * entries yield an empty array instead of throwing.
 */
class FaqParser
{
    private Json $serializer;

    public function __construct(Json $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Parse the seoai_faq JSON attribute value.
     *
     * @return array<int, array{q:string,a:string}>
     */
    public function parseFaq(string $json): array
    {
        $items = $this->decode($json);
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $q = isset($item['q']) ? trim((string) $item['q']) : '';
            $a = isset($item['a']) ? trim((string) $item['a']) : '';
            if ($q === '' && $a === '') {
                continue;
            }
            $result[] = ['q' => $q, 'a' => $a];
        }

        return $result;
    }

    /**
     * Parse the seoai_howto JSON attribute value.
     *
     * @return array<int, array{name:string,text:string,url:string}>
     */
    public function parseHowto(string $json): array
    {
        $items = $this->decode($json);
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = isset($item['name']) ? trim((string) $item['name']) : '';
            $text = isset($item['text']) ? trim((string) $item['text']) : '';
            $url  = isset($item['url'])  ? trim((string) $item['url'])  : '';
            if ($name === '' && $text === '') {
                continue;
            }
            $result[] = ['name' => $name, 'text' => $text, 'url' => $url];
        }

        return $result;
    }

    /**
     * Decode a JSON string into an array, swallowing any error.
     *
     * @return array
     */
    private function decode(string $json): array
    {
        $trimmed = trim($json);
        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($trimmed);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
