<?php declare(strict_types=1);

namespace AcmeFooPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use AcmeFooPlugin\Entity\FooRunDefinition;

class Migration1700000003PlaceholderAlter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000003;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            ALTER TABLE `#table#`
                ADD `status` VARCHAR(255) NOT NULL,
                ADD `message_count` INT DEFAULT 0 NOT NULL;
SQL;

        $connection->executeStatement(\str_replace(
            ['#table#'],
            [FooRunDefinition::ENTITY_NAME],
            $sql
        ));
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
