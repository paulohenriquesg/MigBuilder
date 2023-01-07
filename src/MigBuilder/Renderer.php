<?php

namespace MigBuilder;

class Renderer
{
    private static array $columnTypes = [
        'bigint'     => 'bigInteger',
        'binary'     => 'binary',
        'tinyint'    => 'boolean',
        'bit'        => 'boolean',
        'char'       => 'char',
        'date'       => 'date',
        'datetime'   => 'dateTime',
        'decimal'    => 'decimal',
        'double'     => 'double',
        'float'      => 'float',
        'smallint'   => 'integer',
        'mediumint'  => 'integer',
        'int'        => 'integer',
        'time'       => 'time',
        'timestamp'  => 'timestamp',
        'xxxbigint'  => 'unsignedBigInteger',
        'xxxfloat'   => 'unsignedFloat',
        'varchar'    => 'string',
        'tinytext'   => 'string',
        'text'       => 'string',
        'mediumtext' => 'string',
        'longtext'   => 'longText',
    ];

    public static function migration($table, $columns, $constraints, $timestamps = true): string
    {
        $code = "";
        $indexCode = "";
        $constraintsCode = "";
        $extraDbQueriesCode = "";

        $code .= self::migration_001_class_start($table);
        $code .= self::migration_002_up_start($table);
        foreach ($columns as $column) {
            if (isset($constraints[$column->name])) {
                $column->fk = (object)['ref_table' => $constraints[$column->name]->ref_table, 'ref_column' => $constraints[$column->name]->ref_column];
            }
            $totalCode = self::columnCode($column);
            $code .= $totalCode['code'];
            $indexCode .= $totalCode['indexCode'];
            $constraintsCode .= $totalCode['constraintsCode'];
            $extraDbQueriesCode .= $totalCode['extraDbQueriesCode'];
        }
        if ($timestamps === true) {
            $code .= "            \$table->timestamps();" . "\r\n";
        }
        $code .= "\r\n";
        $code .= "            // Indexes\r\n";
        $code .= $indexCode;
        $code .= "\r\n";
        $code .= "            // Constraints & Foreign Keys\r\n";
        $code .= $constraintsCode;

        $code .= self::migration_003_up_create_end($table);
        $code .= "        // Extra DB code\r\n";
        $code .= str_replace('{{TABLE}}', $table, $extraDbQueriesCode);
        $code .= self::migration_003_up_end($table);
        $code .= self::migration_004_down($table);
        $code .= self::migration_005_class_end();
        return $code;
    }

    public static function model($table, $columns, $constraints, $children, $timestamps): string
    {
        $code = self::model_001_class_start($table);

        // UUID
        foreach ($columns as $column) {
            if ($column->column_key === 'PRI' && $column->data_type === 'char' && $column->max_length === 32) {
                $code .= "    protected \$keyType = 'string';\r\n";
                $code .= "    public \$incrementing = false;\r\n\r\n";
            }
        }

        // Timestamps
        if ($timestamps === false) {
            $code .= "    public \$timestamps = false;\r\n\r\n";
        }

        //Fillable
        $code .= "    // Fillables (remove the columns you don't need)\r\n";
        $code .= "    protected \$fillable = [";
        $idx = 0;
        foreach ($columns as $column) {
            $idx++;
            $code .= "'$column->name', ";
            if ($idx % 8 === 0 && $idx < count($columns)) {
                $code .= "\r\n                           ";
            }
        }
        $code .= "];\r\n";
        $code .= "\r\n";

        //Castings
        $code .= "    // Castings (remove the columns you don't need)\r\n";
        $code .= "    protected \$casts = [";
        foreach ($columns as $column) {
            if ($column->data_type === 'datetime') {
                $code .= "\r\n        '$column->name' => 'datetime', ";
            } elseif ($column->data_type === 'date') {
                $code .= "\r\n        '$column->name' => 'date', ";
            } elseif ($column->data_type === 'tinyint' && $column->column_type === 'tinyint(1)') {
                $code .= "\r\n        '$column->name' => 'boolean', ";
            } else {
            }
        }
        $code .= "\r\n    ];\r\n";
        $code .= "\r\n";

        //Relationships
        $code .= "    // Parent relationships (change belongsTo to belongsToMany or similar if needed)\r\n";
        foreach ($columns as $column) {
            if (isset($constraints[$column->name])) {
                $code .= self::modelRelationship(Util::firstUpper($constraints[$column->name]->ref_table), "belongsTo", $constraints[$column->name]->column_name);
            }
        }
        $code .= "\r\n";
        $code .= "    // Child relationships (change hasMany to hasOne or similar if needed)\r\n";
        foreach ($children as $child) {
            $code .= self::modelRelationship(Util::firstUpper($child), "hasMany");
        }

        $code .= self::model_002_class_end();
        return $code;
    }

