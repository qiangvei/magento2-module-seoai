<?php
declare(strict_types=1);

namespace Qiangvei\SeoAi\Block\Schema;

/**
 * Organization / LocalBusiness schema for the store home page.
 *
 * Pulls name/logo/sameAs from the schema group and contact info from the GEO
 * entity_* configuration. Falls back to store name when no explicit
 * organization name is configured.
 */
class Organization extends JsonLd
{
    protected function getGraph(): ?array
    {
        if (!$this->config->isOrganizationSchemaEnabled()) {
            return null;
        }

        $store = $this->_storeManager->getStore();
        $storeCode = $store->getCode();

        $name = $this->cleanText($this->config->getOrganizationName($storeCode));
        if ($name === '') {
            $name = $this->cleanText($store->getFrontendName());
        }
        if ($name === '') {
            return null;
        }

        $url = $this->getStoreBaseUrl();
        $logo = $this->cleanText($this->config->getOrganizationLogo($storeCode));

        $graph = [
            '@type' => 'Organization',
            'name'  => $name,
            'url'   => $url,
        ];

        if ($logo !== '') {
            $graph['logo'] = $logo;
        }

        $sameAs = array_values(array_filter(array_map(
            fn ($u) => $this->cleanText($u),
            array_merge(
                $this->config->getOrganizationSameAs($storeCode),
                $this->config->getBrandSameAs($storeCode)
            )
        )));
        if ($sameAs !== []) {
            $graph['sameAs'] = array_values(array_unique($sameAs));
        }

        $contactPoint = $this->buildContactPoint($storeCode);
        if ($contactPoint !== null) {
            $graph['contactPoint'] = $contactPoint;
        }

        return $graph;
    }

    private function buildContactPoint(string $storeCode): ?array
    {
        $phone = $this->cleanText($this->config->getEntityPhone($storeCode));
        $address = $this->cleanText($this->config->getEntityAddress($storeCode));

        if ($phone === '' && $address === '') {
            return null;
        }

        $contact = ['@type' => 'ContactPoint', 'contactType' => 'customer support'];
        if ($phone !== '') {
            $contact['telephone'] = $phone;
        }
        if ($address !== '') {
            $contact['areaServed'] = $address;
        }

        return $contact;
    }
}
