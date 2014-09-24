<?php

namespace WL\AppBundle\Controller;

use Nokaut\ApiKit\ClientApi\Rest\Async\OffersAsyncFetch;
use Nokaut\ApiKit\ClientApi\Rest\Async\ProductsAsyncFetch;
use Nokaut\ApiKit\ClientApi\Rest\Query\Filter\Single;
use Nokaut\ApiKit\ClientApi\Rest\Query\OffersQuery;
use Nokaut\ApiKit\ClientApi\Rest\Query\ProductsQuery;
use Nokaut\ApiKit\Entity\Offer;
use Nokaut\ApiKit\Repository\OffersAsyncRepository;
use Nokaut\ApiKit\Repository\OffersRepository;
use Nokaut\ApiKit\Repository\ProductsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use WL\AppBundle\Lib\Helper\ClickUrl;
use WL\AppBundle\Lib\Repository\ProductsAsyncRepository;

class ClickController extends Controller
{
    protected static $limitOffers = 30;

    public function clickAction(Request $request)
    {
        $offer = $this->fetchOffer($request->get('offerId'));

        $clickMode = $this->container->getParameter('click_mode');
        $offers = $products = null;
        if ($clickMode == ClickUrl::FRAME_OFFERS_SHOP) {
            $offers = $this->fetchOfferFromShop($offer);
        } else {
            $products = $this->fetchProductsFromCategory($offer);
        }

        /** @var ProductsAsyncRepository $productsRepository */
        $productsRepository = $this->get('repo.products.async');
        $productsRepository->fetchAllAsync();


        $iframeUrl = 'http://www.nokaut.pl' . $offer->getClickUrl();
        return $this->render('WLAppBundle:Click:click.html.twig', array(
            'iframeUrl' => $iframeUrl,
            'products' => $products ? $products->getResult() : null,
            'offers' => $offers ? $offers->getResult() : null,
            'offer' => $offer
        ));
    }

    /**
     * @param $id
     * @return Offer
     */
    protected function fetchOffer($id)
    {
        /** @var OffersRepository $offersRepository */
        $offersRepository = $this->get('repo.offers');
        return $offersRepository->fetchOfferById($id, OffersRepository::$fieldsAll);
    }

    /**
     * @param Offer $offer
     * @return ProductsAsyncFetch
     */
    protected function fetchProductsFromCategory($offer)
    {

        /** @var ProductsAsyncRepository $productsRepository */
        $productsRepository = $this->get('repo.products.async');

        $query = new ProductsQuery($this->container->getParameter('api_url'));
        $query->setFields(ProductsRepository::$fieldsWithBestOfferForProductBox);
        $query->setCategoryIds(array($offer->getCategoryId()));
        $query->setLimit(self::$limitOffers);
        return $productsRepository->fetchProductsWithBestOfferByQuery($query);
    }

    /**
     * @param Offer $offer
     * @return OffersAsyncFetch
     */
    protected function fetchOfferFromShop($offer)
    {
        /** @var OffersAsyncRepository $offersRepository */
        $offersRepository = $this->get('repo.offers.async');

        $query = new OffersQuery($this->container->getParameter('api_url'));
        $query->setFields(OffersRepository::$fieldsAll);
        $query->addFilter(new Single('shop_id', $offer->getShopId()));
        $query->addFilter(new Single('category_id', $offer->getCategoryId()));
        $query->setLimit(self::$limitOffers);

        return $offersRepository->fetchOffersByQuery($query);
    }

}
