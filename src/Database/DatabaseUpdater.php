<?php
/**
 * 2007-2020 PrestaShop SA and Contributors
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
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\ProductComment\Database;

use Doctrine\ORM\EntityManager;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterion;
use PrestaShop\Module\ProductComment\Entity\ProductCommentCriterionLang;
use PrestaShop\PrestaShop\Core\Language\LanguageInterface;
use PrestaShopBundle\Service\Database\DoctrineFolderSchemaUpdater;
use Symfony\Component\Translation\TranslatorInterface;

class DatabaseUpdater
{
    /**
     * @var DoctrineFolderSchemaUpdater
     */
    private $schemaUpdater;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var array
     */
    private $languages;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $modulesFolder;

    /**
     * @param DoctrineFolderSchemaUpdater $schemaUpdater
     * @param EntityManager $em
     * @param array $languages
     * @param TranslatorInterface $translator
     * @param string $modulesFolder
     */
    public function __construct(
        DoctrineFolderSchemaUpdater $schemaUpdater,
        EntityManager $em,
        array $languages,
        TranslatorInterface $translator,
        string $modulesFolder
    ) {
        $this->schemaUpdater = $schemaUpdater;
        $this->em = $em;
        $this->languages = $languages;
        $this->translator = $translator;
        $this->modulesFolder = $modulesFolder;
    }

    public function installDatabase(string $moduleName)
    {
        $moduleEntitiesFolder = $this->modulesFolder . '/' . $moduleName . '/src/Entity';
        $this->schemaUpdater->updateFolderSchema($moduleEntitiesFolder);

        // Check if at least one criterion is present
        $criterionRepository = $this->em->getRepository(ProductCommentCriterion::class);
        $activeCriterionNb = $criterionRepository->count(['active' => 1]);
        if ($activeCriterionNb > 0) {
            return;
        }

        // Add a default criterion
        $criterion = new ProductCommentCriterion();
        $criterion
            ->setType(ProductCommentCriterion::ENTIRE_CATALOG_TYPE)
            ->setActive(true)
        ;

        /** @var LanguageInterface $language */
        foreach ($this->languages as $language) {
            $criterion->addLanguage(
                (new ProductCommentCriterionLang())
                    ->setLangId($language->getId())
                    ->setName($this->translator->trans('Quality', [], 'Modules.Productcomments.Shop', $language->getLocale()))
            );
        }

        $this->em->persist($criterion);
        $this->em->flush();
    }

    public function uninstallDatabase(string $moduleName)
    {
        $moduleEntitiesFolder = $this->modulesFolder . '/' . $moduleName . '/src/Entity';
        $this->schemaUpdater->removeFolderSchema($moduleEntitiesFolder);
    }
}
