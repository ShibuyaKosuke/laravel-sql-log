<?php

namespace ShibuyaKosuke\LaravelSqlLog\Providers;

use Arcanedev\Support\Providers\ServiceProvider;
use Carbon\Carbon;
use DateTime;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Class DataBaseQueryServiceProvider
 * @package ShibuyaKosuke\LaravelSqlLog\Providers
 */
class DataBaseQueryServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sqllog.php', 'sql_log');

        /** @var Repository $config */
        $config = $this->app['config'];

        if (!$config->get('sql_log.sql.enable')) {
            return;
        }

        DB::listen(function ($query): void {

            $sql = $query->sql;

            foreach ($query->bindings as $binding) {
                if (is_string($binding)) {
                    $binding = "'{$binding}'";
                } elseif (is_bool($binding)) {
                    $binding = $binding ? '1' : '0';
                } elseif (is_int($binding)) {
                    $binding = (string)$binding;
                } elseif ($binding === null) {
                    $binding = 'NULL';
                } elseif ($binding instanceof Carbon) {
                    $binding = "'{$binding->toDateTimeString()}'";
                } elseif ($binding instanceof DateTime) {
                    $binding = "'{$binding->format('Y-m-d H:i:s')}'";
                }

                $sql = preg_replace('/\\?/', $binding, $sql, 1);
            }

            Log::debug('SQL:', ['sql' => $sql, 'time' => "{$query->time} ms"]);
        });

        Event::listen(TransactionBeginning::class, function (TransactionBeginning $event): void {
            Log::debug('SQL:START TRANSACTION');
        });

        Event::listen(TransactionCommitted::class, function (TransactionCommitted $event): void {
            Log::debug('SQL:COMMIT');
        });

        Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event): void {
            Log::debug('SQL:ROLLBACK');
        });
    }
}
