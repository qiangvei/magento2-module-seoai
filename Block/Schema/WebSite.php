<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

/**
 * WebSite + SearchAction schema. Uses current store base url and store name.
 */
class WebSite extends JsonLd
{
    protected function getGraph(): ?array
    {
        if (!$this->config->isWebsiteSearchSchemaEnabled()) {
            return null;
        }

        $store = $this->_storeManager->getStore();
        $baseUrl = $this->getStoreBaseUrl();
        $storeName = $this->cleanText($store->getFrontendName()) ?: $baseUrl;

        $graph = [
            '@type'       => 'WebSite',
            'name'        => $storeName,
            'url'         => $baseUrl,
            'inLanguage'  => $store->getConfig('general/locale/code') ?: 'en-US',
        ];

        $graph['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $baseUrl . 'catalogsearch/result/?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ];

        return $graph;
    }
}