    public static function factory($table, $columns, $constraints): string
    {
        $code = self::factory_001_start($table);
        $code .= "        // Record sample structure\r\n";
        $code .= "        return [\r\n";
        foreach ($columns as $column) {
            if ($column->extra === 'auto_increment') {
                // continue;
            }
            $code .= "            //'$column->name' => ";
            if (isset($constraints[$column->name])) {
                $code .= '\App\Models\\' . Util::firstUpper($constraints[$column->name]->ref_table) . "::factory(),\r\n";
            } elseif ($column->data_type === 'char' && $column->max_length === 32) {
                $code .= "fake()->uuid(),\r\n";
            } elseif (in_array($column->data_type, ['int', 'tinyint', 'mediumint', 'bigint', 'smallint'])) {
                $code .= "0,\r\n";
            } elseif ($column->data_type === 'float') {
                $code .= "fake()->randomFloat(2),\r\n";
            } elseif ($column->data_type === 'decimal') {
                $code .= "fake()->randomNumber(5, false),\r\n";
            } elseif ($column->data_type === 'date') {
                $code .= "fake()->date(),\r\n";
            } elseif ($column->data_type === 'datetime') {
                $code .= "fake()->dateTime(),\r\n";
            } elseif ($column->data_type === 'time') {
                $code .= "fake()->time(),\r\n";
            } elseif ($column->data_type === 'char') {
                if ($column->max_length === 1) {
                    $code .= "(string)fake()->numberBetween(0,1),\r\n";
                } else {
                    $code .= "fake()->randomLetter(),\r\n";
                }
            } elseif (in_array($column->data_type, ['longtext', 'text'])) {
                $code .= "fake()->text(),\r\n";
            } elseif (in_array($column->data_type, ['tinytext', 'varchar'])) {
                $code .= "fake()->word(),\r\n";
            } elseif ($column->data_type === 'timestamp') {
                $code .= "'',\r\n";
            } else {
                $code .= " ,\r\n";
            }
        }
        $code .= "        ];\r\n";
        $code .= self::factory_002_end();
        return $code;
    }

    public static function seeder($table, $columns): string
    {
        $code = self::seeder_001_start($table);
        $code .= "    // Record sample structure\r\n";
        $code .= "    \$" . Util::firstUpper($table, false) . " = [\r\n";
        foreach ($columns as $column) {
            $code .= "        //'$column->name' => ";
            if (in_array($column->data_type, ['varchar', 'char', 'text', 'date', 'time', 'datetime', 'timestamp'])) {
                $code .= "'',\r\n";
            } else {
                $code .= " ,\r\n";
            }
        }
        $code .= "    ];\r\n";
        $code .= self::seeder_002_end();
        return $code;
    }


