<?php

namespace OrisIntel\OnlineMigrator\Tests;

class InnodbOnlineDdlTest extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();

        // CONSIDER: Using \DB::listen instead.
        \DB::enableQueryLog();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('online-migrator.strategy', 'InnodbOnlineDdl');
    }

    public function test_migrate_addsColumn()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-column');

        $this->assertEquals(
            'alter table `test_om` add `color` varchar(255) null, ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            // CONSIDER: Fixing implementation to avoid dupes in query log.
            \DB::getQueryLog()[2]['query']);

        $test_row_one = \DB::table('test_om')->where('name', 'one')->first();
        $this->assertNotNull($test_row_one);
        $this->assertEquals('green', $test_row_one->color);
    }

    public function test_migrate_addsUnique()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-unique');

        $this->assertEquals(
            'alter table `test_om` add unique `test_om_name_unique`(`name`), ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[1]['query']);

        $this->expectException(\PDOException::class);
        $this->expectExceptionCode(23000);
        \DB::table('test_om')->insert(['name' => 'one']);
    }

    public function test_migrate_addsWithoutDefault()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-without-default');

        $this->assertEquals(
            'alter table `test_om` add `without_default` varchar(255) not null, ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[2]['query']);

        $this->assertEquals('column added', \DB::table('test_om')->first()->without_default ?? null);
    }

    public function test_migrate_createsFkWithIndex()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-fk-with-index');

        $show_create_sql = str_replace('`', '',
            array_last(
                \DB::select('show create table test_om_fk_with_index')
            )->{"Create Table"}
        );

        preg_match_all('~^\s+KEY\s+([^\s]+)~mu', $show_create_sql, $m);
        // Unlike PTOSC, InnoDB's Online DDL should not create redundant indexes.
        $this->assertEquals([
            'test_om_fk_with_index_test_om_id_index',
        ], $m[1]);
    }

    public function test_migrate_createsTableWithPrimary()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-table-with-primary');

        $this->expectException(\PDOException::class);
        $this->expectExceptionCode(23000);
        \DB::table('test_om_with_primary')->insert(['name' => 'alice']);
    }
}
