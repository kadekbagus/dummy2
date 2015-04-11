<?php namespace DominoPOS\OrbitSession\Driver;
/**
 * Generic interface which all session driver should implements.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
interface GenericInterface
{
    /**
     * Start a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     */
    public function start($sessionData);

    /**
     * Update a session
     *
     * @param DominoPOS\OrbitSession\SessionData
     */
    public function update($sessionData);

    /**
     * Destroy a session
     */
    public function destroy($sessionId);

    /**
     * Clear a session
     */
    public function clear($sessionId);

    /**
     * Get a session
     */
    public function get($sessionId);

    /**
     * Write a value to a session.
     */
    public function write($sessionId, $key, $value);

    /**
     * Read a value from a session
     */
    public function read($sessionId, $key);

    /**
     * Remove a value from a session
     */
    public function remove($sessionId, $key);

    /**
     * Delete expire session
     */
    public function deleteExpires();
}
