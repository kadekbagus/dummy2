<?php

/**
 * Digital Product Model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProduct extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'digital_products';

    protected $primaryKey = 'digital_product_id';

    /**
     * Filter that make sure only select displayed digital product.
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeDisplayed($query)
    {
        return $query->where('is_displayed', 'yes');
    }

    /**
     * Filter/scope that make sure Digital Product is available.
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeAvailable($query)
    {
        return $query->active()->where('is_displayed', 'yes');
    }

    /**
     * Filter that Digital Product is in Promo.
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function scopeIsPromo($query)
    {
        return $query->where('is_promo', 'yes');
    }

    /**
     * Many to many relationship with Game.
     * @return [type] [description]
     */
    public function games()
    {
        return $this->belongsToMany(Game::class, 'digital_product_game')->withTimestamps();
    }

    /**
     * Digital Product - Provider Product relation.
     * @return [type] [description]
     */
    public function provider_product()
    {
        return $this->hasOne(ProviderProduct::class, 'provider_product_id', 'selected_provider_product_id');
    }
}