    /***********************************************************************************
     *                                  UTILITIES
     **********************************************************************************/
    private static function columnCode($column): array
    {
        $precision = null;
        $scale = null;
        $length = null;
        $nullable = $column->nullable === 'YES';
        $default = $column->default;
        $isReferred = $column->isReferred;
        $columnType = self::$columnTypes[$column->data_type];
        if ($columnType === "decimal") {
            $precision = $column->num_precision;
            $scale = $column->num_scale;
        }
        if (in_array($column->data_type, ['varchar', 'char'])) {
            $length = $column->max_length;
            if ($default !== null) {
                $default = "'" . $default . "'";
            }
        }
        if ($column->column_key === "PRI") {
            if ($column->extra === 'auto_increment') {
                $columnType = ($column->data_type === 'int') ? "increments" : "id";
            } else {
                $columnType = ($column->data_type === 'int') ? "unsignedInteger" : "unsignedBigInteger";
            }
        }
        if (isset($column->fk) && $column->fk->ref_column === "id") {
            $columnType = ($column->data_type === 'int') ? "unsignedInteger" : "unsignedBigInteger";
        }
        if ($length === 32 && $column->data_type === 'char') {
            $length = null;
            $columnType = 'uuid';
        }
        $indexCode = "";
        $constraintsCode = "";
        $extraDbQueriesCode = "";

        $code = "            \$table->";
        $code .= $columnType . "('$column->name'";
        if ($length !== null) {
            $code .= ", $length";
        }
        if ($columnType === "decimal") {
            $code .= ", $precision";
            $code .= ", $scale";
        }
        $code .= ")";

        if ($nullable) {
            $code .= "->nullable()";
        }

        if ($default !== null) {
            if ($default === 'CURRENT_TIMESTAMP') {
                $code .= "->useCurrent()";
            } else {
                $code .= "->default($default)";
            }
        }
        $code .= ";\r\n";
        if ($isReferred === true && $column->column_key !== "PRI") {
            $indexCode .= "            \$table->index('$column->name')";
            $indexCode .= ";\r\n";
        } elseif ($column->column_key === "PRI" && in_array($columnType, ['unsignedInteger', 'unsignedBigInteger', 'uuid'])) {
            $indexCode .= "            \$table->primary('$column->name')";
            $indexCode .= ";\r\n";
        } elseif ($column->column_key === "MUL" || $column->column_key === "UNI") {
            $indexCode .= "            \$table->index('$column->name')";
            $indexCode .= ";\r\n";
        }
        if (isset($column->fk)) {
            $constraintsCode .= "            \$table->foreign('$column->name')->references('{$column->fk->ref_column}')->on('{$column->fk->ref_table}')";
            $constraintsCode .= ";\r\n";
        }

        if ($column->column_key === "UNI") {
            if ($column->extra === 'auto_increment') {
                $extraDbQueriesCode .= "        \Illuminate\Support\Facades\DB::statement('ALTER TABLE {{TABLE}} MODIFY $column->name $columnType NOT NULL AUTO_INCREMENT;');";
                $extraDbQueriesCode .= "\r\n";
            }
        }
        return ['code' => $code, 'indexCode' => $indexCode, 'constraintsCode' => $constraintsCode, 'extraDbQueriesCode' => $extraDbQueriesCode];
    }

    /***********************************************************************************
     *                                  TEMPLATES
     **********************************************************************************/
    /******************************************************************
     * MIGRATION
     */
    private static function migration_001_class_start($table): string
    {
        return "
<?php
/* Generated automatically using MigBuilder by Pangodream */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Create" . Util::firstUpper($table) . "Table extends Migration
{
        ";
    }

    private static function migration_002_up_start($table): string
    {
        return "
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('$table', function (Blueprint \$table) {
";
    }

    private static function migration_003_up_create_end($table): string
    {
        return "        });

";
    }

    private static function migration_003_up_end($table): string
    {
        return "
    }
";
    }

    private static function migration_004_down($table): string
    {
        return "
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('$table');
        Schema::enableForeignKeyConstraints();
    }
";
    }

    private static function migration_005_class_end(): string
    {
        return "
}
";
    }

    /******************************************************************
     * MODEL
     */
    private static function model_001_class_start($table): string
    {
        return "<?php
/* Generated automatically using MigBuilder by Pangodream */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class " . Util::firstUpper($table) . " extends Model
{
    use HasFactory;
    protected \$table = '$table';

";
    }

    private static function model_002_class_end(): string
    {
        return "
}
";
    }

    /******************************************************************
     * FACTORY
     */
    private static function factory_001_start($table): string
    {
        return "<?php
/* Generated automatically using MigBuilder by Pangodream */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\\" . Util::firstUpper($table) . ";

class " . Util::firstUpper($table) . "Factory extends Factory
{
    protected \$model = " . Util::firstUpper($table) . "::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
";
    }

    private static function factory_002_end(): string
    {
        return "
    }
}";
    }

    /******************************************************************
     * SEEDER
     */
    private static function seeder_001_start($table): string
    {
        return "<?php
/* Generated automatically using MigBuilder by Pangodream */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\\" . Util::firstUpper($table) . ";

class " . Util::firstUpper($table) . "Seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
";
    }

    private static function seeder_002_end(): string
    {
        return "
    }
}";
    }

    /*********************************************************************
     * MODEL Relationship
     */
    private static function modelRelationship($modelName, $relationship, $foreignKey = ''): string
    {
        if ($foreignKey !== '') {
            return "    public function $modelName(){
        return \$this->$relationship($modelName::class, '$foreignKey');
    }
";
        }
        return "    public function $modelName(){
        return \$this->$relationship($modelName::class);
    }
";
    }

}
