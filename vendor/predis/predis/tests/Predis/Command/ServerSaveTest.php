<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Command;

/**
 * @group commands
 * @group realm-server
 */
class ServerSaveTest extends PredisCommandTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getExpectedCommand()
    {
        return 'Predis\Command\ServerSave';
    }

    /**
     * {@inheritdoc}
     */
    protected function getExpectedId()
    {
        return 'SAVE';
    }

    /**
     * @group disconnected
     */
    public function testFilterArguments()
    {
        $command = $this->getCommand();
        $command->setArguments(array());

        $this->assertSame(array(), $command->getArguments());
    }

    /**
     * @group disconnected
     */
    public function testParseResponse()
    {
        $this->assertTrue($this->getCommand()->parseResponse(true));
    }
}
