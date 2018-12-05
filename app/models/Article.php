<?php
class Article extends Eloquent
{
    /**
     * Article Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    protected $table = 'articles';

    protected $primaryKey = 'article_id';


    /*
        Link to Malls [[malls]]
        Link to Brands (Merchant) [[brands]]

        Link to Events [[events]]
        Link to Promotions [[promotions]]
        Link to Coupons [[coupons]]

        Article Body Images
        Article Body Videos (YouTube)
    */

    public function objectNews()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'news')
                    ->join('news', function($q) {
                                $q->on('news_id', '=', 'object_id')
                                    ->where('news.object_type', '=', 'news');
                            });
    }
    public function objectPromotion()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'promotion')
                    ->join('news', function($q) {
                                $q->on('news_id', '=', 'object_id')
                                    ->where('news.object_type', '=', 'promotion');
                            });
    }
    public function objectCoupon()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'coupon')
                    ->join('promotions', function($q) {
                                $q->on('promotion_id', '=', 'object_id');
                            });
    }
    public function objectMall()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'mall')
                    ->join('merchants', function($q) {
                                $q->on('merchant_id', '=', 'object_id');
                            });
    }
    public function objectStore()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'store')
                    ->join('merchants', function($q) {
                                $q->on('merchant_id', '=', 'object_id');
                            });
    }



}
