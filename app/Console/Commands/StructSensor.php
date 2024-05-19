<?php

namespace App\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\DomCrawler\Crawler;
use Webmozart\Assert\Assert;

class StructSensor extends BaseBot
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'struct:sensor {--domain=} {--sleep=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(substr(env('MINI_APPID_1'), 0, length: 3));
        exit(1);

        Assert::notNull($this->option('domain'));
        Assert::notNull($this->option('sleep'));

        if (!Schema::hasTable('sensor')) {
            Schema::create('sensor', function (Blueprint $table) {
                $table->string('kk')->unique()->primary();
                $table->text('vv');
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
            });
        }

        if (DB::table('sensor')->where('kk', 'v2.brand.list.json')->doesntExist()) {
            DB::table('sensor')->insert([
                'kk' => 'v2.brand.list.json',
                'vv' => file_get_contents("F:/sf/v2.brand.list.json"),
            ]);
        }

        $list = json_decode(
            DB::table('sensor')
                ->where('kk', 'v2.brand.list.json')
                ->value('vv'),
            true
        )['data']['list'];

        $i = 0;

        foreach ($list as $item) {
            $loc = "https://www.{$this->option('domain')}/brand/{$item['u']}.html";

            $this->line("[$i][{$item['u']}]");

            $html = $this->getURLWithDB(
                "$loc#{$item['u']}",
                [
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Host' => 'www.'.$this->option('domain'),
                    'User-Agent' => "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
                ],
                'sensor',
                [
                    'kk' => $loc,
                ]
            );

            if (!$this->is_read_from_db) {
                $i++;
            }

            if ($i > 100) {
                break;
            }

            if ($html === null) {
                continue;
            }

            // $crawler = new Crawler($html);
        }
    }
}
