<?php

namespace Zhengwhizz\DBDic\Helper;

use Illuminate\Support\Facades\DB;

class TableHelper
{

    public static function getTableInfos()
    {
        switch (config('database.default')) {
            case "pgsql":
                $tables = DB::select("select relname as \"Name\",cast(obj_description(relfilenode,'pg_class') as varchar) as \"Comment\" from pg_class c where relname in (select tablename from pg_tables where schemaname='public' and position('_2' in tablename)=0);");
                foreach ($tables as $key => $table) {
                    $columns = DB::select('select
                    tmp.attname as "Field",
                    typname as "Type",
                    d.description as "Comment",
                    case when i.indisprimary then \'PRI\' end as "Key",

                case when tmp.attnotnull then \'NO\' else \'YES\'
                end  as "Null",
                    \'\' "Extra",
                    da.adsrc "Default"
                from
                    (
                        select a.attname,
                        t.typname,
                        a.atttypid,
                        a.atttypmod,
                        a.attnum,
                        a.attrelid,
                        a.attnotnull,
                        c.oid
                    from
                        pg_attribute a,
                        pg_class c,
                        pg_type t
                    where
                        c.relname = \'' . $table->Name . '\'
                        and a.attnum>0
                        and a.attrelid = c.oid
                        and a.atttypid = t.oid) as tmp
                left join pg_description d on
                    d.objoid = tmp.attrelid
                    and d.objsubid = tmp.attnum
                left join pg_attrdef da on
                    da.adnum = tmp.attnum
                    and da.adrelid = tmp.oid
                left join pg_index i on i.indisprimary and i.indrelid=\'' . $table->Name . '\'::regclass and tmp.attrelid = i.indrelid and tmp.attnum = ANY(i.indkey)

                order by tmp.attnum');
                    $table->columns = $columns;
                    $tables[$key] = $table;
                }
                return $tables;
                break;
            case "mysql":
                $tables = DB::select('SHOW TABLE STATUS ');
                foreach ($tables as $key => $table) {
                    //获取改表的所有字段信息
                    $columns = DB::select("SHOW FULL FIELDS FROM `" . $table->Name . "`");
                    $table->columns = $columns;
                    $tables[$key] = $table;
                }
                return $tables;
                break;
            default:
                return [];
        }

    }

    public static function comment($table, $comment)
    {
        switch (config('database.default')) {
            case "mysql":
                DB::statement("ALTER TABLE $table comment '$comment'");
                break;
            case "pgsql":
                DB::statement("comment on table $table is '$comment'");
                break;
            default:
                break;
        }
    }

}
