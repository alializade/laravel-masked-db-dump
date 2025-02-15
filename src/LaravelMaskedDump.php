<?php

namespace AliAlizade\LaravelMaskedDumper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Illuminate\Console\OutputStyle;
use AliAlizade\LaravelMaskedDumper\TableDefinitions\TableDefinition;

class LaravelMaskedDump
{
    /** @var DumpSchema */
    protected $definition;

    /** @var OutputStyle */
    protected $output;

    protected $isFirstUser = false;

    public function __construct(DumpSchema $definition, OutputStyle $output)
    {
        $this->definition = $definition;
        $this->output = $output;
    }

    public function dump(): string
    {
        $tables = $this->definition->getDumpTables();

        $query = '';

        $overallTableProgress = $this->output->createProgressBar(count($tables));

        $query .= $this->disableStrictChecks($query);

        foreach ($tables as $tableName => $table) {
            if (!$table->shouldDumpOnlyData()) {
                $query .= "DROP TABLE IF EXISTS `$tableName`;" . PHP_EOL;
                $query .= $this->dumpSchema($table);
            }

            if ($table->shouldDumpData()) {
                //                $query .= $this->lockTable($tableName);

                $query .= $this->dumpTableData(
                    table: $table,
                    dataOnly: $table->shouldDumpOnlyData()
                );

                //                $query .= $this->unlockTable($tableName);
            }

            $overallTableProgress->advance();
        }

        $query .= $this->enableStrictChecks($query);


        return $query;
    }

    protected function transformResultForInsert($row, TableDefinition $table, string $tableName)
    {
        /** @var Connection $connection */
        $connection = $this->definition->getConnection()->getDoctrineConnection();

        return collect($row)->map(function ($value, $column) use ($connection, $table, $tableName) {
            if ($columnDefinition = $table->findColumn($column)) {
                $value = $columnDefinition->modifyValue($value);
            }

            if($tableName === 'users' && $column === 'id' && $value === 1) {
                $this->isFirstUser = true;
            }

            if($tableName === 'users' && $column === 'email' && $this->isFirstUser) {
                $value = 'demo@fake.com';
                $this->isFirstUser = false;
            }

            if ($value === null) {
                return 'NULL';
            }
            if ($value === '') {
                return '""';
            }

            return $connection->quote($value);
        })->toArray();
    }

    protected function dumpSchema(TableDefinition $table)
    {
        $platform = $this->definition->getConnection()->getDoctrineSchemaManager()->getDatabasePlatform();

        $schema = new Schema([$table->getDoctrineTable()]);

        return implode(";", $schema->toSql($platform)) . ";" . PHP_EOL;
    }

    protected function disableStrictChecks(string $query): string
    {
        return "SET unique_checks=0; " . PHP_EOL .
            "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL;
    }

    protected function enableStrictChecks(string $query): string
    {
        return "SET unique_checks=1; " . PHP_EOL .
            "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL;
    }

    protected function lockTable(string $tableName): string
    {
        return PHP_EOL . "LOCK TABLES `$tableName` WRITE;" . PHP_EOL .
            "ALTER TABLE `$tableName` DISABLE KEYS;" . PHP_EOL;
    }

    protected function unlockTable(string $tableName): string
    {
        return PHP_EOL . "ALTER TABLE `$tableName` ENABLE KEYS;" . PHP_EOL .
            "UNLOCK TABLES;" . PHP_EOL;
    }

    protected function dumpTableData(TableDefinition $table, bool $dataOnly = false): string
    {
        $query = '';

        $queryBuilder = $this->definition->getConnection()
            ->table($table->getDoctrineTable()->getName());

        $table->modifyQuery($queryBuilder);

        $tableName = $table->getDoctrineTable()->getName();
        $columns = $this->definition->getConnection()->getSchemaBuilder()->getColumnListing($tableName);

        $total_count = $queryBuilder->count();

        if($total_count == 0) return '';

        $query .= $dataOnly ? "INSERT IGNORE INTO " : "INSERT INTO ";
        $query .= "`${tableName}` (`" . implode('`, `', $columns) . '`) VALUES ';

        $index = 0;

        foreach ($queryBuilder->cursor() as $row) {
            $row = $this->transformResultForInsert((array)$row, $table, $tableName);

            $query .= "(";

            $firstColumn = true;
            foreach ($row as $value) {
                if (!$firstColumn) {
                    $query .= ", ";
                }
                $query .= $value;
                $firstColumn = false;
            }

            $index++;

            $query .= ($total_count == $index ? ");" : "),") . PHP_EOL;
        }

        return $query;
    }
}
