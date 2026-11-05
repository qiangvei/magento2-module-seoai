<?php
/**
 * UNUSED / SUPERSEDED.
 *
 * robots.txt is served by Plugin\Robots\Controller (around-plugin on
 * Magento\Robots\Controller\Index\Index), which returns a Raw result with the
 * RobotsBuilder content when the module is enabled. This block-level
 * interception is therefore not registered in di.xml and intentionally left
 * empty so it is never autoloaded as a duplicate robots body provider.
 */
