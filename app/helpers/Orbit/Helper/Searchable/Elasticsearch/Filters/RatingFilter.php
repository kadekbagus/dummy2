<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait RatingFilter
{
    private $mallCountryList = null;

    public function filterByRating($ratingLow, $ratingHigh, $params)
    {
        $rateLow = (double) $ratingLow;
        //TODO: +0.01 is hack because they way rating is added in update queue
        //for example ESNewsUpdateQueue.php (line 189)
        //need to fix them.
        $rateHigh = (double) $ratingHigh + 0.001;

        $this->filterByScript(
             $this->getRatingFilterScript($params),
             compact('rateLow', 'rateHigh')
        );
    }

    /**
     * Get mall country list.
     *
     * @return [type] [description]
     */
    protected function getMallCountryList()
    {
        //TODO : cache this call database call as we may need to call it several times
        if (empty($this->mallCountryList)) {
            $mallCountry = Mall::groupBy('country')->lists('country');
            $countries = Country::select('country_id')->whereIn('name', $mallCountry)->get();
            $this->mallCountryList = $countries;
        } else {
            $countries = $this->mallCountryList;
        }
        return $countries;
    }

    /**
     * Build rating review calculation script.
     *
     * @param  [type] $countryId   [description]
     * @param  [type] $cityFilters [description]
     * @return [type]              [description]
     */
    protected function buildRatingReviewCalcScriptByCountryCities($countryId, $cityFilters)
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        // count total review and average rating based on city filter
        foreach ($cityFilters as $cityFilter) {
            $cities = str_replace(" ", "_", trim(strtolower($cityFilter), " "));
            $ratingKey = "location_rating.rating_{$countryId}_{$cities}";
            $reviewKey = "location_rating.review_{$countryId}_{$cities}";
            $scriptFieldRating = $scriptFieldRating . ' ' .
            "if (doc.containsKey('{$ratingKey}')) {
                if (! doc['{$ratingKey}'].empty) {
                    counter = counter + doc['{$reviewKey}'].value;
                    rating = rating + (doc['{$ratingKey}'].value * doc['{$reviewKey}'].value);
                }
            }; ";
            $scriptFieldReview = $scriptFieldReview . ' ' .
            "if (doc.containsKey('{$reviewKey}')) {
                if (! doc['{$reviewKey}'].empty) {
                    review = review + doc['{$reviewKey}'].value;
                }
            }; ";
        }
        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    /**
     * Build rating review calculation script by mall id.
     *
     * @param  [type] $mallId [description]
     * @return [type]         [description]
     */
    protected function buildRatingReviewCalcScriptByMallId($mallId)
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        $ratingKey = "mall_rating.rating_{$mallId}";
        $reviewKey = "mall_rating.review_{$mallId}";
        $scriptFieldRating = $scriptFieldRating . " " .
        "if (doc.containsKey('{$ratingKey}')) {
            if (! doc['{$ratingKey}'].empty) {
                counter = counter + doc['{$reviewKey}'].value;
                rating = rating + (doc['{$ratingKey}'].value * doc['{$reviewKey}'].value);
            }
        };";
        $scriptFieldReview = $scriptFieldReview . " " .
        "if (doc.containsKey('{$reviewKey}')) {
            if (! doc['{$reviewKey}'].empty) {
                review = review + doc['{$reviewKey}'].value;
            }
        };";
        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    /**
     * Build rating review calculation script by countries.
     *
     * @param  [type] $countryIds [description]
     * @return [type]             [description]
     */
    protected function buildRatingReviewCalcScriptByCountries($countryIds)
    {
        $scriptFieldRating = "double counter = 0; double rating = 0;";
        $scriptFieldReview = "double review = 0;";
        foreach ($countryIds as $countryId) {
            // count total review and average rating based on country filter
            $ratingKey = "location_rating.rating_{$countryId}";
            $reviewKey = "location_rating.review_{$countryId}";
            $scriptFieldRating = $scriptFieldRating . ' ' .
            "if (doc.containsKey('{$ratingKey}')) {
                if (! doc['{$ratingKey}'].empty) {
                    counter = counter + doc['{$reviewKey}'].value;
                    rating = rating + (doc['{$ratingKey}'].value * doc['{$reviewKey}'].value);
                }
            }; ";
            $scriptFieldReview = $scriptFieldReview . ' ' .
            "if (doc.containsKey('{$reviewKey}')) {
                if (! doc['{$reviewKey}'].empty) {
                    review = review + doc['{$reviewKey}'].value;
                }
            }; ";
        }

        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    protected function buildRatingReviewCalcScriptByMallCountries()
    {
        $countries = $this->getMallCountryList();
        $countryIds = [];
        foreach ($countries as $country) {
            $countryIds[] = $country->country_id;
        }
        return $this->buildRatingReviewCalcScriptByCountries($countryIds);
    }

    protected function buildRatingReviewCalcScript($params = [])
    {
        if (! empty($params['mallId'])) {
            return $this->buildRatingReviewCalcScriptByMallId($params['mallId']);
        } else if (! empty($params['countryData']) && ! empty($params['cityFilters'])) {
            $countryId = $params['countryData']->country_id;
            return $this->buildRatingReviewCalcScriptByCountryCities($countryId, $params['cityFilters']);
        } else if (! empty($params['countryData']) && empty($params['cityFilter'])) {
            $countryId = $params['countryData']->country_id;
            return $this->buildRatingReviewCalcScriptByCountries([$countryId]);
        } else {
            return $this->buildRatingReviewCalcScriptByMallCountries();
        }
    }

    protected function getReviewRatingScript($params = [])
    {
        $scripts = $this->buildRatingReviewCalcScript($params);
        $scriptFieldRating = $scripts['scriptFieldRating'] . " " .
        "if (counter == 0 || rating == 0) {
            return 0;
        } else {
            return rating/counter;
        }; ";
        $scriptFieldReview = $scripts['scriptFieldReview'] . " " .
        "if (review == 0) {
            return 0;
        } else {
            return review;
        }; ";

        return compact('scriptFieldRating', 'scriptFieldReview');
    }

    protected function getRatingFilterScript($params = [])
    {
        $scripts = $this->buildRatingReviewCalcScript($params);
        return $scripts['scriptFieldRating'] . " " .
        "return (counter == 0 && rateLow == 0) || ".
        "((counter>0) && (rating/counter >= rateLow) && (rating/counter <= rateHigh));";
    }

    protected function buildRatingParamsFromRequest()
    {
        $params = [
            'mallId' => null,
            'countryData' => null,
            'cityFilters' => null,
        ];

        $this->request->has('mall_id', function($mallId) use ($params) {
            $params['mallId'] = $mallId;
        });

        $this->request->has('country', function($country) use ($params) {
            $params['countryData'] = Country::select('country_id')
                ->where('name', $country)->first();
        });

        return $params;
    }

    public function addReviewFollowScript($params = [])
    {
        $params = $this->buildRatingParamsFromRequest();
        $scripts = $this->getReviewRatingScript($params);
        // Add script fields into request body...
        $this->scriptFields([
            'average_rating' => $scripts['scriptFieldRating'],
            'total_review' => $scripts['scriptFieldReview'],
        ]);

        return $scripts;
    }
}
