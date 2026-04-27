<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCodeToBankTableAndSeedBanks extends Migration
{
    /**
     * COMPE / bancos (código => nome oficial legível).
     *
     * @return array<int, array{code: string, name: string}>
     */
    private function banks(): array
    {
        return [
            ['code' => '001', 'name' => 'Banco do Brasil'],
            ['code' => '237', 'name' => 'Bradesco'],
            ['code' => '341', 'name' => 'Itaú Unibanco'],
            ['code' => '033', 'name' => 'Santander'],
            ['code' => '104', 'name' => 'Caixa Econômica Federal'],
            ['code' => '260', 'name' => 'Nubank'],
            ['code' => '077', 'name' => 'Banco Inter'],
            ['code' => '212', 'name' => 'Banco Original'],
            ['code' => '336', 'name' => 'C6 Bank'],
            ['code' => '290', 'name' => 'PagSeguro'],
            ['code' => '380', 'name' => 'PicPay'],
            ['code' => '197', 'name' => 'Stone'],
            ['code' => '655', 'name' => 'Banco Votorantim'],
            ['code' => '041', 'name' => 'Banrisul'],
            ['code' => '748', 'name' => 'Sicredi'],
            ['code' => '756', 'name' => 'Sicoob'],
            ['code' => '085', 'name' => 'Ailos'],
            ['code' => '070', 'name' => 'BRB'],
            ['code' => '389', 'name' => 'Banco Mercantil do Brasil'],
            ['code' => '422', 'name' => 'Safra'],
            ['code' => '399', 'name' => 'HSBC Brasil'],
            ['code' => '246', 'name' => 'ABC Brasil'],
            ['code' => '745', 'name' => 'Citibank'],
            ['code' => '069', 'name' => 'Crefisa'],
        ];
    }

    /**
     * Nomes antigos na base (dump legado) => código COMPE.
     *
     * @return array<string, string>
     */
    private function legacyNameToCode(): array
    {
        return [
            'Itaú' => '341',
            'Santander' => '033',
            'Bradesco' => '237',
            'Caixa' => '104',
        ];
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->string('code', 5)->nullable()->unique()->after('id');
        });

        foreach ($this->legacyNameToCode() as $name => $code) {
            DB::table('bank')
                ->whereNull('code')
                ->where('name', $name)
                ->update(['code' => $code]);
        }

        foreach ($this->banks() as $row) {
            DB::table('bank')->updateOrInsert(
                ['code' => $row['code']],
                ['name' => $row['name']]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
}
