<?php
/**
 * UNUSED / SUPERSEDED.
 *
 * The template-driven meta title/description generation feature has been
 * removed by request: Magento/Luma now keeps full control of the page title and
 * meta description. This module no longer intercepts Page\Config, so this
 * plugin is intentionally left empty and is NOT registered in etc/di.xml.
 *
 * The TemplateEngine / VariableResolver classes remain (used by the Open Graph
 * plugin to build og:title / og:description and by the batch generator), but the
 * stock <title> and <meta name="description"> output is no longer rewritten.
 */
