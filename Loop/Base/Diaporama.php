<?php
/*************************************************************************************/
/*      This file is part of the "Diaporamas" Thelia 2 module.                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Diaporamas\Loop\Base;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\BooleanOrBothType;
use Diaporamas\Model\DiaporamaQuery;

/**
 * Class Diaporama
 * @package Diaporamas\Loop\Base
 * @author TheliaStudio
 */
class Diaporama extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;
    protected $versionable = true;

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Diaporamas\Model\Diaporama $entry */
        foreach ($loopResult->getResultDataCollection() as $entry) {
            $row = new LoopResultRow($entry);

            $row
                ->set("ID", $entry->getId())
                ->set("TITLE", $entry->getVirtualColumn("i18n_TITLE"))
                ->set("SHORTCODE", $entry->getShortcode())
            ;

            $this->addMoreResults($row, $entry);

            $loopResult->addRow($row);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument("id"),
            Argument::createAnyTypeArgument("title"),
            Argument::createAnyTypeArgument("shortcode"),
            Argument::createEnumListTypeArgument(
                "order",
                [
                    "id",
                    "id-reverse",
                    "title",
                    "title-reverse",
                    "shortcode",
                    "shortcode-reverse",
                ],
                "id"
            )
        );
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $query = new DiaporamaQuery();
        $this->configureI18nProcessing($query, ["TITLE", ]);

        if (null !== $id = $this->getId()) {
            $query->filterById($id);
        }

        if (null !== $title = $this->getTitle()) {
            $title = array_map("trim", explode(",", $title));
            $query->filterByTitle($title);
        }

        if (null !== $shortcode = $this->getShortcode()) {
            $shortcode = array_map("trim", explode(",", $shortcode));
            $query->filterByShortcode($shortcode);
        }

        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case "id":
                    $query->orderById();
                    break;
                case "id-reverse":
                    $query->orderById(Criteria::DESC);
                    break;
                case "title":
                    $query->addAscendingOrderByColumn("i18n_TITLE");
                    break;
                case "title-reverse":
                    $query->addDescendingOrderByColumn("i18n_TITLE");
                    break;
                case "shortcode":
                    $query->orderByShortcode();
                    break;
                case "shortcode-reverse":
                    $query->orderByShortcode(Criteria::DESC);
                    break;
            }
        }

        return $query;
    }

    protected function addMoreResults(LoopResultRow $row, $entryObject)
    {
    }
}
