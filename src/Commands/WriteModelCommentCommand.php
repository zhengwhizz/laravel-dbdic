<?php

namespace Zhengwhizz\DBDic\Commands;

use Barryvdh\Reflection\DocBlock;
use Barryvdh\Reflection\DocBlock\Context;
use Barryvdh\Reflection\DocBlock\Serializer as DocBlockSerializer;
use Barryvdh\Reflection\DocBlock\Tag;
use Composer\Autoload\ClassMapGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;

class WriteModelCommentCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oa:schema {modelArg?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据模型名从数据库查出备注生成 OpenApi Schema. \n usage: oa:schema App/Models/User';

    /**
     *
     *
     */
    protected $dirs;
    protected $files;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $modelArg = $this->argument('modelArg');
        $output = "<?php\r\n";
        $this->dirs = ['app'];

        if (empty($modelArg)) {
            $models = $this->loadModels();
        } else {
            $models = [str_replace('/', '\\', $modelArg)];
        }

        foreach ($models as $name) {
            if (class_exists($name)) {

                try {
                    // handle abstract classes, interfaces, ...
                    $reflectionClass = new ReflectionClass($name);

                    if (!$reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                        continue;
                    }

                    if (!$reflectionClass->IsInstantiable()) {
                        // ignore abstract class or interface
                        continue;
                    }

                    $model = $this->laravel->make($name);

                    $output .= $this->createPhpDocs($model);

                } catch (\Exception $e) {
                    $this->error("Exception: " . $e->getMessage() . "\nCould not analyze class $name.");
                }
            }
        }
        if (Storage::put("models.php", $output)) {
            $this->info('Written new phpDocBlock to ' . "models.php");
        }

    }

    protected function loadModels()
    {
        $models = array();
        foreach ($this->dirs as $dir) {
            $dir = base_path() . '/' . $dir;
            if (file_exists($dir)) {
                foreach (ClassMapGenerator::createMap($dir) as $model => $path) {
                    $models[] = $model;
                }
            }
        }
        return $models;
    }

    protected function createPhpDocs($class)
    {

        $tableName = $class->getTable();
        $table = DB::select(
            "select
                relname as \"Name\",
                cast(obj_description(relfilenode,'pg_class') as varchar) as \"Comment\"
            from pg_class
            where relname = '$tableName' limit 1"
        );
        if (count($table) == 0) {
            $this->warn($tableName . '未从数据库中检出');
            return '';
        } else {
            $table = $table[0];
        }

        $info = $this->getTableInfos($class->getTable());
        $reflection = new ReflectionClass($class);
        $namespace = $reflection->getNamespaceName();
        $classShortName = $reflection->getShortName();
        $originalDoc = $reflection->getDocComment();
        $className = $reflection->getName();

        $phpdoc = new DocBlock('', new Context($namespace));
        $desc = "";
        $required = "";

        foreach ($info as $val) {
            $desc = $desc . "@OA\Property( property=\"$val->Field\", type=\"$val->Type\", description=\"$val->Comment\" )\r\n";
            if ($val->Null) {
                $required = $required . '"' . $val->Field . '",';
            }
        }
        $required = mb_substr($required, 0, mb_strlen($required) - 1);
        $schema = "@OA\Schema(\r\n\tschema=\"$className\",\r\n\trequired={$required},\r\n\tdescription=\"$table->Comment\"\r\n)";

        $phpdoc->appendTag(Tag::createInstance($schema . "\r\n"));
        $phpdoc->appendTag(Tag::createInstance($desc . "\r\n"));

        $serializer = new DocBlockSerializer();
        $docComment = $serializer->getDocComment($phpdoc);
        $filename = $reflection->getFileName();
        $contents = $this->files->get($filename);
        if ($originalDoc) {
            $contents = str_replace($originalDoc, $docComment, $contents);
        } else {
            $needle = "class {$classShortName}";
            $replace = "{$docComment}\nclass {$classShortName}";
            $pos = strpos($contents, $needle);
            if ($pos !== false) {
                $contents = substr_replace($contents, $replace, $pos, strlen($needle));
            }
        }
        /*
        if ($this->files->put($filename, $contents)) {
        $this->info('Written new phpDocBlock to ' . $filename);
        }
         */
        $output = $docComment . "\r\n\r\n";
        return $output;
    }

    public static function getTableInfos($table)
    {

        $columns = DB::select(
            'select
                tmp.attname as "Field",
                typname as "Type",
                d.description as "Comment",
                tmp.attnotnull  as "Null"
            from
            (
                select
                    a.attname,
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
                                    c.relname = \'' . $table . '\'
                                    and a.attnum>0
                                    and a.attrelid = c.oid
                                    and a.atttypid = t.oid) as tmp
            left join pg_description d on
                d.objoid = tmp.attrelid
                and d.objsubid = tmp.attnum
            order by tmp.attnum'
        );

        return $columns;

    }

}
