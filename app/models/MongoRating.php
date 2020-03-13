<?php

// TODO: move to app/models ?
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

    private $rating = null;

    function __construct(MongoClient $mongo)
    {
        $this->mongo = $mongo;
    }

    public function save($data = [])
    {
        $this->rating = $this->mongo->setFormParam($data)
            ->setEndPoint('reviews')
            ->request('POST');

        return $this->rating;
    }

    public function update($id, $data = [])
    {
        $this->rating = $this->mongo->setFormParam($data)
            ->setEndPoint("reviews/{$id}")
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
        $this->rating = $this->mongo->setEndPoint("reviews/{$id}")
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
            ->setEndPoint('reviews')
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
     * Determine if current rating instance is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        if (! empty($this->rating) && isset($this->rating->data)) {
            $ratingData = $this->rating->data;

            if (! empty($ratingData) && $ratingData->returned_records > 0) {
                return false;
            }
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
