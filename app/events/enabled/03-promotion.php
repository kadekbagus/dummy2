<?php
/**
 * Event listener for Promotion related events.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.promotion.postnewpromotion.after.save`
 * Purpose:      Handle file upload on promotion creation
 *
 * @author Tian <tian@dominopos.com>
 *
 * @param PromotionAPIController $controller - The instance of the PromotionAPIController or its subclass
 * @param Promotion $promotion - Instance of object Promotion
 */
Event::listen('orbit.promotion.postnewpromotion.after.save', function($controller, $promotion)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['promotion_id'] = $promotion->promotion_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('promotion.new')
                                   ->postUploadPromotionImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['promotion_id']);

    $promotion->setRelation('media', $response->data);
    $promotion->media = $response->data;
    $promotion->image = $response->data[0]->path;
});

/**
 * Listen on:    `orbit.promotion.postupdatepromotion.after.save`
 * Purpose:      Handle file upload on promotion update
 *
 * @author Tian <tian@dominopos.com>
 *
 * @param PromotionAPIController $controller - The instance of the PromotionAPIController or its subclass
 * @param Promotion $promotion - Instance of object Promotion
 */
Event::listen('orbit.promotion.postupdatepromotion.after.save', function($controller, $promotion)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('promotion.update')
                                   ->postUploadPromotionImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $promotion->load('media');
    $promotion->image = $response->data[0]->path;
});
