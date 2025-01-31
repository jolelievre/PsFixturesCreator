<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
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
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\Module\PsFixturesCreator\Command;

use Db;
use PrestaShop\Module\PsFixturesCreator\CartCreator;
use PrestaShop\Module\PsFixturesCreator\CartRuleCreator;
use PrestaShop\Module\PsFixturesCreator\CustomerCreator;
use PrestaShop\Module\PsFixturesCreator\OrderCreator;
use PrestaShop\Module\PsFixturesCreator\ProductCombinationCreator;
use PrestaShop\Module\PsFixturesCreator\ProductCreator;
use Shop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command is used for appending the hook names in the configuration file.
 */
class ShopCreatorCommand extends Command
{
    private CustomerCreator $customerCreator;

    private CartCreator $cartCreator;

    private OrderCreator $orderCreator;

    private CartRuleCreator $cartRuleCreator;

    private ProductCreator $productCreator;

    private ProductCombinationCreator $productCombinationCreator;

    public function __construct(
        CustomerCreator $customerCreator,
        CartCreator $cartCreator,
        OrderCreator $orderCreator,
        CartRuleCreator $cartRuleCreator,
        ProductCreator $productCreator,
        ProductCombinationCreator $productCombinationCreator
    ) {
        parent::__construct(null);

        $this->customerCreator = $customerCreator;
        $this->cartCreator = $cartCreator;
        $this->orderCreator = $orderCreator;
        $this->cartRuleCreator = $cartRuleCreator;
        $this->productCreator = $productCreator;
        $this->productCombinationCreator = $productCombinationCreator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('prestashop:shop-creator')
            ->addOption('orders', null, InputOption::VALUE_OPTIONAL, 'Number of orders to create', 0)
            ->addOption('customers', null, InputOption::VALUE_OPTIONAL, 'Number of customers without order to create', 0)
            ->addOption('carts', null, InputOption::VALUE_OPTIONAL, 'Number of carts to create', 0)
            ->addOption('cart-rules', null, InputOption::VALUE_OPTIONAL, 'Number of cart rules to create', 0)
            ->addOption('products', null, InputOption::VALUE_OPTIONAL, 'Number of products to create', 0)
            ->addOption('shopId', null, InputOption::VALUE_OPTIONAL, 'The shop identifier', 1)
            ->addOption('shopGroupId', null, InputOption::VALUE_OPTIONAL, 'The shop group identifier', 1)
            ->addOption('languageId', null, InputOption::VALUE_OPTIONAL, 'The languageId identifier', 1)

            // values for product combinations, if all three parameters are not above zero, nothing will be done
            ->addOption('attributeGroups', null, InputOption::VALUE_OPTIONAL, 'Number of attribute groups', 0)
            ->addOption('attributes', null, InputOption::VALUE_OPTIONAL, 'Number of attributes per attribute group', 0)
            ->addOption('targetProductIdForAttributes', null, InputOption::VALUE_OPTIONAL, 'ID of the product that will receive attributes', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \Context::getContext()->currency = \Currency::getDefaultCurrency();

        $numberOfOrders = (int) $input->getOption('orders');
        $numberOfCustomerWithoutOrder = (int) $input->getOption('customers');
        $numberOfCarts = (int) $input->getOption('carts');
        $numberOfCartRules = (int) $input->getOption('cart-rules');
        $numberOfProducts = (int) $input->getOption('products');
        $idLang = (int) $input->getOption('languageId');
        $idshop = (int) $input->getOption('shopId');
        $idShopGroup = (int) $input->getOption('shopGroupId');
        $numberOfAttributeGroups = (int) $input->getOption('attributeGroups');
        $numberOfAttributes = (int) $input->getOption('attributes');
        $targetProductIdForAttributes = (int) $input->getOption('targetProductIdForAttributes');

        $productIds = $this->getSimpleProducts($idLang);

        // Create customers (without order)
        $this->customerCreator->generate($numberOfCustomerWithoutOrder);
        $output->writeln(sprintf('%s customer(s) without orders created.', $numberOfCustomerWithoutOrder));

        // Create carts
        $this->cartCreator->generate($numberOfCarts, $productIds);
        $output->writeln(sprintf('%s cart(s) created.', $numberOfCarts));

        // Create orders
        $this->orderCreator->generate($numberOfOrders, $idShopGroup, $idshop, $productIds);
        $output->writeln(sprintf('%s order(s) created.', $numberOfOrders));

        // create cart rules
        $this->cartRuleCreator->generate($numberOfCartRules, $idLang);
        $output->writeln(sprintf('%s cart rule(s) created.', $numberOfCartRules));

        // create products
        $this->productCreator->generate($numberOfProducts, $idLang);
        $output->writeln(sprintf('%s product(s) created', $numberOfProducts));

        // create product combination (attribute groups and attributes which are then attached to a product)
        if ($numberOfAttributeGroups > 0 && $numberOfAttributes > 0 && $targetProductIdForAttributes > 0) {
            $this->productCombinationCreator->generate($numberOfAttributeGroups, $numberOfAttributes, $targetProductIdForAttributes, $idLang, $idshop);
            $output->writeln(sprintf('Created %s attribute group(s) with %s different values each for product with ID %s.', $numberOfAttributeGroups, $numberOfAttributes, $targetProductIdForAttributes));
        } else {
            $output->writeln('0 product combination(s) created.');
        }

        return 0;
    }

    /**
     * @param int $id_lang Language identifier
     *
     * @return array
     */
    private function getSimpleProducts($id_lang, bool $front = true): bool|array
    {
        $sql = 'SELECT p.`id_product`, pl.`name`
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')
                WHERE pl.`id_lang` = ' . (int) $id_lang . '
                ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                ORDER BY pl.`name`';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
