<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112145003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure product image_filename column exists';
    }

    public function up(Schema $schema): void
    {
        // Add the column only if it does not exist.
        $this->addSql(
            "SET @column_exists = (
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'product'
                  AND COLUMN_NAME = 'image_filename'
            )"
        );

        $this->addSql(
            "SET @sql = IF(
                @column_exists = 0,
                'ALTER TABLE product ADD image_filename VARCHAR(255) DEFAULT NULL',
                'SELECT 1'
            )"
        );

        $this->addSql('PREPARE stmt FROM @sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');
    }

    public function down(Schema $schema): void
    {
        // Intentionally leave the existing column intact
        // to avoid deleting stored image filenames.
    }
}
