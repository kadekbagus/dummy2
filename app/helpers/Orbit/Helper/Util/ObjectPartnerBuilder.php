<?php namespace Orbit\Helper\Util;
/**
 * get partner query builder
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 * @author Ahmad <ahmad@dominopos.com>
 */
use DB;
use PartnerAffectedGroup;
use \Config;

class ObjectPartnerBuilder
{
   public static function getQueryBuilder($query, $partner_id, $type)
   {
        $prefix = DB::getTablePrefix();
        $partner_affected = PartnerAffectedGroup::join('affected_group_names', function($q) use ($type) {
                                                            $q->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                              ->on('affected_group_names.group_type', '=', DB::raw("'{$type}'"));
                                                        })
                                                ->where('partner_id', $partner_id)
                                                ->first();

        switch ($type) {
            case 'promotion':
                if (is_object($partner_affected)) {
                    $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                    if (in_array($partner_id, $exception)) {
                        $query->leftJoin('object_partner', function($q) use ($partner_id) {
                                $q->on('object_partner.object_id', '=', 'news.news_id')
                                  ->where('object_partner.object_type', '=', 'promotion')
                                  ->where('object_partner.partner_id', '=', $partner_id);
                            })
                            ->whereNotExists(function($q) use ($partner_id, $prefix) {
                                $q->select('object_partner.object_id')
                                      ->from('object_partner')
                                      ->join('partner_competitor', function($q) {
                                            $q->on('partner_competitor.competitor_id', '=', 'object_partner.partner_id');
                                        })
                                      ->whereRaw("{$prefix}object_partner.object_type = 'promotion'")
                                      ->whereRaw("{$prefix}partner_competitor.partner_id = '{$partner_id}'")
                                      ->whereRaw("{$prefix}object_partner.object_id = {$prefix}news.news_id")
                                      ->groupBy('object_partner.object_id');
                            });
                    } else {
                        $query->join('object_partner', function($q) use ($partner_id) {
                                $q->on('object_partner.object_id', '=', 'news.news_id')
                                  ->where('object_partner.object_type', '=', 'promotion');
                            })
                            ->where('object_partner.partner_id', '=', $partner_id);
                    }
                }
                break;

            case 'news':
                if (is_object($partner_affected)) {
                    $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                    if (in_array($partner_id, $exception)) {
                        $query->leftJoin('object_partner',function($q) use ($partner_id){
                                $q->on('object_partner.object_id', '=', 'news.news_id')
                                  ->where('object_partner.object_type', '=', 'news')
                                  ->where('object_partner.partner_id', '=', $partner_id);
                            })
                            ->whereNotExists(function($q) use ($partner_id, $prefix)
                            {
                                $q->select('object_partner.object_id')
                                  ->from('object_partner')
                                  ->join('partner_competitor', function($q) {
                                        $q->on('partner_competitor.competitor_id', '=', 'object_partner.partner_id');
                                    })
                                  ->whereRaw("{$prefix}object_partner.object_type = 'news'")
                                  ->whereRaw("{$prefix}partner_competitor.partner_id = '{$partner_id}'")
                                  ->whereRaw("{$prefix}object_partner.object_id = {$prefix}news.news_id")
                                  ->groupBy('object_partner.object_id');
                            });
                    } else {
                        $query->join('object_partner',function($q) use ($partner_id){
                                $q->on('object_partner.object_id', '=', 'news.news_id')
                                  ->where('object_partner.object_type', '=', 'news');
                            })
                            ->where('object_partner.partner_id', '=', $partner_id);
                    }
                }
                break;

            case 'coupon':
                if (is_object($partner_affected)) {
                    $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                    if (in_array($partner_id, $exception)) {
                        $query->leftJoin('object_partner', function($q) use ($partner_id) {
                                $q->on('object_partner.object_id', '=', 'promotions.promotion_id')
                                  ->where('object_partner.object_type', '=', 'coupon')
                                  ->where('object_partner.partner_id', '=', $partner_id);
                            })
                            ->whereNotExists(function($q) use ($partner_id, $prefix) {
                                $q->select('object_partner.object_id')
                                  ->from('object_partner')
                                  ->join('partner_competitor', function($q) {
                                        $q->on('partner_competitor.competitor_id', '=', 'object_partner.partner_id');
                                    })
                                  ->whereRaw("{$prefix}object_partner.object_type = 'coupon'")
                                  ->whereRaw("{$prefix}partner_competitor.partner_id = '{$partner_id}'")
                                  ->whereRaw("{$prefix}object_partner.object_id = {$prefix}promotions.promotion_id")
                                  ->groupBy('object_partner.object_id');
                            });
                    } else {
                        $query->join('object_partner',function($q) use ($partner_id){
                                $q->on('object_partner.object_id', '=', 'promotions.promotion_id')
                                  ->where('object_partner.object_type', '=', 'coupon');
                            })
                            ->where('object_partner.partner_id', '=', $partner_id);
                    }
                }
                break;

            case 'tenant':
                if (is_object($partner_affected)) {
                    $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);

                    if (in_array($partner_id, $exception)) {
                        $query->leftJoin('object_partner', function($q) use ($partner_id) {
                                $q->on('object_partner.object_id', '=', 'merchants.merchant_id')
                                  ->where('object_partner.object_type', '=', 'tenant')
                                  ->where('object_partner.partner_id', '=', $partner_id);
                            })
                            ->whereNotExists(function($q) use ($partner_id, $prefix)
                            {
                                $q->select('object_partner.object_id')
                                  ->from('object_partner')
                                  ->join('partner_competitor', function($q) {
                                        $q->on('partner_competitor.competitor_id', '=', 'object_partner.partner_id');
                                    })
                                  ->whereRaw("{$prefix}object_partner.object_type = 'tenant'")
                                  ->whereRaw("{$prefix}partner_competitor.partner_id = '{$partner_id}'")
                                  ->whereRaw("{$prefix}object_partner.object_id = {$prefix}merchants.merchant_id")
                                  ->groupBy('object_partner.object_id');
                            });
                    } else {
                        $query->join('object_partner',function($q) use ($partner_id){
                                $q->on('object_partner.object_id', '=', 'merchants.merchant_id')
                                  ->where('object_partner.object_type', '=', 'tenant');
                            })
                            ->where('object_partner.partner_id', '=', $partner_id);
                    }
                }
                break;

        }
        return $query;
   }
}


