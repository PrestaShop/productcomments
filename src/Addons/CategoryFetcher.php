<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\ProductComment\Addons;

use Doctrine\Common\Cache\FilesystemCache;
use DOMDocument;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Subscriber\Cache\CacheStorage;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use PrestaShop\CircuitBreaker\AdvancedCircuitBreakerFactory;
use PrestaShop\CircuitBreaker\FactorySettings;
use PrestaShop\CircuitBreaker\Storage\DoctrineCache;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Class CategoryFetcher helps you to fetch an Addon category data. It calls the Addons
 * API for name and link, and scrap the Addons platform to get its description.
 * Every call is protected by a CircuitBreaker to avoid blocking the back-office.
 */
class CategoryFetcher
{
    const CACHE_DURATION = 86400; //24 hours

    const ADDONS_BASE_URL = 'https://addons.prestashop.com';
    const ADDONS_API_URL = 'https://api-addons.prestashop.com';

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

    /** @var AdvancedCircuitBreakerFactory */
    private $factory;

    /** @var FactorySettings */
    private $apiSettings;

    /** @var FactorySettings */
    private $platformSettings;

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

        //Doctrine cache used for Guzzle and CircuitBreaker storage
        $doctrineCache = new FilesystemCache(_PS_CACHE_DIR_ . '/addons_category');

        //Init Guzzle cache
        $cacheStorage = new CacheStorage($doctrineCache, null, self::CACHE_DURATION);
        $cacheSubscriber = new CacheSubscriber($cacheStorage, function (Request $request) { return true; });

        //Init circuit breaker factory
        $storage = new DoctrineCache($doctrineCache);
        $this->apiSettings = new FactorySettings(self::CLOSED_ALLOWED_FAILURES, self::API_TIMEOUT_SECONDS, 0);
        $this->apiSettings
            ->setThreshold(self::OPEN_THRESHOLD_SECONDS)
            ->setStrippedFailures(self::OPEN_ALLOWED_FAILURES)
            ->setStrippedTimeout(self::OPEN_TIMEOUT_SECONDS)
            ->setStorage($storage)
            ->setClientOptions([
                'subscribers' => [$cacheSubscriber],
                'method' => 'POST',
            ])
        ;

        $this->platformSettings = new FactorySettings(self::CLOSED_ALLOWED_FAILURES, self::PLATFORM_TIMEOUT_SECONDS, 0);
        $this->platformSettings
            ->setThreshold(self::OPEN_THRESHOLD_SECONDS)
            ->setStrippedFailures(self::OPEN_ALLOWED_FAILURES)
            ->setStrippedTimeout(self::OPEN_TIMEOUT_SECONDS)
            ->setStorage($storage)
            ->setClientOptions([
                'subscribers' => [$cacheSubscriber],
                'method' => 'GET',
            ])
        ;

        $this->factory = new AdvancedCircuitBreakerFactory();
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
        $circuitBreaker = $this->factory->create($this->apiSettings);
        $apiJsonResponse = $circuitBreaker->call(
            self::ADDONS_API_URL . '?iso_lang=' . $isoCode, //Include language in url to correctly cache results
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

        $circuitBreaker = $this->factory->create($this->platformSettings);
        $categoryResponse = $circuitBreaker->call($category['clean_link']);
        if (empty($categoryResponse)) {
            return $defaultDescription;
        }

        $cssSelector = new CssSelectorConverter();
        $document = new DOMDocument();

        // if fetched HTML is not valid, DOMDocument::loadHtml() will generate E_WARNING warnings
        libxml_use_internal_errors(true);
        $document->loadHTML($categoryResponse);
        libxml_use_internal_errors(false);

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
