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
        Link to Promotions [[promotions]]
        Link to Coupons [[coupons]]
        Link to Events [[events]]
        Article Body Images
        Article Body Videos (YouTube)
    */



}
