<?php
/**
 * Copyright © Eriocnemis, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eriocnemis\DownloadableImportExport\Model\Export;

use \Magento\CatalogImportExport\Model\Export\RowCustomizerInterface;
use \Magento\Catalog\Api\Data\ProductInterface;
use \Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use \Magento\ImportExport\Model\Import as ImportModel;
use \Magento\Downloadable\Api\Data\LinkInterface;
use \Magento\Downloadable\Api\Data\SampleInterface;
use \Magento\Downloadable\Model\ComponentInterface;
use \Magento\Downloadable\Model\Product\Type;

/**
 * Downloadable row customizer
 */
class RowCustomizer implements RowCustomizerInterface
{
    /**
     * Column downloadable samples
     */
    const SAMPLES_COLUMN = 'downloadable_samples';

    /**
     * Column downloadable links
     */
    const LINKS_COLUMN = 'downloadable_links';

    /**
     * Default sort order
     */
    const DEFAULT_SORT_ORDER = 0;

    /**
     * Default number of downloads
     */
    const DEFAULT_NUMBER_OF_DOWNLOADS = 0;

    /**
     * Default is shareable
     */
    const DEFAULT_IS_SHAREABLE = 2;

    /**
     * Default links can be purchased separately
     */
    const DEFAULT_PURCHASED_SEPARATELY = 1;

    /**
     * Product rows data
     *
     * @var array
     */
    protected $dataRow = [];

    /**
     * Column names
     *
     * @var array
     */
    protected $columns = [
        self::SAMPLES_COLUMN,
        self::LINKS_COLUMN
    ];

    /**
     * Prepare data for export
     *
     * @param ProductCollection $collection
     * @param int[] $productIds
     * @return void
     */
    public function prepareData($collection, $productIds)
    {
        $productCollection = clone $collection;
        $productCollection->addAttributeToFilter('entity_id', ['in' => $productIds])
            ->addAttributeToFilter('type_id', ['eq' => Type::TYPE_DOWNLOADABLE])
            ->addAttributeToSelect(['links_title', 'samples_title', 'links_purchased_separately']);

        return $this->populateProductData($productCollection);
    }

    /**
     * Populate products data
     *
     * @param ProductCollection $collection
     * @return $this
     */
    protected function populateProductData(ProductCollection $collection)
    {
        foreach ($collection as $product) {
            /* populate links data */
            if ($product->getTypeInstance()->hasLinks($product)) {
                $this->populateLinkData($product);
            }
            /* populate samples data */
            if ($product->getTypeInstance()->hasSamples($product)) {
                $this->populateSampleData($product);
            }
        }
        return $this;
    }

    /**
     * Populate links data
     *
     * @param ProductInterface $product
     * @return $this
     */
    protected function populateLinkData(ProductInterface $product)
    {
        $links = [];
        foreach ($product->getTypeInstance()->getLinks($product) as $link) {
            $option = array_merge(
                $this->prepareLinkData($product, $link),
                $this->prepareAdditionalLinkData($link)
            );
            $links[] = $this->getFormattedRow($option);
        }
        if ($links) {
            $this->dataRow[$product->getId()][self::LINKS_COLUMN] = implode('|', $links);
        }
        return $this;
    }

    /**
     * Populate sample data
     *
     * @param ProductInterface $product
     * @return $this
     */
    protected function populateSampleData(ProductInterface $product)
    {
        $samples = [];
        foreach ($product->getTypeInstance()->getSamples($product) as $sample) {
            $option = array_merge(
                $this->prepareSampleData($product, $sample),
                $this->prepareAdditionalSampleData($sample)
            );
            $samples[] = $this->getFormattedRow($option);
        }
        if ($samples) {
            $this->dataRow[$product->getId()][self::SAMPLES_COLUMN] = implode('|', $samples);
        }
        return $this;
    }

    /**
     * Retrieve formatted row data
     *
     * @param array $options
     * @return string
     */
    protected function getFormattedRow($options)
    {
        return implode(
            ImportModel::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR,
            array_map(
                function ($value, $key) {
                    return $key . '=' . $value;
                },
                $options,
                array_keys($options)
            )
        );
    }

    /**
     * Prepare link options
     *
     * @param ProductInterface $product
     * @param LinkInterface $link
     * @return array
     */
    public function prepareLinkData(ProductInterface $product, LinkInterface $link)
    {
        return [
            'group_title' => $product->getLinksTitle(),
            'purchased_separately' => $product->getLinksPurchasedSeparately(),
            'title' => $link->getTitle()
        ];
    }

