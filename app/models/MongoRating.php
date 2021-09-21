<?php

use Orbit\Controller\API\v1\Rating\RatingModelInterface;
use Orbit\Helper\MongoDB\Client as MongoClient;

/**
 * Mongo-based rating model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class MongoRating implements RatingModelInterface
{
    private $mongo = null;

    private $endPoint = 'reviews';

    private $rating = null;

    function __construct(MongoClient $mongo)
    {
        $this->mongo = $mongo;
    }

    public function save($data = [])
    {
        $this->rating = $this->mongo->setFormParam($data)
            ->setEndPoint($this->endPoint)
            ->request('POST');

        return $this->rating;
    }

    public function update($id, $data = [])
    {
        $this->rating = $this->mongo->setFormParam($data)
            ->setEndPoint($this->endPoint)
            ->request('PUT');

        return $this->rating;
    }

    /**
     * Return self instance, because this method can add chain method.
     *
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public function find($id)
    {
        $this->rating = $this->mongo->setEndPoint("{$this->endPoint}/{$id}")
            ->request('GET');

        return $this;
    }

    /**
     * Return self instance, because this method can add chain method.
     *
     * @param  array  $searchQuery [description]
     * @return [type]              [description]
     */
    public function findByQuery($searchQuery = [])
    {
        $this->rating = $this->mongo->setQueryString($searchQuery)
            ->setEndPoint($this->endPoint)
            ->request('GET');

        return $this;
    }

    /**
     * Determine if given rating (by $ratingId) is exists on storage or not.
     *
     * @param  [type] $ratingId [description]
     * @return [type]           [description]
     */
    public function exists($ratingId)
    {
        return $this->find($ratingId)->isNotEmpty();
    }

    /**
     * Get rating images...
     * @return array $images array of rating images.
     */
    public function getImages()
    {
        $images = [];

        if ($this->isEmpty()) {
            return $images;
        }

        if (isset($this->rating->data->images)) {
            $ratingImages = $this->rating->data->images;

            // Loop thru number of image...
            foreach($ratingImages as $key => $imageList) {

                // For each image, loop thru its variants...
                foreach($imageList as $keyVar => $image) {
                    $images[$key][$keyVar] = [
                        'media_id' => $image->media_id,
                        'variant_name' => $image->variant_name,
                        'url' => $image->url,
                        'cdn_url' => $image->cdn_url,
                        'metadata' => $image->metadata,
                        'approval_status' => $image->approval_status,
                        'rejection_message' => $image->rejection_message,
                    ];
                }
            }
        }

        return $images;
    }

    /**
     * Determine if current rating instance is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        if (! empty($this->rating) && isset($this->rating->data)) {
            $ratingData = $this->rating->data;

            if (! empty($ratingData) && isset($ratingData->returned_records)) {

                if ($ratingData->returned_records > 0) {
                    return false;
                }
                else {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    public function isNotEmpty()
    {
        return ! $this->isEmpty();
    }

    public function getRating()
    {
        return $this->rating;
    }

    public function __invoke()
    {
        return $this->getRating();
    }
}
