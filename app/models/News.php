<?php
class News extends Eloquent
{
    /**
     * News Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'news';

    protected $primaryKey = 'news_id';

    public function mall()
    {
        return $this->belongsTo('Retailer', 'mall_id', 'merchant_id')->isMall();
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function tenants()
    {
        return $this->belongsToMany('Retailer', 'news_merchant', 'news_id', 'merchant_id');
    }

    /**
     * News has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'news_id')
                    ->where('object_name', 'news');
    }

}
