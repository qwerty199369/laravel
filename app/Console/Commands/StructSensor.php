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

    private int $ts;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->ts = time();

        Assert::notNull($this->option('domain'));

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

        $_total = count($list);
        foreach ($list as $idx => $item) {
            if (time() - $this->ts > 60) {
                break;
            }

            $loc = "https://www.{$this->option('domain')}/brand/{$item['u']}.html";

            $this->line("[" . (number_format($idx / $_total * 100, 2)) . "%][{$item['u']}]");

            $html = $this->getURLWithDB(
                "$loc#{$item['u']}",
                [
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Host' => "www.{$this->option('domain')}",
                    'User-Agent' => "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
                ],
                'sensor',
                [
                    'kk' => $loc,
                ]
            );

            if ($html === null) {
                continue;
            }

            // $crawler = new Crawler($html);

            preg_match('#company_id=(\d+)#', $html, $matches);
            $company_id = $matches[1];

            $productJson = $this->getURLWithDB(
                "https://cgs.{$this->option('domain')}/v1/company/search-product?company_id=$company_id&page=1&pageSize=20#$company_id",
                [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Host' => "cgs.{$this->option('domain')}",
                    'Referer' => "https://servicewechat.com/" . env('MINI_APPID_1') . "/30/page-frame.html",
                    'User-Agent' => "Mozilla/5.0 (iPhone; CPU iPhone OS 17_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E287 MicroMessenger/8.0.45(0x1801512d) NetType/WIFI Language/zh_CN",
                ],
                'sensor',
                [
                    'kk' => "https://cgs.{$this->option('domain')}/v1/company/search-product?company_id=$company_id.1",
                ]
            );
        }
    }
}
