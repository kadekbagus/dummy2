<?php
class Article extends Eloquent
{
    /**
     * Article Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    use ModelStatusTrait;

    const NOT_FOUND_ERROR_CODE = 4040;

    protected $table = 'articles';

    protected $primaryKey = 'article_id';

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

    public function objectProduct()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'product')
                    ->join('products', function($q) {
                                $q->on('product_id', '=', 'object_id');
                            });
    }

    public function objectPartner()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'partner')
                    ->join('partners', function($q) {
                                $q->on('partner_id', '=', 'object_id');
                            });
    }

    public function objectArticle()
    {
        return $this->belongsToMany('Article', 'article_link_to_objects', 'article_id', 'object_id');
    }

    public function objectMerchant()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'merchant')
                    ->join('merchants', function($q) {
                                $q->on('merchant_id', '=', 'object_id');
                            });
    }

    public function category()
    {
        return $this->hasMany('ArticleLinkToObject', 'article_id', 'article_id')
                    ->where('article_link_to_objects.object_type', 'category')
                    ->join('categories', function($q) {
                                $q->on('category_id', '=', 'object_id');
                            });
    }

    public function mediaCover()
    {
        return $this->hasMany('Media', 'object_id', 'article_id')
                    ->where('media_name_id', 'article_cover_image')
                    ->orderBy('media.created_at', 'desc');
    }

    public function mediaContent()
    {
        return $this->hasMany('Media', 'object_id', 'article_id')
                    ->where('media_name_id', 'article_content_image');
    }

    public function video()
    {
        return $this->hasMany('ArticleVideo', 'article_id', 'article_id');
    }

    public function cities()
    {
        return $this->hasMany('ArticleCity', 'article_id', 'article_id')
                    ->join('mall_cities', 'mall_cities.mall_city_id', '=', 'article_cities.mall_city_id');
    }


}
