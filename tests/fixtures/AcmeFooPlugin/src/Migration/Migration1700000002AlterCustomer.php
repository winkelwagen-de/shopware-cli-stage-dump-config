<?php declare(strict_types=1);

namespace Acme\FooPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000002AlterCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `customer` ADD COLUMN `acme_foo_loyalty_notes` LONGTEXT NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
