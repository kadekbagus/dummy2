<?php

/**
 * Simple model interface.
 *
 * @author Budi <budi@gotomalls.com>
 */
interface ModelInterface
{
    public function save($data = []);

    public function update($id, $data = []);

    public function find($id);
}
