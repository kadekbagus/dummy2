<?php namespace OrbitShop\API\v1;
/**
 * Trait for common API controller.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use DB;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

trait CommonAPIControllerTrait
{
    /**
     * Begin the database transaction.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function beginTransaction()
    {
        if (! $this->useTransaction) {
            return;
        }

        DB::connection()->beginTransaction();
    }

    /**
     * Rollback the transaction.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function rollBack()
    {
        if (! $this->useTransaction) {
            return;
        }

        // Make sure we are in transaction mode, to prevent the rollback()
        // complaining
        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->rollBack();
        }
    }

    /**
     * Commit the changes to database.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function commit()
    {
        if (! $this->useTransaction) {
            return;
        }

        DB::connection()->commit();
    }

    /**
     * Set the transaction flag on controller.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param $use boolean
     * @return ControllerAPI
     */
    public function setUseTransaction($use=TRUE)
    {
        $this->useTransaction = $use;

        return $this;
    }

    /**
     * Make sure if we expect error the error code is not zero and set fallback
     * to StatusInterface::UNKNOWN_ERROR
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $code - The error code
     * @return int
     */
    public function getNonZeroCode($code)
    {
        if ((int)$code === 0) {
            return Status::UNKNOWN_ERROR;
        }

        return $code;
    }
}