    /**
     * Prepare additional link options
     *
     * @param LinkInterface $link
     * @return array
     */
    public function prepareAdditionalLinkData(LinkInterface $link)
    {
        return  array_merge(
            $this->preparePrice($link),
            $this->prepareDownloads($link),
            $this->prepareSortOrder($link),
            $this->prepareShareable($link),
            $this->prepareLink($link),
            $this->prepareLinkSample($link)
        );
    }

    /**
     * Prepare link option
     *
     * @param LinkInterface $link
     * @return array
     */
    protected function prepareLink(LinkInterface $link)
    {
        return $link->getLinkType() == 'file'
            ? ['file' => $link->getLinkFile()]
            : ['url' => $link->getLinkUrl()];
    }

    /**
     * Prepare link sample option
     *
     * @param LinkInterface $link
     * @return array
     */
    protected function prepareLinkSample(LinkInterface $link)
    {
        if ($link->getSampleFile() || $link->getSampleUrl()) {
            return $link->getSampleType() == 'file'
                ? ['sample_file' => $link->getSampleFile()]
                : ['sample_url' => $link->getSampleUrl()];
        }
        return [];
    }

    /**
     * Prepare sample options
     *
     * @param ProductInterface $product
     * @param SampleInterface $sample
     * @return array
     */
    public function prepareSampleData(ProductInterface $product, SampleInterface $sample)
    {
        return [
            'group_title' => $product->getSamplesTitle(),
            'title' => $sample->getTitle()
        ];
    }

    /**
     * Prepare additional sample options
     *
     * @param SampleInterface $sample
     * @return array
     */
    public function prepareAdditionalSampleData(SampleInterface $sample)
    {
        return  array_merge(
            $this->prepareSortOrder($sample),
            $this->prepareSample($sample)
        );
    }

    /**
     * Prepare sample option
     *
     * @param SampleInterface $sample
     * @return array
     */
    protected function prepareSample(SampleInterface $sample)
    {
        return $sample->getSampleType() == 'file'
            ? ['file' => $sample->getSampleFile()]
            : ['url' => $sample->getSampleUrl()];
    }

    /**
     * Prepare price option
     *
     * @param LinkInterface $link
     * @return array
     */
    protected function preparePrice(LinkInterface $link)
    {
        return $link->getPrice()
            ? ['price' => $link->getPrice()]
            : [];
    }

    /**
     * Prepare downloads option
     *
     * @param LinkInterface $link
     * @return array
     */
    protected function prepareDownloads(LinkInterface $link)
    {
        return $link->getNumberOfDownloads() > self::DEFAULT_NUMBER_OF_DOWNLOADS
            ? ['downloads' => $link->getNumberOfDownloads()]
            : [];
    }

    /**
     * Prepare shareable option
     *
     * @param LinkInterface $link
     * @return array
     */
    protected function prepareShareable(LinkInterface $link)
    {
        return $link->getIsShareable() != self::DEFAULT_IS_SHAREABLE
            ? ['shareable' => $link->getIsShareable()]
            : [];
    }

    /**
     * Prepare sort order option
     *
     * @param ComponentInterface $link
     * @return array
     */
    protected function prepareSortOrder(ComponentInterface $link)
    {
        return $link->getSortOrder() > self::DEFAULT_SORT_ORDER
            ? ['sortorder' => $link->getSortOrder()]
            : [];
    }

    /**
     * Set headers columns
     *
     * @param array $columns
     * @return array
     */
    public function addHeaderColumns($columns)
    {
        return array_merge($columns, $this->columns);
    }

    /**
     * Add data for export
     *
     * @param array $dataRow
     * @param int $productId
     * @return array
     */
    public function addData($dataRow, $productId)
    {
        if (!empty($this->dataRow[$productId])) {
            $dataRow = array_merge($dataRow, $this->dataRow[$productId]);
        }
        return $dataRow;
    }

    /**
     * Calculate the largest links block
     *
     * @param array $additionalRowsCount
     * @param int $productId
     * @return array
     */
    public function getAdditionalRowsCount($additionalRowsCount, $productId)
    {
        if (!empty($this->dataRow[$productId])) {
            $additionalRowsCount = max(
                $additionalRowsCount,
                count($this->dataRow[$productId])
            );
        }
        return $additionalRowsCount;
    }
}
