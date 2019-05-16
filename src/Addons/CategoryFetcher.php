<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\ProductComment\Addons;

use PrestaShop\CircuitBreaker\AdvancedCircuitBreakerFactory;
use PrestaShop\CircuitBreaker\Contracts\Factory;
use PrestaShop\CircuitBreaker\Storages\SymfonyCache;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\CssSelector\CssSelectorConverter;
use DOMDocument;
use DOMXPath;
use DOMNode;

/**
 * Class CategoryFetcher helps you to fetch an Addon category data. It calls the Addons
 * API for name and link, and scrap the Addons platform to get its description.
 * Every call is protected by a CircuitBreaker to avoid blocking the back-office.
 */
class CategoryFetcher
{
    const ADDONS_BASE_URL = 'https://addons.prestashop.com';

    const CLOSED_ALLOWED_FAILURES = 2;
    const API_TIMEOUT_SECONDS = 0.6;

    /**
     * The timeout is longer for Addons platform as the content is bigger (HTML content)
     */
    const PLATFORM_TIMEOUT_SECONDS = 2;

    const OPEN_ALLOWED_FAILURES = 1;
    const OPEN_TIMEOUT_SECONDS = 1.2;

    const OPEN_THRESHOLD_SECONDS = 60;

    /** @var int */
    private $categoryId;

    /** @var array */
    private $defaultData;

    /** @var Factory */
    private $factory;

    /**
     * @param int $categoryId
     * @param array $defaultData
     */
    public function __construct(
        $categoryId,
        array $defaultData
    ) {
        $this->categoryId = $categoryId;
        $this->defaultData = array_merge([
            'id_category' => (int) $categoryId,
        ], $defaultData);

        $storage = new SymfonyCache(new FilesystemCache(
            'addons_category',
            0,
            _PS_CACHE_DIR_
        ));
        $this->factory = new AdvancedCircuitBreakerFactory([
            'closed' => [self::CLOSED_ALLOWED_FAILURES, self::API_TIMEOUT_SECONDS, 0],
            'open' => [0, 0, self::OPEN_THRESHOLD_SECONDS],
            'half_open' => [self::OPEN_ALLOWED_FAILURES, self::OPEN_TIMEOUT_SECONDS, 0],
            'storage' => $storage,
        ]);
    }

    /**
     * @param string $isoCode Two letters iso code to identify the country (ex: en, fr, es, ...)
     *
     * @return array
     */
    public function getData($isoCode)
    {
        $category = $this->getCategoryFromApi($isoCode);
        $category = $this->addTracking($category, $isoCode);
        $category['description'] = $this->getDescription($category);

        return $category;
    }

    /**
     * @param string $isoCode
     *
     * @return array
     */
    private function getCategoryFromApi($isoCode)
    {
        $circuitBreaker = $this->factory->create([
            'client' => [
                'method' => 'POST',
            ],
        ]);
        $apiJsonResponse = $circuitBreaker->call(
            self::ADDONS_BASE_URL,
            function () { return false; },
            [
                'body' => [
                    'method' => 'listing',
                    'action' => 'categories',
                    'version' => '1.7',
                    'iso_lang' => $isoCode,
                ],
            ]
        );
        $apiResponse = !empty($apiJsonResponse) ? json_decode($apiJsonResponse, true) : false;
        $category = null;
        if (false !== $apiResponse && !empty($apiResponse['module']) && empty($apiResponse['errors'])) {
            $category = $this->searchCategory($apiResponse['module'], $this->categoryId);
        }

        return null !== $category ? $category : $this->defaultData;
    }

    /**
     * @param array $categories
     * @param int $searchedCategoryId
     *
     * @return array|null
     */
    private function searchCategory(array $categories, $searchedCategoryId)
    {
        foreach ($categories as $category) {
            if (!empty($category['id_category']) && $searchedCategoryId == $category['id_category']) {
                return $category;
            }

            if (!empty($category['categories'])) {
                $subCategory = $this->searchCategory($category['categories'], $searchedCategoryId);
                if (null !== $subCategory) {
                    return $subCategory;
                }
            }
        }

        return null;
    }

    /**
     * @param array $category
     *
     * @return string
     */
    private function getDescription(array $category)
    {
        $defaultDescription = !empty($this->defaultData['description']) ? $this->defaultData['description'] : '';
        //Clean link used to fetch description (no need for tracking then)
        if (empty($category['clean_link'])) {
            return $defaultDescription;
        }

        $circuitBreaker = $this->factory->create([
            'closed' => [self::CLOSED_ALLOWED_FAILURES, self::PLATFORM_TIMEOUT_SECONDS, 0],
            'client' => [
                'method' => 'GET',
            ],
        ]);

        $categoryResponse = $circuitBreaker->call($category['clean_link'], function () { return false; });
        if (empty($categoryResponse)) {
            return $defaultDescription;
        }

        $cssSelector = new CssSelectorConverter();
        $document = new DOMDocument();
        $document->loadHTML($categoryResponse);
        $xpath = new DOMXPath($document);
        $descriptionNode = $xpath->query($cssSelector->toXPath('#category_description'))->item(0);
        $categoryDescription = '';
        /** @var DOMNode $childNode */
        foreach ($descriptionNode->childNodes as $childNode) {
            $categoryDescription .= $childNode->ownerDocument->saveHTML($childNode);
        }

        return !empty($categoryDescription) ? $categoryDescription : $defaultDescription;
    }

    /**
     * Updates link property with a correctly formatted url with tracking parameters
     *
     * @param array $category
     * @param string $isoCode
     *
     * @return array
     */
    private function addTracking(array $category, $isoCode)
    {
        if (empty($category['link'])) {
            return $category;
        }

        $parsedUrl = parse_url($category['link']);
        if (false === $parsedUrl) {
            return $category;
        }

        $parameters = [];
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $parameters);
        }

        $parameters['utm_source'] = 'back-office';
        $parameters['utm_medium'] = 'modules';
        $parameters['utm_campaign'] = 'back-office-' . strtoupper($isoCode);

        //Clean link used to fetch description (no need for tracking then)
        $category['clean_link'] = self::ADDONS_BASE_URL . $parsedUrl['path'];
        $category['link'] = self::ADDONS_BASE_URL . $parsedUrl['path'] . '?' . http_build_query($parameters);

        return $category;
    }
}
