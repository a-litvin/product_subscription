<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

use Doctrine\DBAL\Connection;

abstract class AbstractReadModel
{
    /**
     * @var string
     */
    protected $databasePrefix;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     * @param string $databasePrefix
     */
    public function __construct(Connection $connection, string $databasePrefix)
    {
        $this->connection = $connection;
        $this->databasePrefix = $databasePrefix;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
