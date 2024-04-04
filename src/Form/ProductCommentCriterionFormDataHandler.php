<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
declare(strict_types=1);

namespace PrestaShop\Module\ProductComment\Form;

use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterionLang;
use PrestaShop\Module\ProductComment\Repository\ProductCommentCriterionRepository;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\FormDataHandlerInterface;
use PrestaShopBundle\Entity\Repository\LangRepository;

class ProductCommentCriterionFormDataHandler implements FormDataHandlerInterface
{
    /**
     * @var ProductCommentCriterionRepository
     */
    private $pccriterionRepository;

    /**
     * @var LangRepository
     */
    private $langRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param ProductCommentCriterionRepository $pccriterionRepository
     * @param LangRepository $langRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ProductCommentCriterionRepository $pccriterionRepository,
        LangRepository $langRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->pccriterionRepository = $pccriterionRepository;
        $this->langRepository = $langRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function update($id, array $data)
    {
    }

    /**
     * @param ProductCommentCriterion $pccriterion
     * @param array $pcc_languages
     *
     * @todo migrate this temporary function to above standard function create
     */
    public function createLangs($pccriterion, $pcc_languages): void
    {
        foreach ($pcc_languages as $langId => $langContent) {
            $lang = $this->langRepository->find($langId);
            $pccriterionLang = new ProductCommentCriterionLang();
            $pccriterionLang
                ->setLang($lang)
                ->setName($langContent)
            ;
            $pccriterion->addCriterionLang($pccriterionLang);
        }

        $this->entityManager->persist($pccriterion);
        $this->entityManager->flush();
    }

    /**
     * @param ProductCommentCriterion $pccriterion
     * @param array $pcc_languages
     *
     * @todo migrate this temporary function to above standard function update
     */
    public function updateLangs($pccriterion, $pcc_languages): void
    {
        foreach ($pcc_languages as $langId => $langContent) {
            $lang = $this->langRepository->find($langId);
            $pccriterionLang = $pccriterion->getCriterionLangByLangId($langId);
            if (null === $pccriterionLang) {
                continue;
            }
            $pccriterionLang
                ->setName($langContent)
            ;
        }

        $this->entityManager->persist($pccriterion);
        $this->entityManager->flush();
    }
}
